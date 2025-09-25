<?php
if (!defined('ABSPATH')) exit;

class Ask_Adam_Lite_Logic {
    /** Hard caps to avoid runaway costs/time */
    private const MODEL        = 'gpt-4o-mini';
    private const TIMEOUT      = 15;              // keep responsive for REST
    private const MAX_PROMPT   = 2000;            // chars after sanitation
    private const MAX_CONTEXT  = 6000;            // chars merged from KB
    private const TEMPERATURE  = 0.7;
    private const MAX_TOKENS   = 900;

    /** Prefer constant, fall back to option */
    private static function api_key() {
        if (defined('AALITE_OPENAI_API_KEY')) {
            $c = trim((string) constant('AALITE_OPENAI_API_KEY'));
            if ($c !== '') return $c;
        }
        $api = get_option('aalite_api_settings', []);
        return (string) ($api['openai'] ?? '');
    }

    /**
     * Main answer method. Returns array or WP_Error.
     */
    public static function answer($prompt) {
        $key = self::api_key();
        if ($key === '') {
            return new WP_Error(
                'aalite_no_key',
                __('Missing OpenAI API key.', 'ask-adam-lite'),
                ['status' => 401]
            );
        }

        // Normalize & guard input length (extra safety; API router already sanitizes)
        $prompt = (string) $prompt;
        $prompt = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]+/u', ' ', $prompt); // strip control chars
        $prompt = trim($prompt);
        if ($prompt === '') {
            return new WP_Error(
                'aalite_empty_prompt',
                __('Empty prompt.', 'ask-adam-lite'),
                ['status' => 400]
            );
        }
        if (mb_strlen($prompt) > self::MAX_PROMPT) {
            $prompt = mb_substr($prompt, 0, self::MAX_PROMPT);
        }

        // Retrieve KB context safely
        $ctx = ['context' => '', 'sources' => []];
        if (class_exists('Ask_Adam_Lite_KB')) {
            $maybe_ctx = Ask_Adam_Lite_KB::retrieve_topk($prompt, 3);
            if (is_array($maybe_ctx)) {
                $ctx['context'] = (string) ($maybe_ctx['context'] ?? '');
                $ctx['sources'] = is_array($maybe_ctx['sources'] ?? null) ? $maybe_ctx['sources'] : [];
            }
        }

        // Trim context to a safe bound
        if ($ctx['context'] !== '' && mb_strlen($ctx['context']) > self::MAX_CONTEXT) {
            $ctx['context'] = mb_substr($ctx['context'], 0, self::MAX_CONTEXT);
        }

        // Compose messages (keep same general approach; context appended clearly)
        $context_block = $ctx['context'] !== '' ? "\n\nContext:\n".$ctx['context'] : '';
        $messages = [
            [
                'role'    => 'system',
                'content' => 'You are Adam, a concise and helpful assistant. Cite sources when provided.'
            ],
            [
                'role'    => 'user',
                'content' => $prompt . $context_block
            ],
        ];

        // Prepare request
        $endpoint = 'https://api.openai.com/v1/chat/completions';
        $args = [
            'timeout' => self::TIMEOUT,
            'redirection' => 2,
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'User-Agent'    => 'AskAdamLite/' . (defined('AALITE_VER') ? AALITE_VER : '1.0') . '; ' . home_url('/'),
            ],
            'body' => wp_json_encode([
                'model'       => self::MODEL,
                'messages'    => $messages,
                'temperature' => self::TEMPERATURE,
                'max_tokens'  => self::MAX_TOKENS,
            ]),
        ];

        $resp = wp_remote_post($endpoint, $args);

        // Transport errors
        if (is_wp_error($resp)) {
            return new WP_Error(
                'aalite_openai_transport',
                sprintf(
                    /* translators: %s: transport error message */
                    __('Network error contacting model: %s', 'ask-adam-lite'),
                    $resp->get_error_message()
                ),
                ['status' => 502]
            );
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $raw  = (string) wp_remote_retrieve_body($resp);

        // Decode body defensively
        $body = json_decode($raw, true);
        if ($body === null && json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'aalite_openai_json',
                __('Invalid response from model (JSON parse failed).', 'ask-adam-lite'),
                ['status' => 502]
            );
        }

        // Non-2xx HTTP handling (don’t leak secrets/raw bodies)
        if ($code < 200 || $code >= 300) {
            $safe_msg = (string) ($body['error']['message'] ?? '');
            if ($safe_msg === '') {
                // fallback to short excerpt of raw body without newlines
                $excerpt = preg_replace('/\s+/', ' ', mb_substr($raw, 0, 200));
                $safe_msg = sprintf(__('HTTP %d: %s', 'ask-adam-lite'), $code, $excerpt);
            }
            return new WP_Error('aalite_openai_http', $safe_msg, ['status' => $code]);
        }

        // Extract answer
        $answer = (string) ($body['choices'][0]['message']['content'] ?? '');
        if ($answer === '') {
            return new WP_Error(
                'aalite_openai_empty',
                __('The model returned an empty answer.', 'ask-adam-lite'),
                ['status' => 502]
            );
        }

        return [
            'answer'  => $answer,
            'source'  => 'openai',
            'sources' => $ctx['sources'],
            'meta'    => [
                'provider'  => 'openai',
                'model'     => self::MODEL,
                'timestamp' => time(),
            ],
        ];
    }
}
