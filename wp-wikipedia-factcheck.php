<?php
/**
 * Plugin Name: WP Wikipedia Fact-Check
 * Description: Wikipedia-powered fact-check panel for the Gutenberg block editor using the Wikimedia Enterprise API.
 * Version: 1.0.7
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Ross Mulcahy
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-wikipedia-factcheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_WIKIPEDIA_FACTCHECK_VERSION', '1.0.7' );
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
 * Register Gutenberg blocks.
 */
function wp_wikipedia_factcheck_register_blocks(): void {
	register_block_type(
		WP_WIKIPEDIA_FACTCHECK_PATH . 'src/blocks/fact-box',
		array(
			'render_callback' => 'wp_wikipedia_factcheck_render_fact_box_block',
		)
	);

	register_block_type(
		WP_WIKIPEDIA_FACTCHECK_PATH . 'src/blocks/tooltip',
		array(
			'render_callback' => 'wp_wikipedia_factcheck_render_tooltip_block',
		)
	);
}
add_action( 'init', 'wp_wikipedia_factcheck_register_blocks' );

/**
 * Check if Wikimedia credentials are configured.
 */
function wp_wikipedia_factcheck_has_credentials(): bool {
	$username = get_option( 'wikimedia_enterprise_username' );
	$password = get_option( 'wikimedia_enterprise_password' );
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
}
add_action( 'rest_api_init', 'wp_wikipedia_factcheck_register_routes' );

/**
 * Handle lookup requests.
 */
function wp_wikipedia_factcheck_lookup( WP_REST_Request $request ): WP_REST_Response {
	$term     = trim( $request->get_param( 'term' ) );
	$language = get_option( 'wikimedia_enterprise_language', 'en' );

	// Check cache first.
	$cache_key = 'wikimedia_fc_v2_' . md5( $term . '_' . $language );
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return new WP_REST_Response( $cached, 200 );
	}

	$api    = new Wikimedia_API();
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
	$language = get_option( 'wikimedia_enterprise_language', 'en' );
	$api      = new Wikimedia_API();
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
	$term          = trim( (string) $request->get_param( 'term' ) );
	$selected_text = trim( wp_strip_all_tags( (string) $request->get_param( 'selected_text' ) ) );
	$language      = get_option( 'wikimedia_enterprise_language', 'en' );

	if ( '' === $selected_text ) {
		return new WP_REST_Response(
			array(
				'code'    => 'empty_selection',
				'message' => __( 'Select some editor text to analyze.', 'wp-wikipedia-factcheck' ),
			),
			400
		);
	}

	$api     = new Wikimedia_API();
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

	$analysis = Wikimedia_Factcheck_AI::analyze_claim( $selected_text, $article );
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
}

/**
 * Generate interesting fact candidates from a Wikipedia article.
 */
function wp_wikipedia_factcheck_generate_facts( WP_REST_Request $request ): WP_REST_Response {
	$term     = trim( (string) $request->get_param( 'term' ) );
	$language = get_option( 'wikimedia_enterprise_language', 'en' );
	$api      = new Wikimedia_API();
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

	$facts = Wikimedia_Factcheck_AI::generate_interesting_facts( $article );
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
}

/**
 * Preserve the exact Wikimedia password while removing WordPress slashes.
 *
 * @param string $value Raw password value.
 * @return string
 */
function wp_wikipedia_factcheck_sanitize_password( string $value ): string {
	return wp_unslash( $value );
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
		'wikimedia_enterprise_username',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	register_setting(
		'wp_wikipedia_factcheck_settings',
		'wikimedia_enterprise_password',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'wp_wikipedia_factcheck_sanitize_password',
			'default'           => '',
		)
	);

	register_setting(
		'wp_wikipedia_factcheck_settings',
		'wikimedia_enterprise_language',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
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
		'wikimedia_enterprise_username',
		__( 'Username', 'wp-wikipedia-factcheck' ),
		function () {
			$value = get_option( 'wikimedia_enterprise_username', '' );
			echo '<input type="text" name="wikimedia_enterprise_username" value="' . esc_attr( $value ) . '" class="regular-text" autocomplete="off" />';
		},
		'wp-wikipedia-factcheck',
		'wp_wikipedia_factcheck_credentials'
	);

	add_settings_field(
		'wikimedia_enterprise_password',
		__( 'Password', 'wp-wikipedia-factcheck' ),
		function () {
			$value = get_option( 'wikimedia_enterprise_password', '' );
			echo '<input type="password" name="wikimedia_enterprise_password" value="' . esc_attr( $value ) . '" class="regular-text" autocomplete="off" />';
		},
		'wp-wikipedia-factcheck',
		'wp_wikipedia_factcheck_credentials'
	);

	add_settings_field(
		'wikimedia_enterprise_language',
		__( 'Default Language', 'wp-wikipedia-factcheck' ),
		function () {
			$value     = get_option( 'wikimedia_enterprise_language', 'en' );
			$languages = array(
				'en' => 'English',
				'de' => 'Deutsch',
				'fr' => 'Français',
				'es' => 'Español',
				'it' => 'Italiano',
				'pt' => 'Português',
				'ja' => '日本語',
				'zh' => '中文',
			);
			echo '<select name="wikimedia_enterprise_language">';
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
		<script>
		document.getElementById('wp-wikipedia-factcheck-test').addEventListener('click', function() {
			const btn = this;
			const result = document.getElementById('wp-wikipedia-factcheck-test-result');
			btn.disabled = true;
			result.textContent = '<?php echo esc_js( __( 'Testing...', 'wp-wikipedia-factcheck' ) ); ?>';
			result.style.color = '';

			fetch('<?php echo esc_url( rest_url( 'wp-wikipedia-factcheck/v1/test-connection' ) ); ?>', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>'
				}
			})
			.then(function(r) { return r.json(); })
			.then(function(data) {
				result.textContent = data.message;
				result.style.color = data.success ? 'green' : 'red';
			})
			.catch(function() {
				result.textContent = '<?php echo esc_js( __( 'Request failed.', 'wp-wikipedia-factcheck' ) ); ?>';
				result.style.color = 'red';
			})
			.finally(function() {
				btn.disabled = false;
			});
		});
		</script>
	</div>
	<?php
}

/**
 * Ensure password option is not autoloaded.
 */
function wp_wikipedia_factcheck_activate(): void {
	add_option( 'wikimedia_enterprise_password', '', '', false );
}
register_activation_hook( __FILE__, 'wp_wikipedia_factcheck_activate' );
