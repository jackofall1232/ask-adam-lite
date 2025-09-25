<?php
if (!defined('ABSPATH')) exit;

class Ask_Adam_Lite_API {
    public function __construct() {
        add_action('rest_api_init', [$this, 'routes']);
    }

    public function routes() {
        register_rest_route('adam-lite/v1', '/chat', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle'],
            'permission_callback' => function (WP_REST_Request $req) {
                // Require REST nonce (works for logged-in users). This matches your current front-end.
                $nonce = $req->get_header('x-wp-nonce');
                if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
                    return new WP_Error(
                        'rest_forbidden',
                        __('Invalid or missing security token.', 'ask-adam-lite'),
                        ['status' => 403]
                    );
                }

                // Basic rate limiting per IP (5-minute window)
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                if (!function_exists('rest_is_ip_address') || !rest_is_ip_address($ip)) {
                    $ip = '0.0.0.0';
                }

                $key = 'aalite_rl_' . md5($ip);
                $count = (int) get_transient($key);

                // 30 requests per 5 minutes per IP (adjust to taste)
                if ($count >= 30) {
                    return new WP_Error(
                        'rest_rate_limited',
                        __('Too many requests. Please try again shortly.', 'ask-adam-lite'),
                        ['status' => 429]
                    );
                }

                // Start / bump counter (5-minute TTL)
                set_transient($key, $count + 1, 5 * MINUTE_IN_SECONDS);
                return true;
            },
            'show_in_index' => false,
            'args' => [
                'prompt' => [
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($value) {
                        if (!is_string($value)) {
                            return new WP_Error(
                                'rest_invalid_param',
                                __('Prompt must be a string.', 'ask-adam-lite')
                            );
                        }
                        $len = mb_strlen($value);
                        if ($len < 1) {
                            return new WP_Error(
                                'rest_invalid_param',
                                __('Prompt cannot be empty.', 'ask-adam-lite')
                            );
                        }
                        if ($len > 2000) {
                            return new WP_Error(
                                'rest_invalid_param',
                                __('Prompt is too long (max 2000 characters).', 'ask-adam-lite')
                            );
                        }
                        return true;
                    },
                ],
            ],
        ]);
    }

    public function handle(WP_REST_Request $req) {
        // Double-guard in handler (defense in depth)
        $prompt = $req->get_param('prompt');
        $prompt = is_string($prompt) ? sanitize_text_field($prompt) : '';
        $len    = mb_strlen($prompt);

        if ($len < 1) {
            return new WP_Error(
                'rest_invalid_param',
                __('Prompt cannot be empty.', 'ask-adam-lite'),
                ['status' => 400]
            );
        }
        if ($len > 2000) {
            return new WP_Error(
                'rest_invalid_param',
                __('Prompt is too long (max 2000 characters).', 'ask-adam-lite'),
                ['status' => 400]
            );
        }

        $out = Ask_Adam_Lite_Logic::answer($prompt);
        if (is_wp_error($out)) {
            // Ensure WP_Error is returned as a REST error
            return $out;
        }

        return rest_ensure_response([
            'success' => true,
            'data'    => $out,
        ]);
    }
}

new Ask_Adam_Lite_API();
