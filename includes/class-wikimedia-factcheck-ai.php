<?php
/**
 * AI helpers for Wikipedia fact-check features.
 *
 * @package WP_Wikipedia_Factcheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Wikipedia_Factcheck_AI {

	/**
	 * Ordered provider/model preferences for text generation fallback.
	 *
	 * @return array<int, array{0:string,1:string}>
	 */
	private static function get_text_model_preferences(): array {
		return array(
			array( 'openai', 'gpt-5.1' ),
			array( 'google', 'gemini-3-pro-preview' ),
			array( 'anthropic', 'claude-sonnet-4-5' ),
		);
	}

	/**
	 * Write a debug line for AI fact-check operations.
	 *
	 * @param string $event   Event label.
	 * @param array  $context Extra context.
	 * @return void
	 */
	private static function log_debug( string $event, array $context = array() ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$payload = array_merge(
			array(
				'event' => $event,
			),
			$context
		);

		error_log( 'WP Wikipedia Fact-Check AI: ' . wp_json_encode( $payload ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Configure a prompt builder with shared settings.
	 *
	 * @param object      $builder Prompt builder.
	 * @param string      $system_instruction System instruction.
	 * @param float       $temperature Temperature.
	 * @param array|null  $schema Optional JSON schema.
	 * @param array|null  $single_preference Optional single provider/model pair.
	 * @return object
	 */
	private static function configure_builder(
		object $builder,
		string $system_instruction,
		float $temperature,
		?array $schema = null,
		?array $single_preference = null
	): object {
		$builder = $builder
			->using_system_instruction( $system_instruction )
			->using_temperature( $temperature );

		if ( null !== $single_preference && method_exists( $builder, 'using_provider' ) ) {
			$builder = $builder->using_provider( $single_preference[0] );
		}

		if ( null !== $schema ) {
			$builder = $builder->as_json_response( $schema );
		}

		if ( method_exists( $builder, 'using_model_preference' ) ) {
			if ( null !== $single_preference ) {
				$builder = $builder->using_model_preference( $single_preference );
			} else {
				$builder = $builder->using_model_preference( ...self::get_text_model_preferences() );
			}
		}

		return $builder;
	}

	/**
	 * Wrap a provider-specific AI error in a user-facing response shape.
	 *
	 * @param WP_Error $error      Original provider error.
	 * @param string   $provider   Provider slug.
	 * @param string   $model      Model identifier.
	 * @param bool     $final_try  Whether this was the last provider attempt.
	 * @return WP_Error
	 */
	private static function wrap_provider_error( WP_Error $error, string $provider, string $model, bool $final_try = false ): WP_Error {
		$message = sprintf(
			/* translators: 1: provider name, 2: model name, 3: provider error */
			__( '%1$s (%2$s) failed: %3$s', 'wp-wikipedia-factcheck' ),
			ucfirst( $provider ),
			$model,
			$error->get_error_message()
		);

		if ( $final_try ) {
			$message = sprintf(
				/* translators: %s: provider failure summary */
				__( 'All configured AI providers failed. Last error: %s', 'wp-wikipedia-factcheck' ),
				$message
			);
		}

		$data = $error->get_error_data();
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$data['status']   = 400;
		$data['provider'] = $provider;
		$data['model']    = $model;

		return new WP_Error( $error->get_error_code(), $message, $data );
	}

	/**
	 * Determine whether an error looks provider-specific and worth retrying elsewhere.
	 *
	 * @param WP_Error $error Error to inspect.
	 * @return bool
	 */
	private static function should_retry_provider_error( WP_Error $error ): bool {
		$message = strtolower( $error->get_error_message() );

		return str_contains( $message, 'credit balance' )
			|| str_contains( $message, 'quota' )
			|| str_contains( $message, 'rate limit' )
			|| str_contains( $message, 'rate-limit' )
			|| str_contains( $message, 'billing' )
			|| str_contains( $message, 'insufficient' )
			|| str_contains( $message, 'provider' );
	}

	/**
	 * Generate text with provider fallback.
	 *
	 * @param string     $prompt Prompt text.
	 * @param string     $system_instruction System instruction.
	 * @param float      $temperature Temperature.
	 * @param array|null $schema Optional JSON schema.
	 * @param string     $context Context label for logging.
	 * @return string|WP_Error
	 */
	private static function generate_text_with_fallback(
		string $prompt,
		string $system_instruction,
		float $temperature,
		?array $schema,
		string $context
	): string|WP_Error {
		$preferences = self::get_text_model_preferences();
		$last_error  = null;

		$total_preferences = count( $preferences );

		foreach ( $preferences as $index => $preference ) {
			$builder = self::get_prompt_builder( $prompt );
			if ( is_wp_error( $builder ) ) {
				self::log_debug(
					$context . ':builder_error',
					array(
						'code'       => $builder->get_error_code(),
						'message'    => $builder->get_error_message(),
						'provider'   => $preference[0],
						'model'      => $preference[1],
					)
				);
				return $builder;
			}

			if ( method_exists( $builder, 'is_supported_for_text_generation' ) && ! $builder->is_supported_for_text_generation() ) {
				self::log_debug(
					$context . ':unsupported_generation',
					array(
						'provider' => $preference[0],
						'model'    => $preference[1],
					)
				);
				$last_error = new WP_Error(
					'ai_unsupported',
					__( 'AI text generation is not currently supported by the configured providers on this site.', 'wp-wikipedia-factcheck' ),
					array( 'status' => 501 )
				);
				continue;
			}

			$builder = self::configure_builder( $builder, $system_instruction, $temperature, $schema, $preference );
			self::log_debug(
				$context . ':configured_builder',
				array(
					'provider' => $preference[0],
					'model'    => $preference[1],
				)
			);

			$result = $builder->generate_text();

			self::log_debug(
				$context . ':generate_text_complete',
				array(
					'is_wp_error' => is_wp_error( $result ),
					'provider'    => $preference[0],
					'model'       => $preference[1],
				)
			);

			if ( is_wp_error( $result ) ) {
				self::log_debug(
					$context . ':generate_text_error',
					array(
						'code'     => $result->get_error_code(),
						'message'  => $result->get_error_message(),
						'provider' => $preference[0],
						'model'    => $preference[1],
					)
				);
				$last_error = self::wrap_provider_error(
					$result,
					$preference[0],
					$preference[1],
					$index === ( $total_preferences - 1 )
				);

				if ( self::should_retry_provider_error( $result ) ) {
					continue;
				}

				return $last_error;
			}

			return $result;
		}

		return $last_error instanceof WP_Error
			? $last_error
			: new WP_Error(
				'ai_generation_failed',
				__( 'All configured AI providers failed to generate a response.', 'wp-wikipedia-factcheck' ),
				array( 'status' => 400 )
			);
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
			'text_model_preferences' => self::get_text_model_preferences(),
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

		$cache_key = 'wpwfc_analysis_' . md5( wp_json_encode( array( $selected_text, $article['name'] ?? '', $article['abstract'] ?? '' ) ) );
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

		$json = self::generate_text_with_fallback(
			$prompt,
			'You are a careful fact-checking assistant for WordPress editors. Be conservative, cite only the provided article summary, and do not infer unsupported details.',
			0.1,
			$schema,
			'analyze_claim'
		);

		if ( is_wp_error( $json ) ) {
			return $json;
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			self::log_debug( 'analyze_claim:invalid_json_response' );
			return new WP_Error(
				'ai_invalid_response',
				__( 'The AI Client returned an invalid response.', 'wp-wikipedia-factcheck' ),
				array( 'status' => 422 )
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

		$cache_key = 'wpwfc_facts_' . md5( wp_json_encode( array( $article['name'] ?? '', $article['abstract'] ?? '' ) ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			self::log_debug( 'generate_interesting_facts:cache_hit' );
			return $cached;
		}

		$schema = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'facts' => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array(
							'fact'            => array( 'type' => 'string' ),
							'headline'        => array( 'type' => 'string' ),
							'why_interesting' => array( 'type' => 'string' ),
						),
						'required'             => array( 'fact', 'headline', 'why_interesting' ),
					),
				),
			),
			'required'             => array( 'facts' ),
		);

		$prompt = sprintf(
			"Article title: %s\nSummary: %s\nCategories: %s\n\nExtract 3 short, interesting, editor-friendly fact candidates from the article summary only. Facts must stay faithful to the provided text and should work inside a compact sidebar or fact box. Return JSON only.",
			$article['name'] ?? '',
			$article['abstract'] ?? '',
			implode( ', ', $article['categories'] ?? array() )
		);

		$json = self::generate_text_with_fallback(
			$prompt,
			'You write concise factual callouts for editors. Use only the provided Wikipedia material, avoid speculation, and prefer specific concrete facts over generic summaries.',
			0.4,
			$schema,
			'generate_interesting_facts'
		);

		if ( is_wp_error( $json ) ) {
			return $json;
		}

		$data = json_decode( $json, true );
		$facts = is_array( $data ) && isset( $data['facts'] ) && is_array( $data['facts'] )
			? $data['facts']
			: null;

		if ( ! is_array( $facts ) ) {
			self::log_debug( 'generate_interesting_facts:invalid_json_response' );
			return new WP_Error(
				'ai_invalid_response',
				__( 'The AI Client returned an invalid response.', 'wp-wikipedia-factcheck' ),
				array( 'status' => 422 )
			);
		}

		set_transient( $cache_key, $facts, 12 * HOUR_IN_SECONDS );
		self::log_debug( 'generate_interesting_facts:success' );

		return $facts;
	}

	/**
	 * Suggest Wikipedia search terms from draft content.
	 *
	 * @param string $content Draft content.
	 * @return array|WP_Error
	 */
	public static function suggest_topics_from_content( string $content ): array|WP_Error {
		self::log_debug(
			'suggest_topics:start',
			array(
				'content_length' => strlen( $content ),
			)
		);

		$content = trim( $content );

		if ( '' === $content ) {
			return new WP_Error(
				'ai_empty_content',
				__( 'Add some draft content before asking for Wikipedia topic suggestions.', 'wp-wikipedia-factcheck' ),
				array( 'status' => 400 )
			);
		}

		$cache_key = 'wpwfc_topics_' . md5( $content );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			self::log_debug( 'suggest_topics:cache_hit' );
			return $cached;
		}

		$schema = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'suggestions' => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array(
							'term' => array( 'type' => 'string' ),
							'why'  => array( 'type' => 'string' ),
						),
						'required'             => array( 'term', 'why' ),
					),
				),
			),
			'required'             => array( 'suggestions' ),
		);

		$prompt = sprintf(
			"Draft article content:\n%s\n\nSuggest up to 5 specific Wikipedia search terms a writer should check. Prefer named people, places, organisations, events, works, or concepts that are central to the draft and likely to have useful Wikipedia pages. Return JSON only.",
			$content
		);

		$json = self::generate_text_with_fallback(
			$prompt,
			'You help journalists and editors identify the best Wikipedia lookup targets from a draft article. Suggest only terms that are likely to produce useful factual context, and keep explanations brief.',
			0.2,
			$schema,
			'suggest_topics'
		);

		if ( is_wp_error( $json ) ) {
			return $json;
		}

		$data = json_decode( $json, true );
		$suggestions = is_array( $data ) && isset( $data['suggestions'] ) && is_array( $data['suggestions'] )
			? $data['suggestions']
			: null;

		if ( ! is_array( $suggestions ) ) {
			return new WP_Error(
				'ai_invalid_response',
				__( 'The AI Client returned invalid topic suggestions.', 'wp-wikipedia-factcheck' ),
				array( 'status' => 422 )
			);
		}

		$suggestions = array_values(
			array_filter(
				array_map(
					static function ( $item ) {
						if ( ! is_array( $item ) ) {
							return null;
						}

						$term = trim( (string) ( $item['term'] ?? '' ) );
						$why  = trim( (string) ( $item['why'] ?? '' ) );

						if ( '' === $term ) {
							return null;
						}

						return array(
							'term' => $term,
							'why'  => $why,
						);
					},
					$suggestions
				)
			)
		);

		set_transient( $cache_key, $suggestions, 6 * HOUR_IN_SECONDS );
		self::log_debug(
			'suggest_topics:success',
			array(
				'count' => count( $suggestions ),
			)
		);

		return $suggestions;
	}

	/**
	 * Build an editor briefing from a matched Wikipedia article.
	 *
	 * @param array  $article Article data from the Wikimedia client.
	 * @param string $content Draft content.
	 * @return array|WP_Error
	 */
	public static function generate_article_briefing( array $article, string $content = '' ): array|WP_Error {
		self::log_debug(
			'generate_briefing:start',
			array(
				'article_name'   => $article['name'] ?? '',
				'content_length' => strlen( $content ),
			)
		);

		$cache_key = 'wpwfc_briefing_' . md5(
			wp_json_encode(
				array(
					$article['name'] ?? '',
					$article['abstract'] ?? '',
					$article['date_modified'] ?? '',
					$content,
				)
			)
		);
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			self::log_debug( 'generate_briefing:cache_hit' );
			return $cached;
		}

		$schema = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'headline'     => array( 'type' => 'string' ),
				'why_relevant' => array( 'type' => 'string' ),
				'key_facts'    => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'angles'       => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'cautions'     => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
			),
			'required'             => array( 'headline', 'why_relevant', 'key_facts', 'angles', 'cautions' ),
		);

		$prompt = sprintf(
			"Draft article content:\n%s\n\nWikipedia article:\nTitle: %s\nSummary: %s\nLast edited: %s\nCategories: %s\n\nCreate a concise editorial briefing based only on the provided Wikipedia material. Explain why the topic matters to this draft, list concrete facts a writer could verify or use for context, suggest a few reporting angles, and include any cautions or limitations from the article material. Return JSON only.",
			$content,
			$article['name'] ?? '',
			$article['abstract'] ?? '',
			$article['date_modified'] ?? '',
			implode( ', ', $article['categories'] ?? array() )
		);

		$json = self::generate_text_with_fallback(
			$prompt,
			'You create compact editorial briefings for writers using only the supplied Wikipedia article data. Be concrete, cautious, and useful. Do not invent facts or imply verification beyond the provided material.',
			0.25,
			$schema,
			'generate_briefing'
		);

		if ( is_wp_error( $json ) ) {
			return $json;
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'ai_invalid_response',
				__( 'The AI Client returned an invalid article briefing.', 'wp-wikipedia-factcheck' ),
				array( 'status' => 422 )
			);
		}

		$data['key_facts'] = array_values( array_filter( array_map( 'strval', $data['key_facts'] ?? array() ) ) );
		$data['angles']    = array_values( array_filter( array_map( 'strval', $data['angles'] ?? array() ) ) );
		$data['cautions']  = array_values( array_filter( array_map( 'strval', $data['cautions'] ?? array() ) ) );

		set_transient( $cache_key, $data, 12 * HOUR_IN_SECONDS );
		self::log_debug( 'generate_briefing:success' );

		return $data;
	}
}
