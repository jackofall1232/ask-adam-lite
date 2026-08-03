<?php
/**
 * Model configuration utility class.
 *
 * @package Ask_Adam_Lite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static utility class for model configuration and OpenAI endpoint resolution.
 */
class Ask_Adam_Lite_Model_Config {

	const DEFAULT_REASONING_MODEL  = 'gpt-5.6-luna';
	const DEFAULT_VISION_MODEL     = 'gpt-5.6-luna';
	const DEFAULT_INTENT_MODEL     = 'gpt-5.6-luna';
	const DEFAULT_EMBEDDING_MODEL  = 'text-embedding-3-small';

	const ENDPOINT_RESPONSES  = 'https://api.openai.com/v1/responses';
	const ENDPOINT_EMBEDDINGS = 'https://api.openai.com/v1/embeddings';

	/**
	 * Private constructor — prevent instantiation.
	 */
	private function __construct() {}

	/**
	 * Returns the configured reasoning model, falling back to the default.
	 *
	 * @return string
	 */
	public static function get_reasoning_model(): string {
		$model = trim( (string) get_option( 'aalite_reasoning_model', '' ) );
		return ( '' !== $model ) ? $model : self::DEFAULT_REASONING_MODEL;
	}

	/**
	 * Returns the configured vision model, falling back to the default.
	 *
	 * @return string
	 */
	public static function get_vision_model(): string {
		$model = trim( (string) get_option( 'aalite_vision_model', '' ) );
		return ( '' !== $model ) ? $model : self::DEFAULT_VISION_MODEL;
	}

	/**
	 * Returns the configured intent model, falling back to the default.
	 *
	 * @return string
	 */
	public static function get_intent_model(): string {
		$model = trim( (string) get_option( 'aalite_intent_model', '' ) );
		return ( '' !== $model ) ? $model : self::DEFAULT_INTENT_MODEL;
	}

	/**
	 * Returns the configured embedding model, falling back to the default.
	 *
	 * @return string
	 */
	public static function get_embedding_model(): string {
		$model = trim( (string) get_option( 'aalite_embedding_model', '' ) );
		return ( '' !== $model ) ? $model : self::DEFAULT_EMBEDDING_MODEL;
	}

	/**
	 * OpenAI requests use the Responses API.
	 *
	 * @return bool
	 */
	public static function use_responses_api(): bool {
		return true;
	}

	/**
	 * Returns an OpenAI endpoint managed by this configuration class.
	 *
	 * @param string $resource Supported values are "responses" and "embeddings".
	 * @return string
	 */
	public static function get_openai_endpoint( string $resource = 'responses' ): string {
		return 'embeddings' === $resource ? self::ENDPOINT_EMBEDDINGS : self::ENDPOINT_RESPONSES;
	}

	/**
	 * Extracts the text content from an OpenAI API response body.
	 *
	 * Supports Responses API output and legacy Chat Completions output as a
	 * parser fallback for response bodies produced before this refactor.
	 *
	 * @param array $body Decoded JSON response from OpenAI.
	 * @return string Trimmed output text, or empty string if not found.
	 */
	public static function normalize_openai_output( array $body ): string {
		// 1. Responses API shorthand.
		if ( isset( $body['output_text'] ) && is_string( $body['output_text'] ) ) {
			$trimmed = trim( $body['output_text'] );
			if ( '' !== $trimmed ) {
				return sanitize_textarea_field( $trimmed );
			}
		}

		// 2. Responses API full structure.
		if ( isset( $body['output'] ) && is_array( $body['output'] ) ) {
			foreach ( $body['output'] as $output_item ) {
				if ( ! is_array( $output_item ) ) {
					continue;
				}
				if ( ! isset( $output_item['content'] ) || ! is_array( $output_item['content'] ) ) {
					continue;
				}
				foreach ( $output_item['content'] as $content_block ) {
					if ( ! is_array( $content_block ) ) {
						continue;
					}
					if ( isset( $content_block['text'] ) && is_string( $content_block['text'] ) ) {
						$trimmed = trim( $content_block['text'] );
						if ( '' !== $trimmed ) {
							return sanitize_textarea_field( $trimmed );
						}
					}
				}
			}
		}

		// 3. Chat Completions legacy format.
		if (
			isset( $body['choices'][0]['message']['content'] ) &&
			is_string( $body['choices'][0]['message']['content'] )
		) {
			return sanitize_textarea_field( trim( $body['choices'][0]['message']['content'] ) );
		}

		return '';
	}

	/**
	 * Returns true if the active embedding model differs from the indexed one.
	 *
	 * @return bool
	 */
	public static function detect_embedding_mismatch(): bool {
		$active  = self::get_embedding_model();
		$indexed = get_option( 'aalite_kb_indexed_embedding_model', '' );

		if ( ! is_string( $indexed ) || '' === $indexed ) {
			return false;
		}

		return $active !== $indexed;
	}

	/**
	 * Returns the embedding model used during the last index build.
	 *
	 * @return string Empty string if not set.
	 */
	public static function get_indexed_embedding_model(): string {
		$model = get_option( 'aalite_kb_indexed_embedding_model', '' );
		return is_string( $model ) ? $model : '';
	}

	/**
	 * Persists the embedding model used during an index build.
	 *
	 * @param string $model Model identifier string.
	 * @return void
	 */
	public static function set_indexed_embedding_model( string $model ): void {
		update_option( 'aalite_kb_indexed_embedding_model', sanitize_text_field( $model ) );
	}
}
