<?php
if (!defined('ABSPATH')) exit;

class Ask_Adam_Lite_KB {
    const MAX_PAGES  = 50;
    const MAX_CHUNKS = 300;
    const TOP_K      = 3;

    /** Single user-agent we’ll use for all outbound requests */
    private static function ua(): string {
        return 'AdamLiteBot/1.0 (+'.home_url('/').')';
    }

    /** Create/upgrade KB tables with proper collation + helpful indexes */
    public static function maybe_install_db() {
        global $wpdb;
        $docs   = $wpdb->prefix . 'aalite_kb_docs';
        $chunks = $wpdb->prefix . 'aalite_kb_chunks';
        $collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("
            CREATE TABLE $docs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                url TEXT NOT NULL,
                url_hash CHAR(64) UNIQUE,
                title TEXT,
                source_hash CHAR(64),
                last_crawled DATETIME,
                priority TINYINT DEFAULT 0,
                status VARCHAR(20) DEFAULT 'new',
                error TEXT,
                KEY source_hash (source_hash),
                KEY last_crawled (last_crawled),
                KEY priority (priority)
            ) $collate;
        ");

        dbDelta("
            CREATE TABLE $chunks (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                doc_id BIGINT UNSIGNED,
                chunk_index INT,
                content LONGTEXT,
                embedding LONGTEXT,
                tokens INT,
                created_at DATETIME,
                KEY doc_id (doc_id),
                KEY doc_idx (doc_id, chunk_index)
            ) $collate;
        ");
    }

    /** Drop all docs/chunks */
    public static function purge_index() {
        global $wpdb;
        $wpdb->query('TRUNCATE TABLE `'.$wpdb->prefix.'aalite_kb_chunks`');
        $wpdb->query('TRUNCATE TABLE `'.$wpdb->prefix.'aalite_kb_docs`');
    }

    /** Crawl based on saved options (same-host only) */
    public static function crawl_from_settings() {
        $kb = get_option('aalite_kb_settings', []);
        $sitemap  = esc_url_raw($kb['sitemap_url'] ?? '');
        $priority = esc_url_raw($kb['priority_url'] ?? '');

        $urls = [];
        if ($priority) $urls[] = $priority;
        if ($sitemap)  $urls = array_merge($urls, self::parse_sitemap($sitemap, self::MAX_PAGES));

        // Same-host filter (safety + reviewer-friendly)
        $urls = self::filter_same_host($urls, home_url());

        $urls = array_values(array_unique($urls));
        self::crawl_list(array_slice($urls, 0, self::MAX_PAGES), $priority);
    }

    /** Parse a sitemap for URLs (shallow, host-neutral until we filter later) */
    private static function parse_sitemap($sitemap, $limit) {
        $res = wp_remote_get($sitemap, [
            'timeout' => 15,
            'headers' => ['User-Agent' => self::ua()],
        ]);
        if (is_wp_error($res)) return [];
        $xml = wp_remote_retrieve_body($res);
        if (!$xml) return [];

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        if (!$dom->loadXML($xml)) { libxml_clear_errors(); return []; }
        libxml_clear_errors();

        $xp = new DOMXPath($dom);
        $out = [];
        foreach ($xp->query('//*[local-name()="loc"]') as $n) {
            $u = trim($n->textContent ?? '');
            if ($u) $out[] = $u;
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    /** Keep only URLs on the same host as $base */
    private static function filter_same_host(array $urls, string $base): array {
        $bh = wp_parse_url($base, PHP_URL_HOST);
        if (!$bh) return [];
        $out = [];
        foreach ($urls as $u) {
            $uh = wp_parse_url($u, PHP_URL_HOST);
            if ($uh && strtolower($uh) === strtolower($bh)) $out[] = $u;
        }
        return $out;
    }

    /** Basic robots.txt allow check (cached per host) */
    private static function robots_allows(string $url): bool {
        $p = wp_parse_url($url);
        if (!$p || empty($p['host'])) return false;
        $host = strtolower($p['host']);
        $scheme = $p['scheme'] ?? 'https';
        $key = 'aalite_robots_' . md5($scheme.'://'.$host);
        $rules = get_transient($key);

        if (!is_array($rules)) {
            $robots_url = $scheme.'://'.$host.'/robots.txt';
            $res = wp_remote_get($robots_url, [
                'timeout' => 8,
                'headers' => ['User-Agent' => self::ua()],
            ]);
            $rules = ['disallow' => []];
            if (!is_wp_error($res) && wp_remote_retrieve_response_code($res) === 200) {
                $body = wp_remote_retrieve_body($res);
                if (is_string($body) && $body !== '') {
                    $lines = preg_split('/\r\n|\r|\n/', $body);
                    $ua_block = false;
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if ($line === '' || strpos($line, '#') === 0) continue;
                        if (stripos($line, 'User-agent:') === 0) {
                            $ua = trim(substr($line, 11));
                            // Treat '*' as applicable to us; simple parser
                            $ua_block = ($ua === '*' || $ua === 'AdamLiteBot/1.0');
                        } elseif ($ua_block && stripos($line, 'Disallow:') === 0) {
                            $path = trim(substr($line, 9));
                            if ($path !== '') $rules['disallow'][] = $path;
                        }
                    }
                }
            }
            set_transient($key, $rules, HOUR_IN_SECONDS);
        }

        // If no rules, allow
        if (empty($rules['disallow'])) return true;

        $path = $p['path'] ?? '/';
        foreach ($rules['disallow'] as $d) {
            if ($d === '/') return false; // everything blocked
            if ($d !== '' && strpos($path, $d) === 0) return false;
        }
        return true;
    }

    /** Chunk plain text into ~targetChars with overlap */
    private static function chunk_text($text, $targetChars = 3600, $overlap = 600) {
        $paras = preg_split("/\n{2,}/u", $text);
        $chunks = []; $buf = '';
        foreach ($paras as $p) {
            $p = trim($p); if ($p==='') continue;
            if (mb_strlen($buf)+mb_strlen($p)+2 > $targetChars) {
                if ($buf!=='') {
                    $chunks[] = $buf;
                    $buf = mb_substr($buf, max(0, mb_strlen($buf)-$overlap));
                }
            }
            $buf .= ($buf===''?'':"\n\n").$p;
        }
        if (trim($buf)!=='') $chunks[] = trim($buf);

        $out = [];
        foreach ($chunks as $i => $c) {
            $out[] = [
                'index'   => $i,
                'content' => $c,
                'tokens'  => (int) ceil(mb_strlen($c) / 4),
            ];
        }
        return $out;
    }

    /** Fetch + extract readable text from a same-host URL, respecting simple robots + meta noindex */
    private static function fetch_text($url) {
        // Enforce same-host as the site
        $home_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $url_host  = wp_parse_url($url, PHP_URL_HOST);
        if (!$home_host || !$url_host || strtolower($home_host) !== strtolower($url_host)) {
            return ['error' => 'offsite'];
        }

        // Basic robots allow
        if (!self::robots_allows($url)) {
            return ['error' => 'robots'];
        }

        $r = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => ['User-Agent' => self::ua()],
        ]);
        if (is_wp_error($r)) return ['error'=>$r->get_error_message()];
        if (wp_remote_retrieve_response_code($r) !== 200) return ['error'=>'HTTP'];

        $html = wp_remote_retrieve_body($r);
        if (!$html) return ['error'=>'empty'];

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NOWARNING|LIBXML_NOERROR);
        libxml_clear_errors();

        // Honor <meta name="robots" content="noindex">
        $metaNodes = $dom->getElementsByTagName('meta');
        foreach ($metaNodes as $m) {
            $name = strtolower((string)$m->getAttribute('name'));
            if ($name === 'robots') {
                $content = strtolower((string)$m->getAttribute('content'));
                if (strpos($content, 'noindex') !== false) {
                    return ['error' => 'noindex'];
                }
            }
        }

        // Remove noise
        foreach (['script','style','noscript','svg','iframe','nav','header','footer'] as $tag) {
            $nodes = $dom->getElementsByTagName($tag);
            for ($i=$nodes->length-1; $i>=0; $i--) {
                $n = $nodes->item($i);
                if ($n && $n->parentNode) $n->parentNode->removeChild($n);
            }
        }

        $xp = new DOMXPath($dom);
        $parts = [];

        foreach (['h1','h2','h3'] as $h) {
            foreach ($xp->query('//'.$h) as $n) {
                $t = trim($n->textContent ?? '');
                if ($t !== '') $parts[] = strtoupper($h).': '.$t;
            }
        }
        foreach ($xp->query('//p') as $n) {
            $t = trim($n->textContent ?? '');
            if ($t !== '') $parts[] = $t;
        }
        foreach ($xp->query('//li') as $n) {
            $t = trim($n->textContent ?? '');
            if ($t !== '') $parts[] = '• '.$t;
        }

        $text = trim(implode("\n\n", $parts));
        if ($text === '') return ['error' => 'no text'];

        $title = '';
        $ts = $dom->getElementsByTagName('title');
        if ($ts && $ts->length) $title = trim($ts->item(0)->textContent ?? '');

        return [
            'title' => $title ?: $url,
            'text'  => $text,
            'hash'  => hash('sha256', $text),
        ];
    }

    /** Crawl & index a list of URLs (respects global caps) */
    private static function crawl_list($urls, $priority) {
        global $wpdb;
        $T1 = $wpdb->prefix.'aalite_kb_docs';
        $T2 = $wpdb->prefix.'aalite_kb_chunks';

        $added = 0; $count = 0;
        foreach ($urls as $u) {
            if ($count >= self::MAX_PAGES) break;
            $count++;

            $res = self::fetch_text($u);
            if (!empty($res['error'])) continue;

            $url_hash = hash('sha256', $u);
            $doc = $wpdb->get_row(
                $wpdb->prepare("SELECT id, priority FROM $T1 WHERE url_hash=%s", $url_hash),
                ARRAY_A
            );

            $now = current_time('mysql');
            $priority_flag = ($u === $priority) ? 1 : 0;

            if ($doc) {
                $wpdb->update(
                    $T1,
                    [
                        'title'        => $res['title'],
                        'source_hash'  => $res['hash'],
                        'last_crawled' => $now,
                        'priority'     => max((int)$doc['priority'], $priority_flag),
                        'status'       => 'indexed',
                        'error'        => ''
                    ],
                    ['id' => (int)$doc['id']]
                );
                $wpdb->delete($T2, ['doc_id' => (int)$doc['id']]);
                $doc_id = (int)$doc['id'];
            } else {
                $wpdb->insert($T1, [
                    'url'          => $u,
                    'url_hash'     => $url_hash,
                    'title'        => $res['title'],
                    'source_hash'  => $res['hash'],
                    'last_crawled' => $now,
                    'priority'     => $priority_flag,
                    'status'       => 'indexed',
                    'error'        => ''
                ]);
                $doc_id = (int)$wpdb->insert_id;
            }

            foreach (self::chunk_text($res['text']) as $ch) {
                // Cap chunks globally
                $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM $T2");
                if ($total >= self::MAX_CHUNKS) break 2;

                $wpdb->insert($T2, [
                    'doc_id'      => $doc_id,
                    'chunk_index' => $ch['index'],
                    'content'     => $ch['content'],
                    'embedding'   => null,
                    'tokens'      => $ch['tokens'],
                    'created_at'  => current_time('mysql')
                ]);
            }

            $added++;
        }

        return $added;
    }

    /** Generate embeddings for pending chunks (batched) */
    public static function embed_pending($limit = 50) {
        global $wpdb;
        $T2 = $wpdb->prefix.'aalite_kb_chunks';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, content FROM $T2 WHERE embedding IS NULL OR embedding='' LIMIT %d",
                max(1, (int)$limit)
            ),
            ARRAY_A
        );
        if (!$rows) return 0;

        $key = defined('AALITE_OPENAI_API_KEY')
            ? constant('AALITE_OPENAI_API_KEY')
            : (get_option('aalite_api_settings',[])['openai'] ?? '');

        if (!$key) return 0;

        $inputs = array_map(function($r){ return (string)$r['content']; }, $rows);

        $resp = wp_remote_post('https://api.openai.com/v1/embeddings', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer '.$key,
                'Content-Type'  => 'application/json',
                'User-Agent'    => self::ua(),
            ],
            'body' => wp_json_encode([
                'model' => 'text-embedding-3-small',
                'input' => $inputs
            ]),
        ]);

        if (is_wp_error($resp)) return 0;
        $code = wp_remote_retrieve_response_code($resp);
        if ($code !== 200) return 0;

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $vecs = $body['data'] ?? null;
        if (!is_array($vecs)) return 0;

        $updated = 0;
        foreach ($rows as $i => $r) {
            if (!isset($vecs[$i]['embedding']) || !is_array($vecs[$i]['embedding'])) continue;
            $emb = wp_json_encode($vecs[$i]['embedding']);
            $wpdb->update($T2, ['embedding' => $emb], ['id' => (int)$r['id']]);
            $updated++;
        }

        return $updated;
    }

    /** Retrieve top-k chunks (simple cosine), return context + distinct sources */
    public static function retrieve_topk($query, $k) {
        global $wpdb;
        $T1 = $wpdb->prefix.'aalite_kb_docs';
        $T2 = $wpdb->prefix.'aalite_kb_chunks';

        // Embed query
        $key = defined('AALITE_OPENAI_API_KEY')
            ? constant('AALITE_OPENAI_API_KEY')
            : (get_option('aalite_api_settings',[])['openai'] ?? '');

        if (!$key) return ['context'=>'','sources'=>[]];

        $resp = wp_remote_post('https://api.openai.com/v1/embeddings', [
            'timeout' => 12,
            'headers' => [
                'Authorization' => 'Bearer '.$key,
                'Content-Type'  => 'application/json',
                'User-Agent'    => self::ua(),
            ],
            'body' => wp_json_encode([
                'model' => 'text-embedding-3-small',
                'input' => (string)$query
            ]),
        ]);
        if (is_wp_error($resp)) return ['context'=>'','sources'=>[]];

        $code = wp_remote_retrieve_response_code($resp);
        if ($code !== 200) return ['context'=>'','sources'=>[]];

        $rbody = json_decode(wp_remote_retrieve_body($resp), true);
        $qvec = $rbody['data'][0]['embedding'] ?? null;
        if (!is_array($qvec)) return ['context'=>'','sources'=>[]];

        // Load a capped set of embeddings
        $rows = $wpdb->get_results("
            SELECT c.id, c.content, c.embedding, d.url, d.title, d.priority
            FROM $T2 c
            INNER JOIN $T1 d ON d.id = c.doc_id
            WHERE c.embedding IS NOT NULL AND c.embedding <> ''
            LIMIT ".(int)self::MAX_CHUNKS,
            ARRAY_A
        );
        if (!$rows) return ['context'=>'','sources'=>[]];

        $scores = [];
        foreach ($rows as $r) {
            $emb = json_decode($r['embedding'], true);
            if (!is_array($emb)) continue;

            // Cosine similarity
            $dot = 0.0; $na = 0.0; $nb = 0.0;
            $len = min(count($emb), count($qvec));
            for ($i = 0; $i < $len; $i++) {
                $a = (float)$emb[$i];
                $b = (float)$qvec[$i];
                $dot += $a * $b; $na += $a * $a; $nb += $b * $b;
            }
            $sim = ($na > 0 && $nb > 0) ? ($dot / (sqrt($na) * sqrt($nb))) : 0.0;

            $scores[] = [
                'sim'     => $sim,
                'content' => (string)$r['content'],
                'url'     => (string)$r['url'],
                'title'   => (string)$r['title'],
                'priority'=> (int)$r['priority'],
            ];
        }

        // Sort by similarity desc, then priority desc (tie-break)
        usort($scores, function($a, $b){
            if ($a['sim'] === $b['sim']) return $b['priority'] <=> $a['priority'];
            return ($a['sim'] < $b['sim']) ? 1 : -1;
        });

        $top = array_slice($scores, 0, max(1, (int)$k));
        $context = implode("\n\n---\n\n", array_column($top, 'content'));

        // Distinct sources (preserve order)
        $seen = [];
        $sources = [];
        foreach ($top as $t) {
            $key = $t['url'];
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $sources[] = ['title' => $t['title'], 'url' => $t['url']];
        }

        return ['context' => $context, 'sources' => $sources];
    }
}
