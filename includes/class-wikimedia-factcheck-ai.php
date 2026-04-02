<?php
/**
 * AI helpers for Wikipedia fact-check features.
 *
 * @package WP_Wikipedia_Factcheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wikimedia_Factcheck_AI {

	/**
	 * Write a debug line for AI fact-check operations.
	 *
	 * @param string $event   Event label.
	 * @param array  $context Extra context.
	 * @return void
	 */
	private static function log_debug( string $event, array $context = array() ): void {
		$payload = array_merge(
			array(
				'event' => $event,
			),
			$context
		);

		error_log( 'WP Wikipedia Fact-Check AI: ' . wp_json_encode( $payload ) );
	}

	/**
	 * Get a prompt builder for either Core AI Client or the AI Experiments plugin.
	 *
	 * @param string $prompt Prompt text.
	 * @return mixed
	 */
	private static function get_prompt_builder( string $prompt ) {
		self::log_debug(
			'get_prompt_builder:start',
			array(
				'has_core_function'  => function_exists( 'wp_ai_client_prompt' ),
				'has_plugin_class'   => class_exists( '\WordPress\AI_Client\AI_Client' ),
				'prompt_length'      => strlen( $prompt ),
			)
		);

		if ( function_exists( 'wp_ai_client_prompt' ) ) {
			try {
				self::log_debug( 'get_prompt_builder:using_core_function' );
				return wp_ai_client_prompt( $prompt );
			} catch ( Throwable $exception ) {
				self::log_debug(
					'get_prompt_builder:core_function_failed',
					array(
						'message' => $exception->getMessage(),
					)
				);
				return new WP_Error( 'ai_prompt_init_failed', $exception->getMessage() );
			}
		}

		if ( class_exists( '\WordPress\AI_Client\AI_Client' ) ) {
			try {
				self::log_debug( 'get_prompt_builder:using_plugin_class' );
				return \WordPress\AI_Client\AI_Client::prompt_with_wp_error( $prompt );
			} catch ( Throwable $exception ) {
				self::log_debug(
					'get_prompt_builder:plugin_class_failed',
					array(
						'message' => $exception->getMessage(),
					)
				);
				return new WP_Error( 'ai_prompt_init_failed', $exception->getMessage() );
			}
		}

		self::log_debug( 'get_prompt_builder:unavailable' );

		return new WP_Error(
			'ai_unavailable',
			__( 'The WordPress AI Client is not available on this site.', 'wp-wikipedia-factcheck' ),
			array( 'status' => 501 )
		);
	}

	/**
	 * Check whether the WordPress AI Client is available for text generation.
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		$builder = self::get_prompt_builder( 'Readiness check.' );
		if ( is_wp_error( $builder ) || ! method_exists( $builder, 'is_supported_for_text_generation' ) ) {
			self::log_debug(
				'is_available:builder_unavailable',
				array(
					'is_wp_error'         => is_wp_error( $builder ),
					'has_support_method'  => is_object( $builder ) ? method_exists( $builder, 'is_supported_for_text_generation' ) : false,
				)
			);
			return false;
		}

		try {
			$is_supported = (bool) $builder->is_supported_for_text_generation();
			self::log_debug(
				'is_available:supported_check',
				array(
					'is_supported' => $is_supported,
				)
			);
			return $is_supported;
		} catch ( Throwable $exception ) {
			self::log_debug(
				'is_available:exception',
				array(
					'message' => $exception->getMessage(),
				)
			);
			return false;
		}
	}

	/**
	 * Run a lightweight health check against the AI client integration.
	 *
	 * @return array<string,mixed>
	 */
	public static function health_check(): array {
		$builder = self::get_prompt_builder( 'Health check.' );

		$result = array(
			'has_core_function' => function_exists( 'wp_ai_client_prompt' ),
			'has_plugin_class'  => class_exists( '\WordPress\AI_Client\AI_Client' ),
			'builder_type'      => is_object( $builder ) ? get_class( $builder ) : null,
			'is_wp_error'       => is_wp_error( $builder ),
			'error_code'        => is_wp_error( $builder ) ? $builder->get_error_code() : null,
			'error_message'     => is_wp_error( $builder ) ? $builder->get_error_message() : null,
			'methods'           => is_object( $builder ) ? get_class_methods( $builder ) : array(),
		);

		if ( is_object( $builder ) && method_exists( $builder, 'is_supported_for_text_generation' ) ) {
			try {
				$result['supports_text_generation'] = (bool) $builder->is_supported_for_text_generation();
			} catch ( Throwable $exception ) {
				$result['supports_text_generation'] = false;
				$result['support_check_error']      = $exception->getMessage();
			}
		}

		self::log_debug( 'health_check', $result );

		return $result;
	}

	/**
	 * Analyze selected editor text against a Wikipedia article summary.
	 *
	 * @param string $selected_text Selected editor text.
	 * @param array  $article       Article data from the Wikimedia client.
	 * @return array|WP_Error
	 */
	public static function analyze_claim( string $selected_text, array $article ): array|WP_Error {
		self::log_debug(
			'analyze_claim:start',
			array(
				'selected_text_length' => strlen( $selected_text ),
				'article_name'         => $article['name'] ?? '',
			)
		);

		$cache_key = 'wikimedia_fc_ai_analysis_' . md5( wp_json_encode( array( $selected_text, $article['name'] ?? '', $article['abstract'] ?? '' ) ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			self::log_debug( 'analyze_claim:cache_hit' );
			return $cached;
		}

		$schema = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'verdict'    => array( 'type' => 'string' ),
				'summary'    => array( 'type' => 'string' ),
				'mismatches' => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array(
							'type'        => array( 'type' => 'string' ),
							'claim'       => array( 'type' => 'string' ),
							'article'     => array( 'type' => 'string' ),
							'explanation' => array( 'type' => 'string' ),
						),
						'required'             => array( 'type', 'claim', 'article', 'explanation' ),
					),
				),
			),
			'required'             => array( 'verdict', 'summary', 'mismatches' ),
		);

		$prompt = sprintf(
			"Selected draft text:\n%s\n\nWikipedia article:\nTitle: %s\nSummary: %s\nLast edited: %s\n\nCompare only the selected draft text against the Wikipedia material provided. Identify likely mismatches in dates, numbers, names, or core claims. If something cannot be verified from the article summary, say that rather than guessing. Return concise structured JSON only.",
			$selected_text,
			$article['name'] ?? '',
			$article['abstract'] ?? '',
			$article['date_modified'] ?? ''
		);

		$builder = self::get_prompt_builder( $prompt );
		if ( is_wp_error( $builder ) ) {
			self::log_debug(
				'analyze_claim:builder_error',
				array(
					'code'    => $builder->get_error_code(),
					'message' => $builder->get_error_message(),
				)
			);
			return $builder;
		}

		if ( method_exists( $builder, 'is_supported_for_text_generation' ) && ! $builder->is_supported_for_text_generation() ) {
			self::log_debug( 'analyze_claim:unsupported_generation' );
			return new WP_Error(
				'ai_unsupported',
				__( 'AI text generation is not currently supported by the configured providers on this site.', 'wp-wikipedia-factcheck' ),
				array( 'status' => 501 )
			);
		}

		$builder = $builder
			->using_system_instruction( 'You are a careful fact-checking assistant for WordPress editors. Be conservative, cite only the provided article summary, and do not infer unsupported details.' )
			->using_temperature( 0.1 )
			->as_json_response( $schema );
		self::log_debug( 'analyze_claim:configured_builder' );

		if ( function_exists( 'wp_ai_client_prompt' ) && method_exists( $builder, 'using_model_preference' ) ) {
			$builder = $builder->using_model_preference( 'gpt-5.4', 'claude-sonnet-4-6', 'gemini-3.1-pro-preview' );
			self::log_debug( 'analyze_claim:applied_model_preference' );
		}

		$json = $builder->generate_text();
		self::log_debug(
			'analyze_claim:generate_text_complete',
			array(
				'is_wp_error' => is_wp_error( $json ),
			)
		);

		if ( is_wp_error( $json ) ) {
			self::log_debug(
				'analyze_claim:generate_text_error',
				array(
					'code'    => $json->get_error_code(),
					'message' => $json->get_error_message(),
				)
			);
			return $json;
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			self::log_debug( 'analyze_claim:invalid_json_response' );
			return new WP_Error(
				'ai_invalid_response',
				__( 'The AI Client returned an invalid response.', 'wp-wikipedia-factcheck' ),
				array( 'status' => 502 )
			);
		}

		set_transient( $cache_key, $data, 12 * HOUR_IN_SECONDS );
		self::log_debug( 'analyze_claim:success' );

		return $data;
	}

	/**
	 * Generate interesting fact candidates from a Wikipedia article summary.
	 *
	 * @param array $article Article data from the Wikimedia client.
	 * @return array|WP_Error
	 */
	public static function generate_interesting_facts( array $article ): array|WP_Error {
		self::log_debug(
			'generate_interesting_facts:start',
			array(
				'article_name' => $article['name'] ?? '',
			)
		);

		$cache_key = 'wikimedia_fc_ai_facts_' . md5( wp_json_encode( array( $article['name'] ?? '', $article['abstract'] ?? '' ) ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			self::log_debug( 'generate_interesting_facts:cache_hit' );
			return $cached;
		}

		$schema = array(
			'type'  => 'array',
			'items' => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'fact'           => array( 'type' => 'string' ),
					'headline'       => array( 'type' => 'string' ),
					'why_interesting'=> array( 'type' => 'string' ),
				),
				'required'             => array( 'fact', 'headline', 'why_interesting' ),
			),
		);

		$prompt = sprintf(
			"Article title: %s\nSummary: %s\nCategories: %s\n\nExtract 3 short, interesting, editor-friendly fact candidates from the article summary only. Facts must stay faithful to the provided text and should work inside a compact sidebar or fact box. Return JSON only.",
			$article['name'] ?? '',
			$article['abstract'] ?? '',
			implode( ', ', $article['categories'] ?? array() )
		);

		$builder = self::get_prompt_builder( $prompt );
		if ( is_wp_error( $builder ) ) {
			self::log_debug(
				'generate_interesting_facts:builder_error',
				array(
					'code'    => $builder->get_error_code(),
					'message' => $builder->get_error_message(),
				)
			);
			return $builder;
		}

		if ( method_exists( $builder, 'is_supported_for_text_generation' ) && ! $builder->is_supported_for_text_generation() ) {
			self::log_debug( 'generate_interesting_facts:unsupported_generation' );
			return new WP_Error(
				'ai_unsupported',
				__( 'AI text generation is not currently supported by the configured providers on this site.', 'wp-wikipedia-factcheck' ),
				array( 'status' => 501 )
			);
		}

		$builder = $builder
			->using_system_instruction( 'You write concise factual callouts for editors. Use only the provided Wikipedia material, avoid speculation, and prefer specific concrete facts over generic summaries.' )
			->using_temperature( 0.4 )
			->as_json_response( $schema );
		self::log_debug( 'generate_interesting_facts:configured_builder' );

		if ( function_exists( 'wp_ai_client_prompt' ) && method_exists( $builder, 'using_model_preference' ) ) {
			$builder = $builder->using_model_preference( 'gpt-5.4', 'claude-sonnet-4-6', 'gemini-3.1-pro-preview' );
			self::log_debug( 'generate_interesting_facts:applied_model_preference' );
		}

		$json = $builder->generate_text();
		self::log_debug(
			'generate_interesting_facts:generate_text_complete',
			array(
				'is_wp_error' => is_wp_error( $json ),
			)
		);

		if ( is_wp_error( $json ) ) {
			self::log_debug(
				'generate_interesting_facts:generate_text_error',
				array(
					'code'    => $json->get_error_code(),
					'message' => $json->get_error_message(),
				)
			);
			return $json;
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			self::log_debug( 'generate_interesting_facts:invalid_json_response' );
			return new WP_Error(
				'ai_invalid_response',
				__( 'The AI Client returned an invalid response.', 'wp-wikipedia-factcheck' ),
				array( 'status' => 502 )
			);
		}

		set_transient( $cache_key, $data, 12 * HOUR_IN_SECONDS );
		self::log_debug( 'generate_interesting_facts:success' );

		return $data;
	}
}
