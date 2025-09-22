<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Ask_Adam_Lite_REST' ) ) {

class Ask_Adam_Lite_REST {
    private static $instance = null;
    const NAMESPACE = 'ask-adam-lite/v1';

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route(
            self::NAMESPACE,
            '/chat',
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => [ $this, 'handle_chat' ],
                    'permission_callback' => [ $this, 'check_permissions' ],
                    'args'                => array(
                        'prompt' => array(
                            'type'              => 'string',
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'kb' => array(
                            'type'     => 'boolean',
                            'required' => false,
                        ),
                        'model' => array(
                            'type'              => 'string',
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'max_tokens' => array(
                            'type'     => 'integer',
                            'required' => false,
                        ),
                        'temperature' => array(
                            'type'     => 'number',
                            'required' => false,
                        ),
                        'image_data_url' => array(
                            'type'     => 'string',
                            'required' => false,
                        ),
                        'channel' => array(
                            'type'              => 'string',
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Require a valid REST nonce (sent as X-WP-Nonce) for this endpoint.
     * Note: WP REST nonces require a logged-in session. For public use,
     * you’ll want to switch to your own token scheme or Application Passwords.
     */
    public function check_permissions( WP_REST_Request $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error(
                'adam_lite_invalid_nonce',
                __( 'Invalid or missing nonce.', ADAML_TEXT_DOMAIN ),
                array( 'status' => 403 )
            );
        }
        return true;
    }

    public function handle_chat( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        if ( ! is_array( $params ) ) { $params = array(); }

        $prompt       = isset( $params['prompt'] ) ? sanitize_text_field( (string) $params['prompt'] ) : '';
        $kb           = ! empty( $params['kb'] );
        $model        = isset( $params['model'] ) ? sanitize_text_field( (string) $params['model'] ) : '';
        $max_tokens   = isset( $params['max_tokens'] ) ? intval( $params['max_tokens'] ) : null;
        $temperature  = isset( $params['temperature'] ) ? floatval( $params['temperature'] ) : null;
        $image_data   = isset( $params['image_data_url'] ) ? (string) $params['image_data_url'] : '';
        $channel      = isset( $params['channel'] ) ? sanitize_text_field( (string) $params['channel'] ) : 'shortcode';

        // Basic validations (Lite-safe; tighten as needed)
        if ( $image_data && 0 !== strpos( $image_data, 'data:image/' ) ) {
            return $this->error( 'adam_lite_bad_image', __( 'Invalid image payload.', ADAML_TEXT_DOMAIN ), 400 );
        }
        if ( strlen( $prompt ) > 10000 ) {
            return $this->error( 'adam_lite_prompt_too_long', __( 'Prompt too long.', ADAML_TEXT_DOMAIN ), 413 );
        }

        // ---- DUMMY RESPONSE (for wiring) ----------------------------------
        // Replace this block later: call your provider (OpenAI) and optionally KB.
        $answer = "Hello from Ask Adam Lite! You said: " . ( $prompt !== '' ? $prompt : '(no prompt)' );
        if ( $kb ) {
            $answer .= " • (KB is enabled)";
        }
        if ( $image_data ) {
            $answer .= " • (image received)";
        }

        return $this->ok( array(
            'answer'      => $answer,
            'model'       => $model ?: 'gpt-4o-mini',
            'max_tokens'  => $max_tokens,
            'temperature' => $temperature,
            'channel'     => $channel,
        ) );
    }

    // ---------------------- Helpers ----------------------

    private function ok( $data, $status = 200 ) {
        return new WP_REST_Response(
            array( 'success' => true, 'data' => $data ),
            $status
        );
    }

    private function error( $code, $message, $status = 400, $extra = array() ) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'data'    => array_merge(
                    array(
                        'error_code' => $code,
                        'error'      => $message,
                    ),
                    $extra
                ),
            ),
            $status
        );
    }
}

}
