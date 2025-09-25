<?php
if (!defined('ABSPATH')) exit;

class Ask_Adam_Lite_Logic {
    private static function api_key() {
        if (defined('AALITE_OPENAI_API_KEY') && trim((string)constant('AALITE_OPENAI_API_KEY'))!=='') {
            return trim((string)constant('AALITE_OPENAI_API_KEY'));
        }
        $api = get_option('aalite_api_settings', []);
        return (string)($api['openai'] ?? '');
    }

    public static function answer($prompt) {
        $key = self::api_key();
        if ($key === '') return new WP_Error('no_key','Missing OpenAI API key',['status'=>401]);

        // Retrieve KB context
        $ctx = Ask_Adam_Lite_KB::retrieve_topk($prompt, 3);
        $messages = [
            ['role'=>'system','content'=>'You are Adam, a concise and helpful assistant. Cite sources when provided.'],
            ['role'=>'user','content'=>$prompt . ($ctx['context'] ? "\n\nContext:\n".$ctx['context'] : '')],
        ];

        $resp = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer '.$key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'       => 'gpt-4o-mini',
                'messages'    => $messages,
                'temperature' => 0.7,
                'max_tokens'  => 900,
            ]),
        ]);

        if (is_wp_error($resp)) return new WP_Error('openai_transport',$resp->get_error_message(),['status'=>500]);
        $code = wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code < 200 || $code >= 300) return new WP_Error('openai_http','HTTP '.$code.': '.substr(wp_remote_retrieve_body($resp),0,200),['status'=>$code]);

        $answer = (string)($body['choices'][0]['message']['content'] ?? '');
        return [
            'answer'  => $answer,
            'source'  => 'openai',
            'sources' => $ctx['sources'],
            'meta'    => ['provider'=>'openai','model'=>'gpt-4o-mini','timestamp'=>time()],
        ];
    }
}
