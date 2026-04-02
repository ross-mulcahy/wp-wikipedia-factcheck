<?php
/**
 * Wikimedia Enterprise API client.
 *
 * Handles authentication (JWT token management) and article lookups
 * via the Wikimedia Enterprise On-demand API.
 *
 * @package WP_Wikipedia_Factcheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wikimedia_API {

	private const AUTH_URL    = 'https://auth.enterprise.wikimedia.com/v1/login';
	private const API_URL     = 'https://api.enterprise.wikimedia.com/v2/articles/';
	private const SEARCH_URL  = 'https://%s.wikipedia.org/w/api.php';
	private const TOKEN_KEY   = 'wikimedia_enterprise_access_token';
	private const TOKEN_TTL   = 82800; // 23 hours in seconds.

	/**
	 * Get a valid access token, refreshing if needed.
	 *
	 * @param bool $force_refresh Force a new token even if cached.
	 * @return string|WP_Error Access token or error.
	 */
	public function get_access_token( bool $force_refresh = false ): string|WP_Error {
		if ( ! $force_refresh ) {
			$cached = get_transient( self::TOKEN_KEY );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$username = strtolower( trim( (string) get_option( 'wikimedia_enterprise_username' ) ) );
		$password = (string) get_option( 'wikimedia_enterprise_password' );

		if ( empty( $username ) || empty( $password ) ) {
			return new WP_Error(
				'no_credentials',
				__( 'Wikimedia Enterprise credentials are not configured.', 'wp-wikipedia-factcheck' )
			);
		}

		$response = wp_remote_post(
			self::AUTH_URL,
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'username' => $username,
						'password' => $password,
					)
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'auth_request_failed',
				__( 'Could not connect to Wikimedia authentication service.', 'wp-wikipedia-factcheck' )
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || empty( $body['access_token'] ) ) {
			return new WP_Error(
				'auth_failed',
				__( 'Authentication failed. Check your Wikimedia Enterprise credentials.', 'wp-wikipedia-factcheck' )
			);
		}

		set_transient( self::TOKEN_KEY, $body['access_token'], self::TOKEN_TTL );

		return $body['access_token'];
	}

	/**
	 * Look up an article by term.
	 *
	 * @param string $term    Search term.
	 * @param string $language Language code (e.g. 'en').
	 * @return array|WP_Error Mapped response data or error.
	 */
	public function lookup( string $term, string $language = 'en' ): array|WP_Error {
		$token = $this->get_access_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$attempted_terms = array();
		foreach ( $this->build_lookup_candidates( $term ) as $candidate ) {
			$attempted_terms[ $candidate ] = true;
			$result = $this->make_lookup_request( $candidate, $language, $token );

			// On auth failure, try refreshing the token once.
			if ( is_wp_error( $result ) && 'auth_expired' === $result->get_error_code() ) {
				$token = $this->get_access_token( true );
				if ( is_wp_error( $token ) ) {
					return $token;
				}
				$result = $this->make_lookup_request( $candidate, $language, $token );
			}

			if ( ! is_wp_error( $result ) ) {
				return $result;
			}

			if ( ! in_array( $result->get_error_code(), array( 'not_found', 'invalid_title' ), true ) ) {
				return $result;
			}
		}

		$resolved_term = $this->resolve_article_title( $term, $language );
		if ( is_wp_error( $resolved_term ) ) {
			return $resolved_term;
		}

		if ( empty( $resolved_term ) || isset( $attempted_terms[ $resolved_term ] ) ) {
			return new WP_Error( 'not_found', __( 'Article not found.', 'wp-wikipedia-factcheck' ) );
		}

		$result = $this->make_lookup_request( $resolved_term, $language, $token );

		if ( is_wp_error( $result ) && 'auth_expired' === $result->get_error_code() ) {
			$token = $this->get_access_token( true );
			if ( is_wp_error( $token ) ) {
				return $token;
			}
			$result = $this->make_lookup_request( $resolved_term, $language, $token );
		}

		return $result;
	}

	/**
	 * Execute the lookup HTTP request.
	 *
	 * @param string $term     Search term.
	 * @param string $language Language code.
	 * @param string $token    Access token.
	 * @return array|WP_Error Mapped response or error.
	 */
	private function make_lookup_request( string $term, string $language, string $token ): array|WP_Error {
		$wiki_id = $language . 'wiki';
		$url     = self::API_URL . rawurlencode( $term );

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'filters' => array(
							array(
								'field' => 'is_part_of.identifier',
								'value' => $wiki_id,
							),
						),
						'limit'   => 1,
					)
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'api_request_failed',
				__( 'Could not reach the Wikimedia API.', 'wp-wikipedia-factcheck' )
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $code || 403 === $code ) {
			return new WP_Error( 'auth_expired', __( 'Access token expired.', 'wp-wikipedia-factcheck' ) );
		}

		if ( 404 === $code ) {
			return new WP_Error( 'not_found', __( 'Article not found.', 'wp-wikipedia-factcheck' ) );
		}

		if ( 422 === $code ) {
			$message = is_array( $body ) && ! empty( $body['message'] ) ? $body['message'] : __( 'The article title is invalid.', 'wp-wikipedia-factcheck' );
			return new WP_Error( 'invalid_title', $message );
		}

		if ( 429 === $code ) {
			return new WP_Error( 'rate_limited', __( 'API rate limit reached. Try again shortly.', 'wp-wikipedia-factcheck' ) );
		}

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $body ) && ! empty( $body['message'] ) ? $body['message'] : __( 'Wikimedia API returned an error.', 'wp-wikipedia-factcheck' );
			return new WP_Error( 'api_error', $message );
		}

		if ( empty( $body ) || ! is_array( $body ) ) {
			return new WP_Error( 'not_found', __( 'Article not found.', 'wp-wikipedia-factcheck' ) );
		}

		// The API returns an array of articles; take the first one.
		$article = is_array( $body ) && isset( $body[0] ) ? $body[0] : $body;

		if ( empty( $article['name'] ) ) {
			return new WP_Error( 'not_found', __( 'Article not found.', 'wp-wikipedia-factcheck' ) );
		}

		return $this->map_response( $article );
	}

	/**
	 * Map raw Wikimedia API response to our filtered format.
	 *
	 * @param array $article Raw article data.
	 * @return array Mapped data.
	 */
	private function map_response( array $article ): array {
		$categories = array();
		if ( ! empty( $article['categories'] ) && is_array( $article['categories'] ) ) {
			$categories = array_slice(
				array_map(
					fn( $cat ) => $cat['name'] ?? '',
					$article['categories']
				),
				0,
				8
			);
			$categories = array_values( array_filter( $categories ) );
		}

		$image = null;
		if ( ! empty( $article['image'] ) ) {
			$image = array(
				'content_url' => $article['image']['content_url'] ?? null,
				'width'       => $article['image']['width'] ?? null,
				'height'      => $article['image']['height'] ?? null,
			);
		}

		$revert_risk = $article['version']['scores']['revertrisk']['probability']['true'] ?? null;
		if ( null !== $revert_risk ) {
			$revert_risk = (float) $revert_risk;
		}

		$license = null;
		if ( ! empty( $article['license'] ) && is_array( $article['license'] ) ) {
			$first_license = $article['license'][0] ?? null;
			if ( $first_license ) {
				$license = array(
					'name'       => $first_license['name'] ?? '',
					'identifier' => $first_license['identifier'] ?? '',
					'url'        => $first_license['url'] ?? '',
				);
			}
		}

		return array(
			'found'         => true,
			'name'          => $article['name'] ?? '',
			'url'           => $article['url'] ?? '',
			'abstract'      => $article['abstract'] ?? '',
			'date_modified' => $article['date_modified'] ?? null,
			'image'         => $image,
			'categories'    => $categories,
			'wikidata_qid'  => $article['main_entity']['identifier'] ?? null,
			'wikidata_url'  => $article['main_entity']['url'] ?? null,
			'revert_risk'   => $revert_risk,
			'license'       => $license,
		);
	}

	/**
	 * Build likely article title candidates for exact title lookups.
	 *
	 * @param string $term Search term.
	 * @return array<string>
	 */
	private function build_lookup_candidates( string $term ): array {
		$term = trim( $term );
		if ( '' === $term ) {
			return array();
		}

		$candidates = array(
			$term,
			str_replace( ' ', '_', $term ),
		);

		$first_char = function_exists( 'mb_substr' ) ? mb_substr( $term, 0, 1 ) : substr( $term, 0, 1 );
		$remainder  = function_exists( 'mb_substr' ) ? mb_substr( $term, 1 ) : substr( $term, 1 );
		if ( '' !== $first_char ) {
			$title_case_candidate = strtoupper( $first_char ) . $remainder;
			$candidates[]         = $title_case_candidate;
			$candidates[]         = str_replace( ' ', '_', $title_case_candidate );
		}

		return array_values( array_unique( array_filter( $candidates ) ) );
	}

	/**
	 * Resolve a free-form search term to a likely Wikipedia article title.
	 *
	 * @param string $term Search term.
	 * @param string $language Language code.
	 * @return string|WP_Error|null
	 */
	private function resolve_article_title( string $term, string $language ): string|WP_Error|null {
		$search_url = add_query_arg(
			array(
				'action'   => 'query',
				'list'     => 'search',
				'srsearch' => $term,
				'srlimit'  => 1,
				'srprop'   => '',
				'format'   => 'json',
			),
			sprintf( self::SEARCH_URL, rawurlencode( $language ) )
		);

		$response = wp_remote_get(
			$search_url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'search_request_failed',
				__( 'Could not resolve the Wikipedia article title.', 'wp-wikipedia-factcheck' )
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'search_request_failed',
				__( 'Could not resolve the Wikipedia article title.', 'wp-wikipedia-factcheck' )
			);
		}

		$title = $body['query']['search'][0]['title'] ?? null;
		if ( empty( $title ) || ! is_string( $title ) ) {
			return null;
		}

		return str_replace( ' ', '_', $title );
	}
}
