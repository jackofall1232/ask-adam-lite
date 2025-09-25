<?php
if (!defined('ABSPATH')) exit;

class Ask_Adam_Lite_API {
    public function __construct() {
        add_action('rest_api_init', [$this, 'routes']);
    }
    public function routes() {
        register_rest_route('adam-lite/v1', '/chat', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle'],
            'permission_callback' => function($req){
                $nonce = $req->get_header('x-wp-nonce');
                if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) return new WP_Error('forbidden','Bad nonce',['status'=>403]);
                // simple rate guard
                $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $key = 'aalite_rl_' . md5($ip);
                $n = (int)get_transient($key);
                if ($n >= 60) return new WP_Error('rate_limited','Try later',['status'=>429]);
                set_transient($key, $n+1, HOUR_IN_SECONDS);
                return true;
            },
            'args' => [
                'prompt' => ['type'=>'string','required'=>true],
            ],
        ]);
    }
    public function handle(WP_REST_Request $req) {
        $prompt = sanitize_text_field($req->get_param('prompt') ?? '');
        if ($prompt === '') return new WP_Error('empty','Empty prompt',['status'=>400]);
        $out = Ask_Adam_Lite_Logic::answer($prompt);
        if (is_wp_error($out)) return $out;
        return new WP_REST_Response(['success'=>true,'data'=>$out], 200);
    }
}
new Ask_Adam_Lite_API();
