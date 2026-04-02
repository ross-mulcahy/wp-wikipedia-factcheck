<?php
/**
 * Plugin Name: WP Wikipedia Fact-Check
 * Plugin URI:  https://github.com/developer-developer/wp-wikipedia-factcheck
 * Description: Wikipedia-powered fact-check panel for the Gutenberg block editor using the Wikimedia Enterprise API.
 * Version: 1.0.16
 * Requires at least: 6.9
 * Requires PHP: 8.1
 * Author: Ross Mulcahy
 * Author URI:  https://developer-developer.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-wikipedia-factcheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_WIKIPEDIA_FACTCHECK_VERSION', '1.0.16' );
define( 'WP_WIKIPEDIA_FACTCHECK_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_WIKIPEDIA_FACTCHECK_URL', plugins_url( '/', __FILE__ ) );

require_once WP_WIKIPEDIA_FACTCHECK_PATH . 'includes/class-wikimedia-api.php';
require_once WP_WIKIPEDIA_FACTCHECK_PATH . 'includes/class-wikimedia-factcheck-ai.php';

/**
 * Register shared editor and block assets.
 */
function wp_wikipedia_factcheck_register_assets(): void {
	$asset_file = WP_WIKIPEDIA_FACTCHECK_PATH . 'build/index.asset.php';

	if ( ! file_exists( $asset_file ) ) {
		return;
	}

	$asset = include $asset_file;
	$script_version = WP_WIKIPEDIA_FACTCHECK_VERSION . '-' . $asset['version'];
	$style_file     = WP_WIKIPEDIA_FACTCHECK_PATH . 'build/style-index.css';
	$style_version  = WP_WIKIPEDIA_FACTCHECK_VERSION;

	if ( file_exists( $style_file ) ) {
		$style_version .= '-' . filemtime( $style_file );
	}

	wp_register_script(
		'wp-wikipedia-factcheck-editor',
		WP_WIKIPEDIA_FACTCHECK_URL . 'build/index.js',
		$asset['dependencies'],
		$script_version,
		true
	);

	wp_register_style(
		'wp-wikipedia-factcheck-style',
		WP_WIKIPEDIA_FACTCHECK_URL . 'build/style-index.css',
		array(),
		$style_version
	);

	wp_localize_script(
		'wp-wikipedia-factcheck-editor',
		'wpWikipediaFactcheck',
		array(
			'restUrl'        => rest_url( 'wp-wikipedia-factcheck/v1/' ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'hasCredentials' => wp_wikipedia_factcheck_has_credentials(),
			'settingsUrl'    => admin_url( 'options-general.php?page=wp-wikipedia-factcheck' ),
		)
	);
}
add_action( 'init', 'wp_wikipedia_factcheck_register_assets' );

/**
 * Enqueue editor assets.
 */
function wp_wikipedia_factcheck_enqueue_editor_assets(): void {
	wp_enqueue_script( 'wp-wikipedia-factcheck-editor' );
	wp_enqueue_style( 'wp-wikipedia-factcheck-style' );
}
add_action( 'enqueue_block_editor_assets', 'wp_wikipedia_factcheck_enqueue_editor_assets' );

/**
 * Check if Wikimedia credentials are configured.
 */
function wp_wikipedia_factcheck_has_credentials(): bool {
	$username = get_option( 'wp_wikipedia_factcheck_username' );
	$password = get_option( 'wp_wikipedia_factcheck_password' );
	return ! empty( $username ) && ! empty( $password );
}

/**
 * Register REST API routes.
 */
function wp_wikipedia_factcheck_register_routes(): void {
	register_rest_route(
		'wp-wikipedia-factcheck/v1',
		'/lookup',
		array(
			'methods'             => 'POST',
			'callback'            => 'wp_wikipedia_factcheck_lookup',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'term' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => function ( $value ) {
						$value = trim( $value );
						return ! empty( $value ) && mb_strlen( $value ) <= 200;
					},
				),
			),
		)
	);

	register_rest_route(
		'wp-wikipedia-factcheck/v1',
		'/test-connection',
		array(
			'methods'             => 'POST',
			'callback'            => 'wp_wikipedia_factcheck_test_connection',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		)
	);

	register_rest_route(
		'wp-wikipedia-factcheck/v1',
		'/analyze',
		array(
			'methods'             => 'POST',
			'callback'            => 'wp_wikipedia_factcheck_analyze_selection',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'term'          => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'selected_text' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'wp_kses_post',
				),
			),
		)
	);

	register_rest_route(
		'wp-wikipedia-factcheck/v1',
		'/interesting-facts',
		array(
			'methods'             => 'POST',
			'callback'            => 'wp_wikipedia_factcheck_generate_facts',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'term' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);

	register_rest_route(
		'wp-wikipedia-factcheck/v1',
		'/suggest-topics',
		array(
			'methods'             => 'POST',
			'callback'            => 'wp_wikipedia_factcheck_suggest_topics',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'content' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'wp_kses_post',
				),
			),
		)
	);

	register_rest_route(
		'wp-wikipedia-factcheck/v1',
		'/briefing',
		array(
			'methods'             => 'POST',
			'callback'            => 'wp_wikipedia_factcheck_generate_briefing',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'term'    => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'content' => array(
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'wp_kses_post',
				),
			),
		)
	);

	register_rest_route(
		'wp-wikipedia-factcheck/v1',
		'/ai-health',
		array(
			'methods'             => 'POST',
			'callback'            => 'wp_wikipedia_factcheck_ai_health',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		)
	);
}
add_action( 'rest_api_init', 'wp_wikipedia_factcheck_register_routes' );

/**
 * Handle lookup requests.
 */
function wp_wikipedia_factcheck_lookup( WP_REST_Request $request ): WP_REST_Response {
	$term     = trim( $request->get_param( 'term' ) );
	$language = get_option( 'wp_wikipedia_factcheck_language', 'en' );

	// Check cache first.
	$cache_key = 'wpwfc_lookup_' . md5( $term . '_' . $language );
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return new WP_REST_Response( $cached, 200 );
	}

	$api    = new WP_Wikipedia_Factcheck_API();
	$result = $api->lookup( $term, $language );

	if ( is_wp_error( $result ) ) {
		$error_code = $result->get_error_code();

		$status_map = array(
			'no_credentials' => 401,
			'auth_failed'    => 401,
			'not_found'      => 200,
			'rate_limited'   => 429,
		);

		$status = $status_map[ $error_code ] ?? 502;

		if ( 'not_found' === $error_code ) {
			$response_data = array(
				'found' => false,
				'term'  => $term,
			);
			set_transient( $cache_key, $response_data, HOUR_IN_SECONDS );
			return new WP_REST_Response( $response_data, 200 );
		}

		return new WP_REST_Response(
			array(
				'code'    => $error_code,
				'message' => $result->get_error_message(),
			),
			$status
		);
	}

	// Cache successful responses for 1 hour.
	set_transient( $cache_key, $result, HOUR_IN_SECONDS );

	return new WP_REST_Response( $result, 200 );
}

/**
 * Handle test connection requests.
 */
function wp_wikipedia_factcheck_test_connection(): WP_REST_Response {
	$language = get_option( 'wp_wikipedia_factcheck_language', 'en' );
	$api      = new WP_Wikipedia_Factcheck_API();
	$result   = $api->lookup( 'Main_Page', $language );

	if ( is_wp_error( $result ) ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => $result->get_error_message(),
			),
			200
		);
	}

	return new WP_REST_Response(
		array(
			'success' => true,
			'message' => __( 'Connection successful! Authentication and article lookup both worked.', 'wp-wikipedia-factcheck' ),
		),
		200
	);
}

/**
 * Analyze selected text against Wikipedia using the AI Client.
 */
function wp_wikipedia_factcheck_analyze_selection( WP_REST_Request $request ): WP_REST_Response {
	try {
		$term          = trim( (string) $request->get_param( 'term' ) );
		$selected_text = trim( wp_strip_all_tags( (string) $request->get_param( 'selected_text' ) ) );
		$language      = get_option( 'wp_wikipedia_factcheck_language', 'en' );

		if ( '' === $selected_text ) {
			return new WP_REST_Response(
				array(
					'code'    => 'empty_selection',
					'message' => __( 'Select some editor text to analyze.', 'wp-wikipedia-factcheck' ),
				),
				400
			);
		}

		$api     = new WP_Wikipedia_Factcheck_API();
		$article = $api->lookup( $term, $language );

		if ( is_wp_error( $article ) ) {
			return new WP_REST_Response(
				array(
					'code'    => $article->get_error_code(),
					'message' => $article->get_error_message(),
				),
				502
			);
		}

		$analysis = WP_Wikipedia_Factcheck_AI::analyze_claim( $selected_text, $article );
		if ( is_wp_error( $analysis ) ) {
			return new WP_REST_Response(
				array(
					'code'    => $analysis->get_error_code(),
					'message' => $analysis->get_error_message(),
				),
				(int) ( $analysis->get_error_data()['status'] ?? 502 )
			);
		}

		return new WP_REST_Response( $analysis, 200 );
	} catch ( Throwable $exception ) {
		wp_wikipedia_factcheck_log_error( 'analysis_failed', $exception );
		return new WP_REST_Response(
			array(
				'code'    => 'analysis_failed',
				'message' => __( 'An unexpected error occurred during analysis.', 'wp-wikipedia-factcheck' ),
			),
			500
		);
	}
}

/**
 * Generate interesting fact candidates from a Wikipedia article.
 */
function wp_wikipedia_factcheck_generate_facts( WP_REST_Request $request ): WP_REST_Response {
	try {
		$term     = trim( (string) $request->get_param( 'term' ) );
		$language = get_option( 'wp_wikipedia_factcheck_language', 'en' );
		$api      = new WP_Wikipedia_Factcheck_API();
		$article  = $api->lookup( $term, $language );

		if ( is_wp_error( $article ) ) {
			return new WP_REST_Response(
				array(
					'code'    => $article->get_error_code(),
					'message' => $article->get_error_message(),
				),
				502
			);
		}

		$facts = WP_Wikipedia_Factcheck_AI::generate_interesting_facts( $article );
		if ( is_wp_error( $facts ) ) {
			return new WP_REST_Response(
				array(
					'code'    => $facts->get_error_code(),
					'message' => $facts->get_error_message(),
				),
				(int) ( $facts->get_error_data()['status'] ?? 502 )
			);
		}

		return new WP_REST_Response(
			array(
				'article' => $article,
				'facts'   => $facts,
			),
			200
		);
	} catch ( Throwable $exception ) {
		wp_wikipedia_factcheck_log_error( 'interesting_facts_failed', $exception );
		return new WP_REST_Response(
			array(
				'code'    => 'interesting_facts_failed',
				'message' => __( 'An unexpected error occurred while generating facts.', 'wp-wikipedia-factcheck' ),
			),
			500
		);
	}
}

/**
 * Suggest Wikipedia topics from the current draft content.
 */
function wp_wikipedia_factcheck_suggest_topics( WP_REST_Request $request ): WP_REST_Response {
	try {
		$content = trim( wp_strip_all_tags( (string) $request->get_param( 'content' ) ) );
		$content = mb_substr( preg_replace( '/\s+/', ' ', $content ), 0, 6000 );

		$suggestions = WP_Wikipedia_Factcheck_AI::suggest_topics_from_content( $content );
		if ( is_wp_error( $suggestions ) ) {
			return new WP_REST_Response(
				array(
					'code'    => $suggestions->get_error_code(),
					'message' => $suggestions->get_error_message(),
				),
				(int) ( $suggestions->get_error_data()['status'] ?? 400 )
			);
		}

		return new WP_REST_Response(
			array(
				'suggestions' => $suggestions,
			),
			200
		);
	} catch ( Throwable $exception ) {
		wp_wikipedia_factcheck_log_error( 'suggest_topics_failed', $exception );
		return new WP_REST_Response(
			array(
				'code'    => 'suggest_topics_failed',
				'message' => __( 'An unexpected error occurred while suggesting topics.', 'wp-wikipedia-factcheck' ),
			),
			500
		);
	}
}

/**
 * Generate an editorial briefing for a Wikipedia match.
 */
function wp_wikipedia_factcheck_generate_briefing( WP_REST_Request $request ): WP_REST_Response {
	try {
		$term     = trim( (string) $request->get_param( 'term' ) );
		$content  = trim( wp_strip_all_tags( (string) $request->get_param( 'content' ) ) );
		$content  = mb_substr( preg_replace( '/\s+/', ' ', $content ), 0, 6000 );
		$language = get_option( 'wp_wikipedia_factcheck_language', 'en' );
		$api      = new WP_Wikipedia_Factcheck_API();
		$article  = $api->lookup( $term, $language );

		if ( is_wp_error( $article ) ) {
			return new WP_REST_Response(
				array(
					'code'    => $article->get_error_code(),
					'message' => $article->get_error_message(),
				),
				502
			);
		}

		$briefing = WP_Wikipedia_Factcheck_AI::generate_article_briefing( $article, $content );
		if ( is_wp_error( $briefing ) ) {
			return new WP_REST_Response(
				array(
					'code'    => $briefing->get_error_code(),
					'message' => $briefing->get_error_message(),
				),
				(int) ( $briefing->get_error_data()['status'] ?? 400 )
			);
		}

		return new WP_REST_Response(
			array(
				'article'  => $article,
				'briefing' => $briefing,
			),
			200
		);
	} catch ( Throwable $exception ) {
		wp_wikipedia_factcheck_log_error( 'briefing_failed', $exception );
		return new WP_REST_Response(
			array(
				'code'    => 'briefing_failed',
				'message' => __( 'An unexpected error occurred while generating the briefing.', 'wp-wikipedia-factcheck' ),
			),
			500
		);
	}
}

/**
 * Return AI client debug information for administrators.
 */
function wp_wikipedia_factcheck_ai_health(): WP_REST_Response {
	return new WP_REST_Response( WP_Wikipedia_Factcheck_AI::health_check(), 200 );
}

/**
 * Get supported Wikipedia languages.
 *
 * @return array<string,string> Language code => label.
 */
function wp_wikipedia_factcheck_get_supported_languages(): array {
	return array(
		'en' => 'English',
		'de' => 'Deutsch',
		'fr' => 'Français',
		'es' => 'Español',
		'it' => 'Italiano',
		'pt' => 'Português',
		'ja' => '日本語',
		'zh' => '中文',
	);
}

/**
 * Sanitize language setting against the allowlist.
 *
 * @param string $value Language code.
 * @return string Validated language code or 'en'.
 */
function wp_wikipedia_factcheck_sanitize_language( string $value ): string {
	$value     = sanitize_text_field( $value );
	$languages = wp_wikipedia_factcheck_get_supported_languages();
	return isset( $languages[ $value ] ) ? $value : 'en';
}

/**
 * Encrypt the password before storing it.
 *
 * Uses wp_encrypt() when available (WP 6.8+), otherwise stores as-is.
 *
 * @param string $value Raw password value.
 * @return string Encrypted (or plain) password.
 */
function wp_wikipedia_factcheck_sanitize_password( string $value ): string {
	$value = wp_unslash( $value );
	if ( function_exists( 'wp_encrypt' ) ) {
		$encrypted = wp_encrypt( $value, 'wp_wikipedia_factcheck' );
		if ( ! is_wp_error( $encrypted ) ) {
			return $encrypted;
		}
	}
	return $value;
}

/**
 * Decrypt the stored password for use.
 *
 * @param string $value Stored password value.
 * @return string Decrypted password.
 */
function wp_wikipedia_factcheck_decrypt_password( string $value ): string {
	if ( '' === $value ) {
		return '';
	}
	if ( function_exists( 'wp_decrypt' ) ) {
		$decrypted = wp_decrypt( $value, 'wp_wikipedia_factcheck' );
		if ( ! is_wp_error( $decrypted ) ) {
			return $decrypted;
		}
	}
	return $value;
}

/**
 * Log an error server-side when WP_DEBUG is enabled.
 *
 * @param string    $context   Short label for where the error happened.
 * @param Throwable $exception The caught exception.
 */
function wp_wikipedia_factcheck_log_error( string $context, Throwable $exception ): void {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			sprintf(
				'WP Wikipedia Fact-Check [%s]: %s in %s:%d',
				$context,
				$exception->getMessage(),
				$exception->getFile(),
				$exception->getLine()
			)
		);
	}
}

/**
 * Render a fact box block.
 */
function wp_wikipedia_factcheck_render_fact_box_block( array $attributes ): string {
	$headline    = trim( (string) ( $attributes['headline'] ?? '' ) );
	$fact        = trim( (string) ( $attributes['fact'] ?? '' ) );
	$term        = trim( (string) ( $attributes['term'] ?? '' ) );
	$article_url = esc_url( (string) ( $attributes['articleUrl'] ?? '' ) );

	if ( '' === $fact ) {
		return '';
	}

	$title = '' !== $headline ? $headline : __( 'Wikipedia Fact', 'wp-wikipedia-factcheck' );

	ob_start();
	?>
	<aside <?php echo get_block_wrapper_attributes( array( 'class' => 'wp-wikipedia-factcheck-fact-box' ) ); ?>>
		<span class="wp-wikipedia-factcheck-fact-box__eyebrow"><?php esc_html_e( 'Fact box', 'wp-wikipedia-factcheck' ); ?></span>
		<h3 class="wp-wikipedia-factcheck-fact-box__title"><?php echo esc_html( $title ); ?></h3>
		<p class="wp-wikipedia-factcheck-fact-box__fact"><?php echo esc_html( $fact ); ?></p>
		<?php if ( '' !== $term || '' !== $article_url ) : ?>
			<p class="wp-wikipedia-factcheck-fact-box__source">
				<?php esc_html_e( 'Source:', 'wp-wikipedia-factcheck' ); ?>
				<?php if ( '' !== $article_url ) : ?>
					<a href="<?php echo esc_url( $article_url ); ?>"><?php echo esc_html( $term ?: __( 'Wikipedia article', 'wp-wikipedia-factcheck' ) ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $term ); ?>
				<?php endif; ?>
			</p>
		<?php endif; ?>
	</aside>
	<?php
	return (string) ob_get_clean();
}

/**
 * Render a tooltip block.
 */
function wp_wikipedia_factcheck_render_tooltip_block( array $attributes ): string {
	$label       = trim( (string) ( $attributes['label'] ?? '' ) );
	$term        = trim( (string) ( $attributes['term'] ?? '' ) );
	$content     = trim( (string) ( $attributes['content'] ?? '' ) );
	$article_url = esc_url( (string) ( $attributes['articleUrl'] ?? '' ) );

	if ( '' === $label || '' === $content ) {
		return '';
	}

	ob_start();
	?>
	<span <?php echo get_block_wrapper_attributes( array( 'class' => 'wp-wikipedia-factcheck-tooltip' ) ); ?>>
		<span class="wp-wikipedia-factcheck-tooltip__label" tabindex="0"><?php echo esc_html( $label ); ?></span>
		<span class="wp-wikipedia-factcheck-tooltip__bubble" role="note">
			<strong class="wp-wikipedia-factcheck-tooltip__term"><?php echo esc_html( $term ?: $label ); ?></strong>
			<span class="wp-wikipedia-factcheck-tooltip__content"><?php echo esc_html( $content ); ?></span>
			<?php if ( '' !== $article_url ) : ?>
				<a class="wp-wikipedia-factcheck-tooltip__link" href="<?php echo esc_url( $article_url ); ?>">
					<?php esc_html_e( 'Read on Wikipedia', 'wp-wikipedia-factcheck' ); ?>
				</a>
			<?php endif; ?>
		</span>
	</span>
	<?php
	return (string) ob_get_clean();
}

/**
 * Register settings page.
 */
function wp_wikipedia_factcheck_admin_menu(): void {
	add_options_page(
		__( 'Wikipedia Fact-Check', 'wp-wikipedia-factcheck' ),
		__( 'Wikipedia Fact-Check', 'wp-wikipedia-factcheck' ),
		'manage_options',
		'wp-wikipedia-factcheck',
		'wp_wikipedia_factcheck_settings_page'
	);
}
add_action( 'admin_menu', 'wp_wikipedia_factcheck_admin_menu' );

/**
 * Register settings.
 */
function wp_wikipedia_factcheck_register_settings(): void {
	register_setting(
		'wp_wikipedia_factcheck_settings',
		'wp_wikipedia_factcheck_username',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	register_setting(
		'wp_wikipedia_factcheck_settings',
		'wp_wikipedia_factcheck_password',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'wp_wikipedia_factcheck_sanitize_password',
			'default'           => '',
		)
	);

	register_setting(
		'wp_wikipedia_factcheck_settings',
		'wp_wikipedia_factcheck_language',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'wp_wikipedia_factcheck_sanitize_language',
			'default'           => 'en',
		)
	);

	add_settings_section(
		'wp_wikipedia_factcheck_credentials',
		__( 'Wikimedia Enterprise Credentials', 'wp-wikipedia-factcheck' ),
		function () {
			echo '<p>' . esc_html__( 'Enter your Wikimedia Enterprise API credentials. These are used server-side to authenticate with the Wikimedia API.', 'wp-wikipedia-factcheck' ) . '</p>';
		},
		'wp-wikipedia-factcheck'
	);

	add_settings_field(
		'wp_wikipedia_factcheck_username',
		__( 'Username', 'wp-wikipedia-factcheck' ),
		function () {
			$value = get_option( 'wp_wikipedia_factcheck_username', '' );
			echo '<input type="text" name="wp_wikipedia_factcheck_username" value="' . esc_attr( $value ) . '" class="regular-text" autocomplete="off" />';
		},
		'wp-wikipedia-factcheck',
		'wp_wikipedia_factcheck_credentials'
	);

	add_settings_field(
		'wp_wikipedia_factcheck_password',
		__( 'Password', 'wp-wikipedia-factcheck' ),
		function () {
			$value = wp_wikipedia_factcheck_decrypt_password( get_option( 'wp_wikipedia_factcheck_password', '' ) );
			echo '<input type="password" name="wp_wikipedia_factcheck_password" value="' . esc_attr( $value ) . '" class="regular-text" autocomplete="off" />';
		},
		'wp-wikipedia-factcheck',
		'wp_wikipedia_factcheck_credentials'
	);

	add_settings_field(
		'wp_wikipedia_factcheck_language',
		__( 'Default Language', 'wp-wikipedia-factcheck' ),
		function () {
			$value     = get_option( 'wp_wikipedia_factcheck_language', 'en' );
			$languages = wp_wikipedia_factcheck_get_supported_languages();
			echo '<select name="wp_wikipedia_factcheck_language">';
			foreach ( $languages as $code => $label ) {
				echo '<option value="' . esc_attr( $code ) . '"' . selected( $value, $code, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
		},
		'wp-wikipedia-factcheck',
		'wp_wikipedia_factcheck_credentials'
	);
}
add_action( 'admin_init', 'wp_wikipedia_factcheck_register_settings' );

/**
 * Enqueue the test-connection script on the settings page.
 *
 * @param string $hook_suffix The admin page hook suffix.
 */
function wp_wikipedia_factcheck_enqueue_admin_assets( string $hook_suffix ): void {
	if ( 'settings_page_wp-wikipedia-factcheck' !== $hook_suffix ) {
		return;
	}

	wp_enqueue_script(
		'wp-wikipedia-factcheck-admin',
		WP_WIKIPEDIA_FACTCHECK_URL . 'build/admin.js',
		array(),
		WP_WIKIPEDIA_FACTCHECK_VERSION,
		true
	);

	wp_localize_script(
		'wp-wikipedia-factcheck-admin',
		'wpWikipediaFactcheckAdmin',
		array(
			'testUrl'        => rest_url( 'wp-wikipedia-factcheck/v1/test-connection' ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'i18n'           => array(
				'testing'       => __( 'Testing...', 'wp-wikipedia-factcheck' ),
				'requestFailed' => __( 'Request failed.', 'wp-wikipedia-factcheck' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'wp_wikipedia_factcheck_enqueue_admin_assets' );

/**
 * Render settings page.
 */
function wp_wikipedia_factcheck_settings_page(): void {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Wikipedia Fact-Check Settings', 'wp-wikipedia-factcheck' ); ?></h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'wp_wikipedia_factcheck_settings' );
			do_settings_sections( 'wp-wikipedia-factcheck' );
			submit_button();
			?>
		</form>
		<hr />
		<h2><?php esc_html_e( 'Test Connection', 'wp-wikipedia-factcheck' ); ?></h2>
		<p>
			<button type="button" id="wp-wikipedia-factcheck-test" class="button button-secondary">
				<?php esc_html_e( 'Test Connection', 'wp-wikipedia-factcheck' ); ?>
			</button>
			<span id="wp-wikipedia-factcheck-test-result" style="margin-left: 10px;"></span>
		</p>
	</div>
	<?php
}

/**
 * Register custom blocks from build directory.
 */
function wp_wikipedia_factcheck_register_blocks(): void {
	if ( ! file_exists( WP_WIKIPEDIA_FACTCHECK_PATH . 'build/blocks/fact-box/block.json' ) ) {
		return;
	}

	register_block_type(
		WP_WIKIPEDIA_FACTCHECK_PATH . 'build/blocks/fact-box',
		array(
			'render_callback' => 'wp_wikipedia_factcheck_render_fact_box_block',
		)
	);

	register_block_type(
		WP_WIKIPEDIA_FACTCHECK_PATH . 'build/blocks/tooltip',
		array(
			'render_callback' => 'wp_wikipedia_factcheck_render_tooltip_block',
		)
	);
}
add_action( 'init', 'wp_wikipedia_factcheck_register_blocks' );

/**
 * Ensure password option is not autoloaded.
 */
function wp_wikipedia_factcheck_activate(): void {
	add_option( 'wp_wikipedia_factcheck_password', '', '', false );
}
register_activation_hook( __FILE__, 'wp_wikipedia_factcheck_activate' );

/**
 * Clean up transients on deactivation.
 */
function wp_wikipedia_factcheck_deactivate(): void {
	delete_transient( 'wpwfc_access_token' );

	global $wpdb;
	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpwfc_%' OR option_name LIKE '_transient_timeout_wpwfc_%'"
	);
}
register_deactivation_hook( __FILE__, 'wp_wikipedia_factcheck_deactivate' );
