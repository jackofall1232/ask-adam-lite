<?php
if (!defined('ABSPATH')) exit;

class Ask_Adam_Lite_KB {
    const MAX_PAGES  = 50;
    const MAX_CHUNKS = 300;
    const TOP_K      = 3;

    public static function maybe_install_db() {
        global $wpdb;
        $docs   = $wpdb->prefix.'aalite_kb_docs';
        $chunks = $wpdb->prefix.'aalite_kb_chunks';
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE $docs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            url TEXT NOT NULL,
            url_hash CHAR(64) UNIQUE,
            title TEXT,
            source_hash CHAR(64),
            last_crawled DATETIME,
            priority TINYINT DEFAULT 0,
            status VARCHAR(20) DEFAULT 'new',
            error TEXT
        ) DEFAULT CHARSET=utf8mb4;");
        dbDelta("CREATE TABLE $chunks (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            doc_id BIGINT UNSIGNED,
            chunk_index INT,
            content LONGTEXT,
            embedding LONGTEXT,
            tokens INT,
            created_at DATETIME,
            KEY doc_id (doc_id)
        ) DEFAULT CHARSET=utf8mb4;");
    }

    public static function purge_index() {
        global $wpdb;
        $wpdb->query('TRUNCATE TABLE '.$wpdb->prefix.'aalite_kb_chunks');
        $wpdb->query('TRUNCATE TABLE '.$wpdb->prefix.'aalite_kb_docs');
    }

    public static function crawl_from_settings() {
        $kb = get_option('aalite_kb_settings', []);
        $sitemap  = esc_url_raw($kb['sitemap_url'] ?? '');
        $priority = esc_url_raw($kb['priority_url'] ?? '');
        $urls = [];
        if ($priority) $urls[] = $priority;
        if ($sitemap)  $urls = array_merge($urls, self::parse_sitemap($sitemap, self::MAX_PAGES));
        $urls = array_values(array_unique($urls));
        self::crawl_list(array_slice($urls, 0, self::MAX_PAGES), $priority);
    }

    private static function parse_sitemap($sitemap, $limit) {
        $res = wp_remote_get($sitemap, ['timeout'=>15]);
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

    private static function chunk_text($text, $targetChars = 3600, $overlap = 600) {
        $paras = preg_split("/\n{2,}/u", $text);
        $chunks = []; $buf = '';
        foreach ($paras as $p) {
            $p = trim($p); if ($p==='') continue;
            if (mb_strlen($buf)+mb_strlen($p)+2 > $targetChars) {
                if ($buf!=='') { $chunks[] = $buf; $buf = mb_substr($buf, max(0, mb_strlen($buf)-$overlap)); }
            }
            $buf .= ($buf===''?'':"\n\n").$p;
        }
        if (trim($buf)!=='') $chunks[] = trim($buf);
        return array_map(function($c,$i){ return ['index'=>$i,'content'=>$c,'tokens'=>(int)ceil(mb_strlen($c)/4)]; }, $chunks, array_keys($chunks));
    }

    private static function fetch_text($url) {
        $r = wp_remote_get($url, ['timeout'=>15,'headers'=>['User-Agent'=>'AdamLiteBot/1.0']]);
        if (is_wp_error($r)) return ['error'=>$r->get_error_message()];
        if (wp_remote_retrieve_response_code($r) !== 200) return ['error'=>'HTTP'];
        $html = wp_remote_retrieve_body($r); if (!$html) return ['error'=>'empty'];
        $dom = new DOMDocument(); libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NOWARNING|LIBXML_NOERROR); libxml_clear_errors();
        foreach (['script','style','noscript','svg','iframe','nav','header','footer'] as $tag) {
            $nodes = $dom->getElementsByTagName($tag);
            for ($i=$nodes->length-1; $i>=0; $i--) { $n=$nodes->item($i); $n->parentNode && $n->parentNode->removeChild($n); }
        }
        $xp = new DOMXPath($dom);
        $parts = [];
        foreach (['h1','h2','h3'] as $h) foreach ($xp->query('//'.$h) as $n) { $t=trim($n->textContent); if ($t!=='') $parts[] = strtoupper($h).': '.$t; }
        foreach ($xp->query('//p') as $n) { $t=trim($n->textContent); if ($t!=='') $parts[]=$t; }
        foreach ($xp->query('//li') as $n) { $t=trim($n->textContent); if ($t!=='') $parts[]='• '.$t; }
        $text = trim(implode("\n\n", $parts));
        if ($text==='') return ['error'=>'no text'];
        $title = '';
        $ts = $dom->getElementsByTagName('title');
        if ($ts && $ts->length) $title = trim($ts->item(0)->textContent);
        return ['title'=>$title ?: $url, 'text'=>$text, 'hash'=>hash('sha256',$text)];
    }

    private static function crawl_list($urls, $priority) {
        global $wpdb;
        $T1 = $wpdb->prefix.'aalite_kb_docs';
        $T2 = $wpdb->prefix.'aalite_kb_chunks';
        $added=0; $count=0;
        foreach ($urls as $u) {
            if ($count >= self::MAX_PAGES) break; $count++;
            $res = self::fetch_text($u);
            if (!empty($res['error'])) continue;
            $url_hash = hash('sha256', $u);
            $doc = $wpdb->get_row($wpdb->prepare("SELECT * FROM $T1 WHERE url_hash=%s",$url_hash), ARRAY_A);
            $now = current_time('mysql');
            $priority_flag = ($u === $priority) ? 1 : 0;
            if ($doc) {
                $wpdb->update($T1, ['title'=>$res['title'],'source_hash'=>$res['hash'],'last_crawled'=>$now,'priority'=>max((int)$doc['priority'],$priority_flag),'status'=>'indexed','error'=>''], ['id'=>(int)$doc['id']]);
                $wpdb->delete($T2, ['doc_id'=>(int)$doc['id']]);
                $doc_id = (int)$doc['id'];
            } else {
                $wpdb->insert($T1, ['url'=>$u,'url_hash'=>$url_hash,'title'=>$res['title'],'source_hash'=>$res['hash'],'last_crawled'=>$now,'priority'=>$priority_flag,'status'=>'indexed','error'=>'']);
                $doc_id = (int)$wpdb->insert_id;
            }
            foreach (self::chunk_text($res['text']) as $ch) {
                // Cap chunks globally
                $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM $T2");
                if ($total >= self::MAX_CHUNKS) break 2;
                $wpdb->insert($T2, [
                    'doc_id'=>$doc_id,'chunk_index'=>$ch['index'],'content'=>$ch['content'],
                    'embedding'=>null,'tokens'=>$ch['tokens'],'created_at'=>current_time('mysql')
                ]);
            }
            $added++;
        }
        return $added;
    }

    public static function embed_pending($limit=50) {
        global $wpdb;
        $T2 = $wpdb->prefix.'aalite_kb_chunks';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, content FROM $T2 WHERE embedding IS NULL OR embedding='' LIMIT %d", max(1,(int)$limit)), ARRAY_A);
        if (!$rows) return 0;

        $key = defined('AALITE_OPENAI_API_KEY') ? constant('AALITE_OPENAI_API_KEY') : (get_option('aalite_api_settings',[])['openai'] ?? '');
        if (!$key) return 0;

        $inputs = array_map(fn($r)=>$r['content'], $rows);
        $resp = wp_remote_post('https://api.openai.com/v1/embeddings', [
            'timeout'=>20,
            'headers'=>['Authorization'=>'Bearer '.$key,'Content-Type'=>'application/json'],
            'body'=> wp_json_encode(['model'=>'text-embedding-3-small','input'=>$inputs]),
        ]);
        if (is_wp_error($resp)) return 0;
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $vecs = $body['data'] ?? [];
        foreach ($rows as $i=>$r) {
            if (!isset($vecs[$i]['embedding'])) continue;
            $emb = wp_json_encode($vecs[$i]['embedding']);
            $wpdb->update($T2, ['embedding'=>$emb], ['id'=>(int)$r['id']]);
        }
        return count($rows);
    }

    public static function retrieve_topk($query, $k) {
        global $wpdb;
        $T1 = $wpdb->prefix.'aalite_kb_docs';
        $T2 = $wpdb->prefix.'aalite_kb_chunks';

        // Embed query
        $key = defined('AALITE_OPENAI_API_KEY') ? constant('AALITE_OPENAI_API_KEY') : (get_option('aalite_api_settings',[])['openai'] ?? '');
        if (!$key) return ['context'=>'','sources'=>[]];
        $resp = wp_remote_post('https://api.openai.com/v1/embeddings', [
            'timeout'=>12,
            'headers'=>['Authorization'=>'Bearer '.$key,'Content-Type'=>'application/json'],
            'body'=> wp_json_encode(['model'=>'text-embedding-3-small','input'=>$query]),
        ]);
        if (is_wp_error($resp)) return ['context'=>'','sources'=>[]];
        $qvec = (json_decode(wp_remote_retrieve_body($resp), true)['data'][0]['embedding'] ?? null);
        if (!$qvec) return ['context'=>'','sources'=>[]];

        // Load embeddings to score (Lite: fetch limited subset for simplicity)
        $rows = $wpdb->get_results("SELECT c.id, c.content, c.embedding, d.url, d.title
                                    FROM $T2 c INNER JOIN $T1 d ON d.id=c.doc_id
                                    WHERE c.embedding IS NOT NULL AND c.embedding <> ''
                                    LIMIT ".(self::MAX_CHUNKS), ARRAY_A);
        $scores = [];
        foreach ($rows as $r) {
            $emb = json_decode($r['embedding'], true);
            if (!is_array($emb)) continue;
            // Cosine similarity
            $dot=0;$na=0;$nb=0;
            $len = min(count($emb), count($qvec));
            for ($i=0;$i<$len;$i++){ $a=$emb[$i]; $b=$qvec[$i]; $dot+= $a*$b; $na+= $a*$a; $nb+= $b*$b; }
            $sim = ($na>0 && $nb>0) ? ($dot / (sqrt($na)*sqrt($nb))) : 0;
            $scores[] = ['sim'=>$sim,'content'=>$r['content'],'url'=>$r['url'],'title'=>$r['title']];
        }
        usort($scores, fn($a,$b)=> $a['sim'] < $b['sim'] ? 1 : -1);
        $top = array_slice($scores, 0, max(1,(int)$k));
        $context = implode("\n\n---\n\n", array_column($top,'content'));
        $sources = array_values(array_unique(array_map(fn($t)=> ['title'=>$t['title'],'url'=>$t['url']], $top), SORT_REGULAR));
        return ['context'=>$context,'sources'=>$sources];
    }
}
