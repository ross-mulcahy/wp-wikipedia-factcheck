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
	 * Check whether the WordPress AI Client is available for text generation.
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return false;
		}

		try {
			return wp_ai_client_prompt( 'Readiness check.' )->is_supported_for_text_generation();
		} catch ( Throwable $exception ) {
			return false;
		}
	}

	/**
	 * Analyze selected editor text against a Wikipedia article summary.
	 *
	 * @param string $selected_text Selected editor text.
	 * @param array  $article       Article data from the Wikimedia client.
	 * @return array|WP_Error
	 */
	public static function analyze_claim( string $selected_text, array $article ): array|WP_Error {
		if ( ! self::is_available() ) {
			return new WP_Error(
				'ai_unavailable',
				__( 'The WordPress AI Client is not available on this site.', 'wp-wikipedia-factcheck' ),
				array( 'status' => 501 )
			);
		}

		$cache_key = 'wikimedia_fc_ai_analysis_' . md5( wp_json_encode( array( $selected_text, $article['name'] ?? '', $article['abstract'] ?? '' ) ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
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

		$json = wp_ai_client_prompt( $prompt )
			->using_system_instruction( 'You are a careful fact-checking assistant for WordPress editors. Be conservative, cite only the provided article summary, and do not infer unsupported details.' )
			->using_temperature( 0.1 )
			->using_model_preference( 'gpt-5.4', 'claude-sonnet-4-6', 'gemini-3.1-pro-preview' )
			->as_json_response( $schema )
			->generate_text();

		if ( is_wp_error( $json ) ) {
			return $json;
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'ai_invalid_response',
				__( 'The AI Client returned an invalid response.', 'wp-wikipedia-factcheck' ),
				array( 'status' => 502 )
			);
		}

		set_transient( $cache_key, $data, 12 * HOUR_IN_SECONDS );

		return $data;
	}

	/**
	 * Generate interesting fact candidates from a Wikipedia article summary.
	 *
	 * @param array $article Article data from the Wikimedia client.
	 * @return array|WP_Error
	 */
	public static function generate_interesting_facts( array $article ): array|WP_Error {
		if ( ! self::is_available() ) {
			return new WP_Error(
				'ai_unavailable',
				__( 'The WordPress AI Client is not available on this site.', 'wp-wikipedia-factcheck' ),
				array( 'status' => 501 )
			);
		}

		$cache_key = 'wikimedia_fc_ai_facts_' . md5( wp_json_encode( array( $article['name'] ?? '', $article['abstract'] ?? '' ) ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
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

		$json = wp_ai_client_prompt( $prompt )
			->using_system_instruction( 'You write concise factual callouts for editors. Use only the provided Wikipedia material, avoid speculation, and prefer specific concrete facts over generic summaries.' )
			->using_temperature( 0.4 )
			->using_model_preference( 'gpt-5.4', 'claude-sonnet-4-6', 'gemini-3.1-pro-preview' )
			->as_json_response( $schema )
			->generate_text();

		if ( is_wp_error( $json ) ) {
			return $json;
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'ai_invalid_response',
				__( 'The AI Client returned an invalid response.', 'wp-wikipedia-factcheck' ),
				array( 'status' => 502 )
			);
		}

		set_transient( $cache_key, $data, 12 * HOUR_IN_SECONDS );

		return $data;
	}
}
