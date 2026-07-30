<?php
namespace RankMathPro\Dependencies\Groupone\Marketplace\Controllers;

use RankMathPro\Dependencies\Groupone\Marketplace\Models\MarketplaceModel;

use WP_REST_Response;
class MarketplaceController {
	protected $config;
	protected $model;
	protected $assets_base_path;
	protected $assets_base_url;

	/**
	 * Create + initialize controller instance.
	 *
	 * @param array $config
	 * @return self
	 */
	public static function boot( array $config = [] ): self {
		$instance = new self( $config );
		$instance->init();
		return $instance;
	}

	public function __construct( array $config ) {
		$this->config = wp_parse_args( $config, [
			'parent_menu_slug' => 'options-general.php',
			'page_title'       => __( 'Plugin Marketplace', 'text-domain' ),
			'menu_title'       => __( 'Marketplace', 'text-domain' ),
			'menu_slug'        => 'plugin-marketplace',
			'api_url'          => '', // default to empty, React can decide
			'brand'            => '', // optional brand identifier for marketplace API
			'css_url'          => '', //  optional additional CSS
			'css_handle'       => 'marketplace-frontend-style',
			'assets_path'      => '', //  Optional: explicit path to package root containing frontend/ directory
			'payload'          => [], //  Optional: key-value array passed as headers for API authentication
			'insert_menu_before_slug' => '', // Optional: submenu slug before which marketplace menu should appear
		] );

		// Defer model and asset initialization until needed (optimization for multi-plugin installs)
		$this->model = null;
		$this->assets_base_path = null;
		$this->assets_base_url = null;
	}

	/**
	 * Lazy-load model instance (optimization for multi-plugin installs).
	 * Only instantiated when actually needed (REST endpoint or page render).
	 */
	protected function get_model() {
		if ( $this->model === null ) {
			$this->model = new MarketplaceModel( $this->config['api_url'] );
		}
		return $this->model;
	}

	/**
	 * Lazy-load asset paths (optimization for multi-plugin installs).
	 * Only resolved when the marketplace page is being rendered.
	 */
	protected function ensure_assets_resolved() {
		if ( $this->assets_base_path === null || $this->assets_base_url === null ) {
			$this->resolve_assets_paths();
		}
	}

	/**
	 * Resolve and validate assets paths.
	 * Priority: 1) Explicit config, 2) Auto-detect via composer.json
	 */
	protected function resolve_assets_paths() {
		$package_root = '';

		// Option 1: Use explicitly provided assets_path
		if ( ! empty( $this->config['assets_path'] ) ) {
			$package_root = wp_normalize_path( $this->config['assets_path'] );
		}

		// Option 2: Auto-detect using composer.json as anchor
		if ( empty( $package_root ) ) {
			$package_root = $this->find_package_root_via_composer();
		}

		// Validate that frontend assets actually exist
		$package_root = trailingslashit( $package_root );
		$frontend_js = $package_root . 'frontend/build/index.js';

		if ( ! file_exists( $frontend_js ) ) {
			// Last resort: use current directory (will likely fail but won't crash)
			$package_root = trailingslashit( dirname( __DIR__ ) );
		}

		$this->assets_base_path = $package_root;
		$this->assets_base_url  = $this->convert_path_to_url( $package_root );
	}

	/**
	 * Find package root by looking for composer.json
	 * Works for both Mozart-prefixed and regular vendor installations
	 *
	 * @return string Package root path or empty string
	 */
	protected function find_package_root_via_composer() {
		$current_dir = wp_normalize_path( __DIR__ );
		$max_depth = 10; // Safety limit

		for ( $i = 0; $i < $max_depth; $i++ ) {
			$composer_path = trailingslashit( $current_dir ) . 'composer.json';

			if ( file_exists( $composer_path ) ) {
				// Verify this is our package by checking the name
				$composer_data = json_decode( file_get_contents( $composer_path ), true );

				if ( isset( $composer_data['name'] ) && $composer_data['name'] === 'groupone/marketplace' ) {
					return $current_dir;
				}
			}

			// Move up one directory
			$parent_dir = dirname( $current_dir );

			// Stop if we've reached the filesystem root
			if ( $parent_dir === $current_dir ) {
				break;
			}

			$current_dir = $parent_dir;
		}

		return '';
	}

	/**
	 * Get the brand-specific AJAX action prefix to avoid conflicts when multiple
	 * plugins embed the marketplace module simultaneously.
	 *
	 * @return string e.g. 'onecom_marketplace' or 'rankmath_marketplace'
	 */
	private function get_ajax_prefix(): string {
		$brand = $this->config['brand'] ?? '';
		return $brand ? "{$brand}_marketplace" : 'marketplace';
	}

	/**
	 * Convert filesystem path to URL
	 *
	 * @param string $path Absolute filesystem path
	 * @return string URL
	 */
	protected function convert_path_to_url( $path ) {
		$path = wp_normalize_path( $path );
		$plugins_dir = wp_normalize_path( WP_PLUGIN_DIR );

		// Check if path is within plugins directory
		if ( strpos( $path, $plugins_dir ) === 0 ) {
			$relative = ltrim( str_replace( $plugins_dir, '', $path ), '/' );
			return trailingslashit( plugins_url( $relative ) );
		}

		// Fallback: try content directory
		$content_dir = wp_normalize_path( WP_CONTENT_DIR );
		if ( strpos( $path, $content_dir ) === 0 ) {
			$relative = ltrim( str_replace( $content_dir, '', $path ), '/' );
			return trailingslashit( content_url( $relative ) );
		}

		// Last resort: return plugins URL with the full path (likely incorrect but won't crash)
		return trailingslashit( plugins_url() );
	}

	/**
	 * Initialize hooks.
	 */
	public function init() {
		if ( is_admin() || is_network_admin() ) {
			add_action( 'admin_menu', [ $this, 'register_menu' ] );
			add_action( 'admin_menu', [ $this, 'register_addons_menu' ] );
			add_action( 'network_admin_menu', [ $this, 'register_menu' ] );
			add_action( 'network_admin_menu', [ $this, 'register_addons_menu' ] );

			// If insert_menu_before_slug is configured, reorder submenus after all menus are registered.
			if ( ! empty( $this->config['insert_menu_before_slug'] ) ) {
				add_action( 'admin_menu', [ $this, 'reorder_submenus' ], 999 );
				add_action( 'network_admin_menu', [ $this, 'reorder_submenus' ], 999 );
			}
			$prefix = $this->get_ajax_prefix();
			add_action( "wp_ajax_{$prefix}_install_plugin", [ $this, 'ajax_install_plugin' ] );
			add_action( "wp_ajax_{$prefix}_activate_plugin", [ $this, 'ajax_activate_plugin' ] );
			add_action( "wp_ajax_{$prefix}_deactivate_plugin", [ $this, 'ajax_deactivate_plugin' ] );
			add_action( "wp_ajax_{$prefix}_delete_plugin", [ $this, 'ajax_delete_plugin' ] );
			add_action( "wp_ajax_{$prefix}_save_pending_procurement", [ $this, 'ajax_save_pending_procurement' ] );
			add_action( "wp_ajax_{$prefix}_clear_pending_procurement", [ $this, 'ajax_clear_pending_procurement' ] );
			add_action( "wp_ajax_{$prefix}_get_pending_procurements", [ $this, 'ajax_get_pending_procurements' ] );
			add_action( "wp_ajax_{$prefix}_subscribe", [ $this, 'ajax_subscribe' ] );
			add_action( "wp_ajax_{$prefix}_track_status", [ $this, 'ajax_track_status' ] );

			add_action( "wp_ajax_{$prefix}_get_subscriptions_list", [ $this, 'get_subscriptions_list' ] );

			add_action( "wp_ajax_{$prefix}_cancel_subscription", [ $this, 'cancel_subscriptions' ] );
			add_action( "wp_ajax_{$prefix}_unsubscribe", [ $this, 'ajax_unsubscribe' ] );
			add_action( "wp_ajax_{$prefix}_clear_subscription_list", [ $this, 'ajax_clear_subscription_list' ] );
			add_action( "wp_ajax_{$prefix}_save_pending_cancellation", [ $this, 'ajax_save_pending_cancellation' ] );
			add_action( "wp_ajax_{$prefix}_clear_pending_cancellation", [ $this, 'ajax_clear_pending_cancellation' ] );


			//reset transient for marketplace catalog
			add_action('upgrader_process_complete', [$this, 'reset_transient_on_core_update'], 10, 2);
			add_action('update_option_WPLANG', [$this, 'reset_transient_on_locale_change'], 999, 0);
		}

		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	public function register_menu() {
		add_submenu_page(
			$this->config['parent_menu_slug'],
			$this->config['page_title'],
			$this->config['menu_title'],
			'manage_options',
			$this->config['menu_slug'],
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Register addons admin menu page with configurable slug.
	 *
	 */
	public function register_addons_menu() {
		$menu_slug      = $this->config['addons_menu_slug'] ?: 'onecom-marketplace-products';
		$has_page_title = ! empty( $this->config['addons_page_title'] );
		$has_menu_title = ! empty( $this->config['addons_menu_title'] );

		$page_title = $has_page_title ? $this->config['addons_page_title'] : __( 'Marketplace Products', '' );
		$menu_title = $has_menu_title ? $this->config['addons_menu_title'] : __( 'Your add-ons', '' );

		// When neither title is supplied, self-parent the page so it's registered without
		// a visible menu entry. WP's menu renderer only iterates $menu (top-level), so a
		// self-parented submenu never renders. Access check still passes because
		// $submenu[$menu_slug] contains our entry with its own capability.
		$parent_menu_slug = ( $has_page_title || $has_menu_title ) ? $this->config['parent_menu_slug'] : $menu_slug;

		add_submenu_page(
			$parent_menu_slug,
			$page_title,
			$menu_title,
			'manage_options',
			$menu_slug,
			[ $this, 'render_addons_page' ]
		);
	}

	/**
	 * Reorder the parent menu's submenu entries so that the marketplace page
	 * and the addons page both appear immediately before the item whose slug
	 * matches $config['insert_menu_before_slug'].
	 *
	 * Called on admin_menu / network_admin_menu at priority 999, after every
	 * other plugin has had a chance to register its own submenu items.
	 * If the target slug is not found in the submenu the array is left untouched.
	 */
	public function reorder_submenus() {
		global $submenu;

		$parent      = $this->config['parent_menu_slug'];
		$before_slug = $this->config['insert_menu_before_slug'];
		$market_slug = $this->config['menu_slug'];
		$addons_slug = $this->config['addons_menu_slug'] ?: 'onecom-marketplace-products';

		// Nothing to do if the parent has no submenu yet.
		if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
			return;
		}

		$items = array_values( $submenu[ $parent ] );

		// Check that insert_menu_before_slug exists; bail without touching anything if not.
		$before_found = false;
		foreach ( $items as $item ) {
			if ( isset( $item[2] ) && $item[2] === $before_slug ) {
				$before_found = true;
				break;
			}
		}

		if ( ! $before_found ) {
			return;
		}

		// Separate our two items from the rest, preserving their registration data.
		$market_item = null;
		$addons_item = null;
		$rest        = [];

		foreach ( $items as $item ) {
			if ( isset( $item[2] ) && $item[2] === $market_slug ) {
				$market_item = $item;
			} elseif ( isset( $item[2] ) && $item[2] === $addons_slug ) {
				$addons_item = $item;
			} else {
				$rest[] = $item;
			}
		}

		// Re-build the submenu: insert our items right before insert_menu_before_slug.
		$reordered = [];
		foreach ( $rest as $item ) {
			if ( isset( $item[2] ) && $item[2] === $before_slug ) {
				if ( $market_item !== null ) {
					$reordered[] = $market_item;
				}
				if ( $addons_item !== null ) {
					$reordered[] = $addons_item;
				}
			}
			$reordered[] = $item;
		}

		$submenu[ $parent ] = $reordered;
	}

	public function render_addons_page() {
		// Lazy-load assets only when this page is actually rendered (optimization)
		$this->ensure_assets_resolved();

		$base_path = $this->assets_base_path;
		$base_url  = $this->assets_base_url;

		// Enqueue JS dynamically for addons
		$js_file   = 'frontend/build/addons.js';
		$js_path   = $base_path . $js_file;
		$js_url    = $base_url . $js_file;

		wp_enqueue_script(
			'marketplace-addons-frontend',
			$js_url,
			[ 'wp-element' ],
			file_exists( $js_path ) ? filemtime( $js_path ) : '1.0.0',
			true
		);

		// Enqueue CSS dynamically (custom or default)
		if ( ! empty( $this->config['custom_css'] ) ) {
			wp_enqueue_style( 'marketplace-css', esc_url( $this->config['custom_css'] ), [], '1.0.0' );
		} else {
			// Enqueue library CSS (one.min.css)
			$one_css_file = 'assets/min-css/one.min.css';
			$one_css_path = $base_path . $one_css_file;
			wp_enqueue_style(
				'marketplace-one-css',
				$base_url . $one_css_file,
				[],
				file_exists( $one_css_path ) ? filemtime( $one_css_path ) : '1.0.0'
			);

			// Enqueue marketplace custom CSS (marketplace.min.css)
			$marketplace_css_file = 'assets/min-css/marketplace.min.css';
			$marketplace_css_path = $base_path . $marketplace_css_file;
			wp_enqueue_style(
				'marketplace-custom-css',
				$base_url . $marketplace_css_file,
				[ 'marketplace-one-css' ],
				file_exists( $marketplace_css_path ) ? filemtime( $marketplace_css_path ) : '1.0.0'
			);
		}

		// Get all active plugin slugs to evaluate rules on frontend
		$active_plugins = $this->get_active_plugin_slugs();

		// Get active theme author to evaluate theme-based rules on frontend
		$active_theme_author = $this->get_active_theme_author();

		// Get current user information
		$current_user = wp_get_current_user();
		$wp_user = $current_user->user_login ? hash( 'sha256', $current_user->user_login ) : '';
		$wp_admin_email = $current_user->user_email ? hash( 'sha256', $current_user->user_email ) : '';
		$wp_role = ! empty( $current_user->roles ) ? $current_user->roles[0] : '';
		$user_id = $current_user->ID;

		// Get WordPress environment information
		$wp_version = get_bloginfo( 'version' );
		$php_version = phpversion();
		$locale = get_locale();

		// Build global properties for Mixpanel
		$global_properties = [
			'application' => 'wordpress_marketplace',
			'brand' => $this->config['brand'],
			'wp_locale' => $locale,
			'wp_version' => $wp_version,
			'php_version' => $php_version,
			'wp_user' => $wp_user, // Hashed
			'wp_admin_email' => $wp_admin_email, // Hashed
			'wp_role' => $wp_role,
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '',
			'user_id' => $user_id,
		];

		// Merge custom mixpanel properties from config if provided
		if ( ! empty( $this->config['mixp_props'] ) && is_array( $this->config['mixp_props'] ) ) {
			$global_properties = array_merge( $global_properties, $this->config['mixp_props'] );
		}

		// Unset is_sandbox from global properties so it's not sent with events
		if ( isset( $global_properties['is_sandbox'] ) ) {
			unset( $global_properties['is_sandbox'] );
		}

		// Get distinct_id from config if provided
		$distinct_id = ! empty( $this->config['mixp_distinct_id'] ) ? $this->config['mixp_distinct_id'] : '';

		// Get data consent status from config
		$data_consent_status = ! empty( $this->config['data_consent_status'] ) ? $this->config['data_consent_status'] : false;

		// Get Mixpanel token
		$mixpanel_token = $this->get_mixpanel_token();

		// Build base localized config
		$localized_config = [
			'apiBaseUrl' => trailingslashit( rest_url( ( $this->config['brand'] ?: 'marketplace' ) . '-marketplace/v1/plugins' ) ),
			'apiUrl'     => $this->config['api_url'],
			'locale' => $locale,
			'brand' => $this->config['brand'],
			'useWPHandlers' => true,
			'wpConfig' => [
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'adminUrl' => admin_url(),
				'nonce'    => wp_create_nonce( 'marketplace_nonce' ),
				'ajaxActionPrefix' => $this->get_ajax_prefix(),
				'rankMathRegistrationSkip' => (bool) ( ! empty( get_option( 'rank_math_registration_skip' ) ) && ( get_option( 'rank_math_registration_skip' ) === '1' || get_option( 'rank_math_registration_skip' ) === true ) ),
			],
			'enableDefaultStyles' => empty( $this->config['custom_css'] ),
			'assetsBaseUrl' => $base_url,
			'wpVersion' => $wp_version,
			'activePlugins' => $active_plugins,
			'activeThemeAuthor' => $active_theme_author,
			'data_consent_status' => $data_consent_status,
			'labels'=>array(
				'install' => __('Install', 'onecom-wp'),
				'installing' => __('Installing', 'onecom-wp'),
				'activate' => __('Activate', 'onecom-wp'),
				'deactivate' => __('Deactivate', 'onecom-wp'),
				'activating' => __('Activating', 'onecom-wp'),
				'deactivating' => __('Deactivating', 'onecom-wp'),
				'download' => __('Download', 'onecom-wp'),
				'downloading' => __('Downloading...', 'onecom-wp'),
				'learnMore' => __('Learn more', 'onecom-wp'),
				'all' => __('All', 'onecom-wp'),
				'recommendedPlugins' => __('Recommended plugins', 'onecom-wp'),
				'discouraged' => __('Discouraged plugins', 'onecom-wp'),
				'moreDetails' => __('More details', 'onecom-wp'),
			),
			// Always send mixpanel config so it can be used when consent is granted dynamically
			'mixpanel' => [
				'token' => $mixpanel_token,
				'globalProperties' => $global_properties,
				'distinctId' => $distinct_id,
			],
			'pendingProcurements'  => get_option( "{$this->config['brand']}_marketplace_pending_procurements", [] ),
		'pendingCancellations' => get_option( "{$this->config['brand']}_marketplace_pending_cancellations", [] ),
		'menuSlug'             => $this->config['menu_slug'],
		'addonsMenuSlug'       => $this->config['addons_menu_slug'] ?: 'onecom-marketplace-products',
		'siteUrl'              => home_url(),
		'dateFormat'           => get_option( 'date_format', 'F j, Y' ),
		];

		// Localize JS with config
		wp_localize_script( 'marketplace-addons-frontend', 'marketplaceConfig', $localized_config );

		$brand_class = ! empty( $this->config['brand'] ) ? ' brand-' . sanitize_html_class( $this->config['brand'] ) : '';
		echo '<div id="marketplace-addons-root" class="gv-activated' . esc_attr( $brand_class ) . '"></div>';
	}

	public function render_admin_page() {
		// Lazy-load assets only when this page is actually rendered (optimization)
		$this->ensure_assets_resolved();

		$base_path = $this->assets_base_path;
		$base_url  = $this->assets_base_url;

		// Enqueue JS dynamically
		$js_file   = 'frontend/build/index.js';
		$js_path   = $base_path . $js_file;
		$js_url    = $base_url . $js_file;

		wp_enqueue_script(
			'marketplace-frontend',
			$js_url,
			[ 'wp-element' ],
			file_exists( $js_path ) ? filemtime( $js_path ) : '1.0.0',
			true
		);

		// Enqueue CSS dynamically (custom or default)
		if ( ! empty( $this->config['custom_css'] ) ) {
			wp_enqueue_style( 'marketplace-css', esc_url( $this->config['custom_css'] ), [], '1.0.0' );
		} else {
			// Enqueue library CSS (one.min.css)
			$one_css_file = 'assets/min-css/one.min.css';
			$one_css_path = $base_path . $one_css_file;
			wp_enqueue_style(
				'marketplace-one-css',
				$base_url . $one_css_file,
				[],
				file_exists( $one_css_path ) ? filemtime( $one_css_path ) : '1.0.0'
			);

			// Enqueue marketplace custom CSS (marketplace.min.css)
			$marketplace_css_file = 'assets/min-css/marketplace.min.css';
			$marketplace_css_path = $base_path . $marketplace_css_file;
			wp_enqueue_style(
				'marketplace-custom-css',
				$base_url . $marketplace_css_file,
				[ 'marketplace-one-css' ],
				file_exists( $marketplace_css_path ) ? filemtime( $marketplace_css_path ) : '1.0.0'
			);
		}

		// Get all active plugin slugs to evaluate rules on frontend
		$active_plugins = $this->get_active_plugin_slugs();

		// Get active theme author to evaluate theme-based rules on frontend
		$active_theme_author = $this->get_active_theme_author();

		// Get current user information
		$current_user = wp_get_current_user();
		$wp_user = $current_user->user_login ? hash( 'sha256', $current_user->user_login ) : '';
		$wp_admin_email = $current_user->user_email ? hash( 'sha256', $current_user->user_email ) : '';
		$wp_role = ! empty( $current_user->roles ) ? $current_user->roles[0] : '';
		$user_id = $current_user->ID;

		// Get WordPress environment information
		$wp_version = get_bloginfo( 'version' );
		$php_version = phpversion();
		$locale = get_locale();

		// Build global properties for Mixpanel
		$global_properties = [
			'application' => 'wordpress_marketplace',
			'brand' => $this->config['brand'],
			'wp_locale' => $locale,
			'wp_version' => $wp_version,
			'php_version' => $php_version,
			'wp_user' => $wp_user, // Hashed
			'wp_admin_email' => $wp_admin_email, // Hashed
			'wp_role' => $wp_role,
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '',
			'user_id' => $user_id,
		];

		// Merge custom mixpanel properties from config if provided
		if ( ! empty( $this->config['mixp_props'] ) && is_array( $this->config['mixp_props'] ) ) {
			$global_properties = array_merge( $global_properties, $this->config['mixp_props'] );
		}

		// Unset is_sandbox from global properties so it's not sent with events
		if ( isset( $global_properties['is_sandbox'] ) ) {
			unset( $global_properties['is_sandbox'] );
		}

 	// Get distinct_id from config if provided
 	$distinct_id = ! empty( $this->config['mixp_distinct_id'] ) ? $this->config['mixp_distinct_id'] : '';

 	// Get data consent status from config
 	$data_consent_status = ! empty( $this->config['data_consent_status'] ) ? $this->config['data_consent_status'] : false;

	// Get Mixpanel token
	$mixpanel_token = $this->get_mixpanel_token();

 	// Build base localized config
 	$localized_config = [
 		'apiBaseUrl' => trailingslashit( rest_url( ( $this->config['brand'] ?: 'marketplace' ) . '-marketplace/v1/plugins' ) ),
 		'apiUrl'     => $this->config['api_url'],
 		'locale' => $locale,
 		'brand' => $this->config['brand'],
 		'useWPHandlers' => true,
 		'wpConfig' => [
 			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
 			'adminUrl' => admin_url(),
 			'nonce'    => wp_create_nonce( 'marketplace_nonce' ),
				'ajaxActionPrefix' => $this->get_ajax_prefix(),
 			'rankMathRegistrationSkip' => (bool) ( ! empty( get_option( 'rank_math_registration_skip' ) ) && ( get_option( 'rank_math_registration_skip' ) === '1' || get_option( 'rank_math_registration_skip' ) === true ) ),
 		],
 		'enableDefaultStyles' => empty( $this->config['custom_css'] ),
 		'assetsBaseUrl' => $base_url,
 		'wpVersion' => $wp_version,
 		'activePlugins' => $active_plugins,
 		'activeThemeAuthor' => $active_theme_author,
 		'data_consent_status' => $data_consent_status,
 		'labels'=>array(
 			'install' => __('Install', 'onecom-wp'),
 			'installing' => __('Installing', 'onecom-wp'),
 			'activate' => __('Activate', 'onecom-wp'),
 			'deactivate' => __('Deactivate', 'onecom-wp'),
 			'activating' => __('Activating', 'onecom-wp'),
 			'deactivating' => __('Deactivating', 'onecom-wp'),
 			'download' => __('Download', 'onecom-wp'),
 			'downloading' => __('Downloading...', 'onecom-wp'),
 			'learnMore' => __('Learn more', 'onecom-wp'),
 			'all' => __('All', 'onecom-wp'),
 			'recommendedPlugins' => __('Recommended plugins', 'onecom-wp'),
 			'discouraged' => __('Discouraged plugins', 'onecom-wp'),
 			'moreDetails' => __('More details', 'onecom-wp'),
 		),
 		// Always send mixpanel config so it can be used when consent is granted dynamically
 		'mixpanel' => [
 			'token' => $mixpanel_token,
 			'globalProperties' => $global_properties,
 			'distinctId' => $distinct_id,
 		],
 		'pendingProcurements'  => get_option( "{$this->config['brand']}_marketplace_pending_procurements", [] ),
		'pendingCancellations' => get_option( "{$this->config['brand']}_marketplace_pending_cancellations", [] ),
		'menuSlug'             => $this->config['menu_slug'],
		'addonsMenuSlug'       => $this->config['addons_menu_slug'] ?: 'onecom-marketplace-products',
		'siteUrl'              => home_url(),
		'dateFormat'           => get_option( 'date_format', 'F j, Y' ),
 	];

 	// Localize JS with config
 	wp_localize_script( 'marketplace-frontend', 'marketplaceConfig', $localized_config );

		$brand_class = ! empty( $this->config['brand'] ) ? ' brand-' . sanitize_html_class( $this->config['brand'] ) : '';
		echo '<div id="marketplace-root" class="gv-activated' . esc_attr( $brand_class ) . '"></div>';
	}

	/**
	 * Get Mixpanel token based on sandbox mode.
	 *
	 * @return string
	 */
	protected function get_mixpanel_token(): string {
		$token = '517e881edc2636e99a2ecf013d8134d3';
		if ( ! empty( $this->config['mixp_props']['is_sandbox'] ) && $this->config['mixp_props']['is_sandbox'] === true ) {
			$token = '4cdc36e9083c158244c3e26d280540f6';
		}
		return $token;
	}

	public function register_rest_routes() {
		$brand = $this->config['brand'] ?: 'marketplace';
		$namespace = "{$brand}-marketplace/v1";

		register_rest_route( $namespace, '/plugins', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_plugins' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/plugins/active/(?P<slug>[a-zA-Z0-9-_]+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'check_plugin_activation' ],
			'permission_callback' => '__return_true',
		] );
	}

	public function check_plugin_activation( $request ) {
		$slug = $request->get_param( 'slug' );
		if ( empty( $slug ) ) {
			return new WP_REST_Response( [ 'activated' => false, 'error' => 'Missing slug' ], 400 );
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugin_file = $this->resolve_plugin_file_by_slug( $slug );

		$activated = ( ! empty( $plugin_file ) && function_exists( 'is_plugin_active' ) ) ? is_plugin_active( $plugin_file ) : false;

		return new WP_REST_Response( [
			'slug'      => $slug,
			'activated' => $activated,
		], 200 );
	}


	public function get_plugins( $request ) {

		$brand_name = $this->config['brand'];
		$transient_name = "{$brand_name}_marketplace_catalog";
		$marketplace_catalog = get_site_transient( $transient_name );
		$is_cached = false;

		// Maintenance-mode passthrough from cache. Return the response as-is so the React
		// frontend can render MaintenanceState; skip the catalog enrichment that follows.
		if ( is_array( $marketplace_catalog ) && ! empty( $marketplace_catalog['data']['maintenanceMode'] ) ) {
			return new WP_REST_Response( $marketplace_catalog, 200 );
		}

		if ( is_array( $marketplace_catalog ) &&
			! empty( $marketplace_catalog['success'] ) &&
			isset( $marketplace_catalog['data']['catalog'] ) &&
			is_array( $marketplace_catalog['data']['catalog'] )
		){
			$plugins = $marketplace_catalog;
			$is_cached = true;
		} else {
			// Lazy-load model only when the REST endpoint is called (optimization)
			$plugins = $this->get_model()->fetch_plugins( $this->config['payload'] );

			if ( is_wp_error( $plugins ) ) {
				return new WP_REST_Response( [ 'error' => $plugins->get_error_message() ], 500 );
			}

			// Maintenance-mode passthrough from upstream. Intentionally NOT cached:
			// every Retry click triggers a fresh upstream check so users recover as soon
			// as the API is back, instead of waiting for a transient to expire.
			if ( is_array( $plugins ) && ! empty( $plugins['data']['maintenanceMode'] ) ) {
				return new WP_REST_Response( $plugins, 200 );
			}

			// Cache the catalog for 15 minutes if not already cached
			if (
				! empty( $plugins['success'] ) &&
				isset( $plugins['data']['catalog'] ) &&
				is_array( $plugins['data']['catalog'] )
			){
				set_site_transient( $transient_name, $plugins, 15 * MINUTE_IN_SECONDS );
			} else {
				error_log( 'Invalid catalog structure' );
				return new WP_REST_Response( [ 'error' => 'Invalid catalog structure' ], 500 );
			}
			$is_cached = false;
		}

		// Attach WP state (installed/activated) for both legacy and new shapes
		$add_state = function( $plugin ) {
			if ( empty( $plugin['slug'] ) ) {
				return $plugin;
			}
			// Check if plugin is installed
			$plugin['installed'] = $this->is_installed( $plugin['slug'] );

			// Only resolve plugin file if we need to check activation status
			$plugin['activated'] = false;
			if ( $plugin['installed'] ) {
				$plugin_file = $this->resolve_plugin_file_by_slug( $plugin['slug'] );
				$plugin['activated'] = ( ! empty( $plugin_file ) && function_exists( 'is_plugin_active' ) ) ? is_plugin_active( $plugin_file ) : false;
			}
			return $plugin;
		};

		if ( ! empty( $plugins['data']['catalog'] ) && is_array( $plugins['data']['catalog'] ) ) {
			// New API response structure: data.catalog array
			$plugins['data']['catalog'] = array_map( $add_state, $plugins['data']['catalog'] );
		} elseif ( isset( $plugins['data'] ) && is_array( $plugins['data'] ) && ( array_values( $plugins['data'] ) === $plugins['data'] ) ) {
			// data is a numerically-indexed list of plugins
			$plugins['data'] = array_map( $add_state, $plugins['data'] );
		} elseif ( ! empty( $plugins['data']['sections'] ) && is_array( $plugins['data']['sections'] ) ) {
			foreach ( $plugins['data']['sections'] as $si => $section ) {
				if ( empty( $section['items'] ) || ! is_array( $section['items'] ) ) {
					continue;
				}
				$plugins['data']['sections'][$si]['items'] = array_map( $add_state, $section['items'] );
			}
		} elseif ( ! empty( $plugins['sections'] ) && is_array( $plugins['sections'] ) ) {
			foreach ( $plugins['sections'] as $si => $section ) {
				if ( empty( $section['items'] ) || ! is_array( $section['items'] ) ) {
					continue;
				}
				$plugins['sections'][$si]['items'] = array_map( $add_state, $section['items'] );
			}
 	} elseif ( ! empty( $plugins['data']['ui_json'] ) && is_array( $plugins['data']['ui_json'] ) ) {
 		$plugins['data']['ui_json'] = array_map( $add_state, $plugins['data']['ui_json'] );
 	}

 	// Add is_cached flag to response
 	$plugins['is_cached'] = $is_cached;

 	return new WP_REST_Response( $plugins, 200 );
	}

	/**
	 * Install plugin via WP_Upgrader
	 */
	public function ajax_install_plugin() {
		check_ajax_referer( 'marketplace_nonce', 'nonce' );

		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error([ 'message' => __( 'Permission denied', 'onecom-wp' ) ]);
		}


		$slug        = sanitize_text_field( $_REQUEST['slug'] ?? '' );
		$download_url = esc_url_raw( $_REQUEST['download_url'] ?? '' );

		if ( empty( $slug ) || empty( $download_url ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid plugin data.', 'text-domain' ) ] );
		}

		// Concurrency / idempotency: if the plugin is already on disk before we even
		// touch the upgrader (sibling tab finished installing, another consumer plugin
		// already installed it, prior install attempt, etc.), be honest with the user
		// — return an informative error rather than fake success. The frontend can
		// inspect 'installed: true' to update its UI without showing a "succeeded" toast.
		if ( $this->is_installed( $slug ) ) {
			wp_send_json_error( [
				'message'   => __( 'Plugin is already installed.', 'onecom-wp' ),
				'installed' => true,
				'activated' => false,
			] );
		}

		// Serialize concurrent installs of the same plugin. Without this, two parallel
		// install requests can both pass the pre-check, both download the same package
		// to /tmp, and race on cleanup — observed as duplicate "upgrader returned NULL"
		// entries plus an "unlink: No such file or directory" warning at the same
		// timestamp. The lock has a TTL so a crashed request can't permanently block
		// future installs; we also delete it explicitly on every exit path.
		$lock_key = "marketplace_install_lock_{$slug}";
		if ( get_transient( $lock_key ) ) {
			wp_send_json_error( [
				'message' => __( 'An install is already in progress for this plugin. Please wait a moment and try again.', 'onecom-wp' ),
			] );
		}
		set_transient( $lock_key, time(), 120 );

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
		$result   = $upgrader->install( $download_url ); //  use URL from React

		if ( is_wp_error( $result ) ) {
			delete_transient( $lock_key );
			// Forward WP's actual error message (e.g. "Destination folder already exists"
			// when a parallel install finished mid-flight). Include 'installed' so the
			// frontend knows whether the plugin ended up on disk.
			wp_send_json_error( [
				'message'   => $result->get_error_message(),
				'installed' => $this->is_installed( $slug ),
				'activated' => false,
			] );
		}

		// No WP_Error: if the plugin is now on disk, this request's upgrader put it
		// there. Plugin_Upgrader::install() can legitimately return false/null on
		// benign hiccups (post-install hook noise, etc.), so is_installed() is the
		// source of truth for "did our own install succeed".
		if ( $this->is_installed( $slug ) ) {
			delete_transient( $lock_key );
			wp_send_json_success([
				'message'   => __( 'Plugin installed successfully', 'onecom-wp' ),
				'installed' => true,
				'activated' => false,
			]);
		}

		// Plugin is not on disk — only now treat null/false as a real failure.
		if ( $result === null || $result === false ) {
			delete_transient( $lock_key );
			$skin_messages = method_exists( $upgrader->skin, 'get_upgrade_messages' ) ? $upgrader->skin->get_upgrade_messages() : [];
			$skin_errors   = isset( $upgrader->skin->errors ) ? $upgrader->skin->errors : null;
			error_log( '[Marketplace] install failed for ' . $slug . ' (upgrader returned ' . var_export( $result, true ) . '); URL: ' . $download_url . '; skin messages: ' . wp_json_encode( $skin_messages ) . '; skin errors: ' . wp_json_encode( $skin_errors ) );
			wp_send_json_error( [ 'message' => __( 'Plugin installation failed. Unable to download or extract the plugin. The download URL may be invalid or inaccessible.', 'onecom-wp' ) ] );
		}

		delete_transient( $lock_key );
		wp_send_json_error( [ 'message' => __( 'Plugin installation failed. The plugin was not found after installation.', 'onecom-wp' ) ] );
	}

	/**
	 * Get the author of the currently active theme.
	 *
	 * @return string Theme author or empty string if not available.
	 */
	private function get_active_theme_author(): string {
		if ( ! function_exists( 'wp_get_theme' ) ) {
			return '';
		}

		$theme = wp_get_theme();
		$author = $theme->get( 'Author' );

		return is_string( $author ) ? $author : '';
	}

	/**
	 * Get all active plugin slugs on the site.
	 * Extracts slugs from active plugin paths (e.g., 'plugin-dir/plugin-file.php' -> 'plugin-dir').
	 * For single-file plugins, the slug is the filename without .php extension.
	 *
	 * @return array Array of active plugin slugs.
	 */
	private function get_active_plugin_slugs(): array {
		if ( ! function_exists( 'get_option' ) ) {
			return [];
		}

		$active_plugins = get_option( 'active_plugins', [] );
		$slugs = [];

		foreach ( $active_plugins as $plugin_path ) {
			// Plugin path is like 'plugin-dir/plugin-file.php' or 'single-file-plugin.php'
			if ( strpos( $plugin_path, '/' ) !== false ) {
				// Multi-file plugin: extract directory name as slug
				$parts = explode( '/', $plugin_path );
				$slugs[] = $parts[0];
			} else {
				// Single-file plugin: use filename without .php as slug
				$slugs[] = str_replace( '.php', '', $plugin_path );
			}
		}

		// Remove duplicates and return
		return array_values( array_unique( $slugs ) );
	}

	/**
	 * Check if plugin is installed.
	 *
	 * This function checks whether a plugin is physically installed in the WordPress plugins directory.
	 * It handles both simple directory slugs (e.g., 'akismet') and full plugin file paths
	 * (e.g., 'seo-by-rank-math-pro/rank-math-pro.php').
	 *
	 * For cases where the slug doesn't match the directory name exactly (e.g., slug "rank-math-pro"
	 * but installed as "seo-by-rank-math-pro/rank-math-pro.php"), the function will scan installed
	 * plugins to find matches based on the main plugin file name.
	 *
	 * @param string $slug Plugin slug or plugin file path (e.g., 'akismet' or 'dirname/filename.php').
	 * @return boolean True if the plugin is installed, false otherwise.
	 */
	private function is_installed( $slug = '' ): bool {
		if ( empty( $slug ) ) {
			return false;
		}

		// If slug contains a slash, it's likely a full plugin file path like 'dirname/filename.php'
		if ( strpos( $slug, '/' ) !== false ) {
			// Check if the full plugin file exists
			$plugin_file_path = WP_PLUGIN_DIR . '/' . $slug;
			if ( file_exists( $plugin_file_path ) ) {
				return true;
			}

			// Also check if just the directory exists (handles edge cases)
			$plugin_dir = dirname( $plugin_file_path );
			if ( file_exists( $plugin_dir ) && is_dir( $plugin_dir ) ) {
				return true;
			}

			return false;
		}

		// For simple slugs, check if directory exists
		$plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
		if ( file_exists( $plugin_dir ) && is_dir( $plugin_dir ) ) {
			return true;
		}

		// Also check if it's a single-file plugin (slug.php)
		$plugin_file = WP_PLUGIN_DIR . '/' . $slug . '.php';
		if ( file_exists( $plugin_file ) ) {
			return true;
		}

		// Fallback: scan installed plugins for partial matches
		// This handles cases like:
		// 1. slug "rank-math-pro" matching "seo-by-rank-math-pro/rank-math-pro.php" (file name)
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugins = get_plugins();

		foreach ( $plugins as $file => $data ) {
			$parts = explode( '/', $file );
			if ( count( $parts ) === 2 ) {
				$directory = $parts[0];
				$main_file = $parts[1];

				// Check if directory exactly matches the slug
				if ( $directory === $slug ) {
					return true;
				}

				// Check if the main plugin file name matches the slug
				$file_slug = str_replace( '.php', '', $main_file );
				if ( $file_slug === $slug ) {
					return true;
				}
			} elseif ( count( $parts ) === 1 ) {
				// Single file plugin
				$file_slug = str_replace( '.php', '', $parts[0] );
				if ( $file_slug === $slug ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Resolve the plugin's main file by slug by scanning installed plugins.
	 * Handles cases like 'seo-by-rank-math/rank-math.php' where the main file
	 * does not match slug/slug.php.
	 *
	 * For cases where the slug doesn't match the directory name exactly (e.g., slug "rank-math-pro"
	 * but installed as "seo-by-rank-math-pro/rank-math-pro.php"), the function will scan installed
	 * plugins to find matches based on the main plugin file name.
	 *
	 * @param string $slug
	 * @return string Plugin file path relative to plugins dir, or empty string if not found.
	 */
	private function resolve_plugin_file_by_slug( $slug ): string {
		if ( empty( $slug ) ) {
			return '';
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugins = get_plugins();

		// If incoming "slug" already looks like a plugin file (contains a slash or ends with .php),
		// try an exact match first.
		if ( strpos( $slug, '/' ) !== false || substr( $slug, -4 ) === '.php' ) {
			if ( isset( $plugins[ $slug ] ) ) {
				return $slug;
			}
			// Also try trimming any leading slashes just in case
			$trimmed = ltrim( $slug, '/' );
			if ( isset( $plugins[ $trimmed ] ) ) {
				return $trimmed;
			}
		}

		// Otherwise, treat input as directory slug and try common patterns.
		foreach ( $plugins as $file => $data ) {
			if ( strpos( $file, $slug . '/' ) === 0 || $file === $slug . '.php' ) {
				return $file;
			}
		}

		// Fallback: scan installed plugins for partial matches
		// This handles cases like:
		// 1. slug "rank-math-pro" matching "seo-by-rank-math-pro/rank-math-pro.php" (file name)
		foreach ( $plugins as $file => $data ) {
			$parts = explode( '/', $file );
			if ( count( $parts ) === 2 ) {
				$directory = $parts[0];
				$main_file = $parts[1];

				// Check if directory exactly matches the slug
				if ( $directory === $slug ) {
					return $file;
				}

				// Check if the main plugin file name matches the slug
				$file_slug = str_replace( '.php', '', $main_file );
				if ( $file_slug === $slug ) {
					return $file;
				}
			} elseif ( count( $parts ) === 1 ) {
				// Single file plugin
				$file_slug = str_replace( '.php', '', $parts[0] );
				if ( $file_slug === $slug ) {
					return $file;
				}
			}
		}

		return '';
	}

	public function ajax_activate_plugin() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to activate plugins.', 'text-domain' ) ] );
		}

		check_ajax_referer( 'marketplace_nonce', '_wpnonce' );

		$slug = isset( $_REQUEST['slug'] ) ? sanitize_key( wp_unslash( $_REQUEST['slug'] ) ) : '';

		if ( empty( $slug ) ) {
			wp_send_json_error( [ 'message' => __( 'Missing plugin slug.', 'text-domain' ) ] );
		}

		// Check if plugin is installed first
		if ( ! $this->is_installed( $slug ) ) {
			wp_send_json_error( [ 'message' => __( 'Plugin not installed.', 'text-domain' ) ] );
		}

		// Resolve the plugin file using the enhanced helper function
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugin_file = $this->resolve_plugin_file_by_slug( $slug );

		if ( empty( $plugin_file ) ) {
			wp_send_json_error( [ 'message' => __( 'Plugin file not found.', 'text-domain' ) ] );
		}

		if ( $plugin_file === 'seo-by-rank-math-pro/rank-math-pro.php' ) {
			// Also activate the Free version if it's installed and not active
			$free_plugin_file = 'seo-by-rank-math/rank-math.php';
			if ( $this->is_installed( 'seo-by-rank-math' ) && ! is_plugin_active( $free_plugin_file ) ) {
				activate_plugin( $free_plugin_file );
			}
		}

		$result = activate_plugin( $plugin_file );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [
			'installed' => true,
			'activated' => true,
			'message'   => __( 'Plugin activated successfully.', 'text-domain' ),
		] );
	}

	public function ajax_deactivate_plugin() {
		check_ajax_referer( 'marketplace_nonce', 'nonce' );

		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error([ 'message' => __( 'Permission denied', 'onecom-wp' ) ]);
		}

		$slug = sanitize_text_field( $_REQUEST['slug'] ?? '' );
		if ( empty( $slug ) ) {
			wp_send_json_error([ 'message' => __( 'Invalid plugin slug', 'onecom-wp' ) ]);
		}

		// Check if plugin is installed first
		if ( ! $this->is_installed( $slug ) ) {
			wp_send_json_error([ 'message' => __( 'Plugin not installed', 'onecom-wp' ) ]);
		}

		// Resolve the plugin file using the enhanced helper function
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugin_file = $this->resolve_plugin_file_by_slug( $slug );

		if ( $plugin_file === 'seo-by-rank-math/rank-math.php' ) {
			// Also deactivate the Pro version if it's active
			if ( is_plugin_active( 'seo-by-rank-math-pro/rank-math-pro.php' ) ) {
				deactivate_plugins( 'seo-by-rank-math-pro/rank-math-pro.php' );
			}
		}

		if ( empty( $plugin_file ) ) {
			wp_send_json_error([ 'message' => __( 'Plugin file not found', 'onecom-wp' ) ]);
		}

		// Ensure the plugin is loaded so its deactivation hooks are registered.
		if ( is_plugin_active( $plugin_file ) ) {
			include_once WP_PLUGIN_DIR . '/' . $plugin_file;
		}

		// Handle both site-wide and network-wide deactivation to ensure hooks fire correctly
		deactivate_plugins( $plugin_file, false, null );

		if ( is_plugin_active( $plugin_file ) ) {
			wp_send_json_error([ 'message' => __( 'Failed to deactivate plugin', 'onecom-wp' ) ]);
		}

		wp_send_json_success([
			'message'   => __( 'Plugin deactivated successfully', 'onecom-wp' ),
			'installed' => true,
			'activated' => false,
		]);
	}

	public function ajax_delete_plugin() {
		check_ajax_referer( 'marketplace_nonce', 'nonce' );

		if ( ! current_user_can( 'delete_plugins' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'onecom-wp' ) ] );
		}

		$slug = sanitize_text_field( $_REQUEST['slug'] ?? '' );
		if ( empty( $slug ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid plugin slug', 'onecom-wp' ) ] );
		}

		// Check if plugin is installed first
		if ( ! $this->is_installed( $slug ) ) {
			wp_send_json_error( [ 'message' => __( 'Plugin not installed', 'onecom-wp' ) ] );
		}

		// Resolve the plugin file
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugin_file = $this->resolve_plugin_file_by_slug( $slug );

		if ( empty( $plugin_file ) ) {
			wp_send_json_error( [ 'message' => __( 'Plugin file not found', 'onecom-wp' ) ] );
		}

		// Check if the plugin is active
		if ( is_plugin_active( $plugin_file ) ) {
			wp_send_json_error( [ 'message' => __( 'Cannot delete an active plugin. Please deactivate it first.', 'onecom-wp' ) ] );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$result = delete_plugins( [ $plugin_file ] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		if ( $result === false ) {
			wp_send_json_error( [ 'message' => __( 'Failed to delete plugin', 'onecom-wp' ) ] );
		}

		wp_send_json_success( [
			'message'   => __( 'Plugin deleted successfully', 'onecom-wp' ),
			'installed' => false,
			'activated' => false,
		] );
	}

	/**
	 * Resets the transient on core update.
	 * @param $upgrader
	 * @param $hook_extra
	 * @return void
	 */
	public function reset_transient_on_core_update($upgrader, $hook_extra): void
	{
		if (
			empty( $hook_extra['action'] ) || 'update' !== $hook_extra['action'] ||
			empty( $hook_extra['type'] )   || 'core' !== $hook_extra['type']
		) {
			return;
		}

		$brand_name = $this->config['brand'];
		$transient_name = "{$brand_name}_marketplace_catalog";
		$deleted = delete_site_transient( $transient_name );

		if ( $deleted ) {
			error_log( 'Reset marketplace catalog transient on core update' );
		}
	}

	/**
	 * Resets the marketplace catalog transient by deleting it from the site transients on locale change.
	 *
	 * @return void
	 */
	public function reset_transient_on_locale_change(){
		$brand_name = $this->config['brand'];
		$transient_name = "{$brand_name}_marketplace_catalog";
		$deleted = delete_site_transient( $transient_name );

		if ( $deleted ) {
			error_log( 'Reset marketplace catalog transient on locale change' );
		}
	}

	/**
	 * Save a pending procurement entry for a plugin.
	 * Called when subscription API returns success but no accessUrl yet.
	 */
	public function ajax_save_pending_procurement() {
		check_ajax_referer( 'marketplace_nonce', 'nonce' );

		$slug            = sanitize_text_field( $_POST['slug'] ?? '' );
		$subscription_id = sanitize_text_field( $_POST['subscriptionId'] ?? '' );
		$product_id      = sanitize_text_field( $_POST['product_id'] ?? '' );

		if ( empty( $slug ) || empty( $subscription_id ) ) {
			wp_send_json_error( [ 'message' => 'Missing required fields.' ] );
		}

		$brand_name = $this->config['brand'];
		$option_name = "{$brand_name}_marketplace_pending_procurements";
		$pending = get_option( $option_name, [] );

		$pending[ $slug ] = [
			'subscriptionId' => $subscription_id,
			'product_id'     => $product_id,
			'timestamp'      => time(),
		];

		update_option( $option_name, $pending, false );

		wp_send_json_success( [ 'message' => 'Pending procurement saved.' ] );
	}

	/**
	 * Return the current pending procurements from the DB.
	 * Called by the frontend refresh button to re-sync React state with the server.
	 */
	public function ajax_get_pending_procurements(): void {
		check_ajax_referer( 'marketplace_nonce', 'nonce' );

		$brand_name = $this->config['brand'];
		$pending    = get_option( "{$brand_name}_marketplace_pending_procurements", [] );

		wp_send_json_success( $pending );
	}

	/**
	 * Proxy a subscription creation request to the external marketplace API.
	 * Avoids CORS issues by making the request server-side and keeps the API key out of the browser.
	 */
	public function ajax_subscribe() {
		check_ajax_referer( 'marketplace_nonce', 'nonce' );

		$product_id     = sanitize_text_field( $_POST['productId'] ?? '' );
		$price_amount   = floatval( $_POST['priceAmount'] ?? 0 );
		$price_currency = sanitize_text_field( $_POST['priceCurrency'] ?? '' );
		$price_period   = sanitize_text_field( $_POST['pricePeriod'] ?? '' );

		if ( empty( $product_id ) ) {
			wp_send_json_error( [ 'message' => 'Missing required fields.' ] );
		}

		$data = [
			'productId' => $product_id,
			'price'     => $price_amount,
			'currency'  => $price_currency,
			'interval'  => $price_period,
		];

		// Merge config credentials (username, api_key, locale) with subscribe-specific fields.
		// 'action' overwrites the one in config['payload'] since it comes second in array_merge.
		$payload = array_merge(
			$this->config['payload'] ?? [],
			[
				'action' => 'wp-marketplace-subscribe',
				'data'   => wp_json_encode( $data ),
			]
		);

		$result = $this->get_model()->request( $payload, 'POST' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		if ( isset( $result['error'] ) && $result['error'] ) {
			wp_send_json_error( $result );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Poll the external API to track subscription/procurement status.
	 * Proxies wp-marketplace-track-status calls server-side to keep credentials out of browser.
	 */
	public function ajax_track_status() {
		check_ajax_referer( 'marketplace_nonce', 'nonce' );

		$subscription_id = sanitize_text_field( $_POST['subscriptionId'] ?? '' );
		$resource_type   = sanitize_text_field( $_POST['resourceType'] ?? 'procurement' );

		if ( empty( $subscription_id ) ) {
			wp_send_json_error( [ 'message' => 'Missing subscriptionId.' ] );
		}

		$payload = array_merge(
			$this->config['payload'] ?? [],
			[
				'action'        => 'wp-marketplace-track-status',
				'resource_type' => $resource_type,
				'resource_id'   => $subscription_id,
			]
		);

		$result = $this->get_model()->request( $payload, 'POST' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		if ( isset( $result['error'] ) && $result['error'] ) {
			wp_send_json_error( $result );
		}

		wp_send_json_success( $result );
	}

	/**
	 *
	 * @return void
	 */
	public function get_subscriptions_list(): void
	{
		check_ajax_referer( 'marketplace_nonce', 'nonce' );

		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error([ 'message' => __( 'Permission denied', 'onecom-wp' ) ]);
		}

		$brand_name = $this->config['brand'];
		$transient_name = "{$brand_name}_subscription_list";
		$get_subscription_list = get_site_transient( $transient_name );

		if ( is_array($get_subscription_list ) && ! empty( $get_subscription_list ) ) {
			wp_send_json_success( $get_subscription_list );
		}

		$payload = array_merge(
			$this->config['payload'] ?? [],
			[
				'action' => 'wp-marketplace-subscription-list'
			]
		);

		//The default method is GET
		$result = $this->get_model()->request( $payload);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		if ( isset( $result['error'] ) && $result['error'] ) {
			wp_send_json_error( $result );
		}

		$get_subscription_list = $result['data']["subscriptions"] ?? null;

		if ( ! empty( $get_subscription_list ) && is_array( $get_subscription_list ) ) {
			set_site_transient( $transient_name, $get_subscription_list, 15 * MINUTE_IN_SECONDS );
		} else {
			$get_subscription_list = [];
		}

		wp_send_json_success($get_subscription_list);
	}

	public function cancel_subscriptions() {
		check_ajax_referer( 'marketplace_nonce', 'nonce' );

		wp_send_json_success( [ 'message' => 'Subscriptions cancelled successfully.' ] );
	}

	/**
	 * Proxy a cancellation (unsubscribe) request to the external marketplace API.
	 * Sends a DELETE request with action wp-marketplace-unsubscribe.
	 * Clears the cached subscription list so the next fetch reflects the cancellation.
	 */
	public function ajax_unsubscribe(): void {
		check_ajax_referer( 'marketplace_nonce', 'nonce' );

		$subscription_id = sanitize_text_field( $_POST['subscriptionId'] ?? '' );

		if ( empty( $subscription_id ) ) {
			wp_send_json_error( [ 'message' => 'Missing subscriptionId.' ] );
		}

		$payload = array_merge(
			$this->config['payload'] ?? [],
			[
				'action' => 'wp-marketplace-unsubscribe',
				'data'   => wp_json_encode( [ 'subscriptionID' => $subscription_id ] ),
			]
		);

		$result = $this->get_model()->request( $payload, 'DELETE' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		if ( isset( $result['error'] ) && $result['error'] ) {
			wp_send_json_error( $result );
		}

		// Clear cached subscription list so next fetch returns fresh data
		$brand_name = $this->config['brand'];
		delete_site_transient( "{$brand_name}_subscription_list" );

		wp_send_json_success( $result );
	}

	/**
	 * Delete the cached subscription list transient so the next fetch returns fresh data from the API.
	 * Called by the frontend after a new subscription is initiated or completed.
	 */
	public function ajax_clear_subscription_list(): void {
		check_ajax_referer( 'marketplace_nonce', 'nonce' );

		$brand_name = $this->config['brand'];
		delete_site_transient( "{$brand_name}_subscription_list" );

		wp_send_json_success( [ 'message' => 'Subscription list cache cleared.' ] );
	}

	/**
	 * Persist a pending cancellation to the DB so the cancelling state survives page reloads.
	 */
	public function ajax_save_pending_cancellation(): void {
		check_ajax_referer( 'marketplace_nonce', 'nonce' );

		$slug            = sanitize_text_field( $_POST['slug'] ?? '' );
		$subscription_id = sanitize_text_field( $_POST['subscriptionId'] ?? '' );

		if ( empty( $slug ) || empty( $subscription_id ) ) {
			wp_send_json_error( [ 'message' => 'Missing required fields.' ] );
		}

		$brand_name       = $this->config['brand'];
		$option_name      = "{$brand_name}_marketplace_pending_cancellations";
		$pending          = get_option( $option_name, [] );
		$pending[ $slug ] = [
			'subscriptionId' => $subscription_id,
			'timestamp'      => time(),
		];
		update_option( $option_name, $pending, false );

		wp_send_json_success( [ 'message' => 'Pending cancellation saved.' ] );
	}

	/**
	 * Remove a pending cancellation entry from the DB once cancellation is confirmed.
	 */
	public function ajax_clear_pending_cancellation(): void {
		check_ajax_referer( 'marketplace_nonce', 'nonce' );

		$slug = sanitize_text_field( $_POST['slug'] ?? '' );

		if ( empty( $slug ) ) {
			wp_send_json_error( [ 'message' => 'Missing slug.' ] );
		}

		$brand_name  = $this->config['brand'];
		$option_name = "{$brand_name}_marketplace_pending_cancellations";
		$pending     = get_option( $option_name, [] );
		if ( isset( $pending[ $slug ] ) ) {
			unset( $pending[ $slug ] );
			update_option( $option_name, $pending, false );
		}

		wp_send_json_success( [ 'message' => 'Pending cancellation cleared.' ] );
	}

	/**
	 * Clear a pending procurement entry for a plugin.
	 * Called when procurement completes and plugin is installed, or on manual cleanup.
	 */
	public function ajax_clear_pending_procurement() {
		check_ajax_referer( 'marketplace_nonce', 'nonce' );

		$slug = sanitize_text_field( $_POST['slug'] ?? '' );

		if ( empty( $slug ) ) {
			wp_send_json_error( [ 'message' => 'Missing slug.' ] );
		}

		$brand_name  = $this->config['brand'];
		$option_name = "{$brand_name}_marketplace_pending_procurements";
		$pending     = get_option( $option_name, [] );

		if ( isset( $pending[ $slug ] ) ) {
			unset( $pending[ $slug ] );
			update_option( $option_name, $pending, false );
		}

		wp_send_json_success( [ 'message' => 'Pending procurement cleared.' ] );
	}
}
