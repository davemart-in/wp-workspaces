<?php
/**
 * Workspace Registry for WP Workspaces.
 *
 * @package WP_Workspaces
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Workspace Registry class.
 */
class WP_Workspace_Registry {

	/**
	 * Single instance of the class.
	 *
	 * @var WP_Workspace_Registry
	 */
	private static $instance = null;

	/**
	 * Registered workspaces.
	 *
	 * @var array
	 */
	private $workspaces = array();

	/**
	 * Get instance of the class.
	 *
	 * @return WP_Workspace_Registry
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'register_default_workspaces' ), 5 );
		add_action( 'init', array( $this, 'scan_workspace_json_files' ), 10 );
	}

	/**
	 * Register a workspace.
	 *
	 * @param string $id Workspace ID.
	 * @param array  $args Workspace arguments.
	 * @return bool True on success, false on failure.
	 */
	public function register( $id, $args = array() ) {
		// Sanitize the workspace ID.
		$id = sanitize_key( $id );

		if ( empty( $id ) ) {
			return false;
		}

		// Default arguments.
		$defaults = array(
			'label'            => ucfirst( $id ),
			'icon'             => 'dashicons-admin-generic',
			'sidebar_items'    => array(),
			'admin_bar_items'  => array(),
			'distraction_free' => false,
			'fallback'         => false,
			'order'            => 10,
			'condition'        => null, // Callable to determine if workspace should be shown.
		);

		$workspace = wp_parse_args( $args, $defaults );
		$workspace['id'] = $id;

		// Store the workspace.
		$this->workspaces[ $id ] = $workspace;

		return true;
	}

	/**
	 * Get a registered workspace.
	 *
	 * @param string $id Workspace ID.
	 * @return array|null Workspace data or null if not found.
	 */
	public function get( $id ) {
		$id = sanitize_key( $id );
		return isset( $this->workspaces[ $id ] ) ? $this->workspaces[ $id ] : null;
	}

	/**
	 * Get all registered workspaces.
	 *
	 * @param bool $filter_by_condition Whether to filter workspaces by their condition callback.
	 * @return array All registered workspaces.
	 */
	public function get_all( $filter_by_condition = false ) {
		$workspaces = $this->workspaces;

		// Filter by condition if requested.
		if ( $filter_by_condition ) {
			$workspaces = array_filter( $workspaces, function( $workspace ) {
				// If no condition is set, include the workspace.
				if ( ! isset( $workspace['condition'] ) || null === $workspace['condition'] ) {
					return true;
				}

				// Check if condition is callable.
				if ( is_callable( $workspace['condition'] ) ) {
					return call_user_func( $workspace['condition'] );
				}

				return true;
			});
		}

		// Sort by order.
		uasort( $workspaces, function( $a, $b ) {
			return $a['order'] - $b['order'];
		});

		// Allow filtering of workspaces.
		return apply_filters( 'wp_workspaces_registered', $workspaces );
	}

	/**
	 * Check if a workspace is registered.
	 *
	 * @param string $id Workspace ID.
	 * @return bool True if registered, false otherwise.
	 */
	public function is_registered( $id ) {
		$id = sanitize_key( $id );
		return isset( $this->workspaces[ $id ] );
	}

	/**
	 * Unregister a workspace.
	 *
	 * @param string $id Workspace ID.
	 * @return bool True on success, false on failure.
	 */
	public function unregister( $id ) {
		$id = sanitize_key( $id );

		if ( ! $this->is_registered( $id ) ) {
			return false;
		}

		unset( $this->workspaces[ $id ] );
		return true;
	}

	/**
	 * Register default workspaces.
	 */
	public function register_default_workspaces() {
		// Default Workspace - shows everything.
		$this->register( 'all', array(
			'label'            => __( 'Default', 'wp-workspaces' ),
			'icon'             => 'dashicons-admin-generic',
			'sidebar_items'    => array(), // Empty means show all.
			'admin_bar_items'  => array(), // Empty means show all.
			'distraction_free' => false,
			'fallback'         => true,
			'order'            => 1,
		));

		// Write Workspace - content creation.
		$this->register( 'write', array(
			'label'            => __( 'Write', 'wp-workspaces' ),
			'icon'             => 'dashicons-edit',
			'sidebar_items'    => array(
				'edit.php',           // Posts
				'edit.php?post_type=page', // Pages
				'upload.php',         // Media
				'edit-comments.php',  // Comments
				'options-writing.php', // Settings > Writing
				'options-reading.php', // Settings > Reading
				'options-discussion.php', // Settings > Discussion
			),
			'admin_bar_items'  => array(
				'new-content',        // New Content group
				'new-post',
				'new-page',
				'new-media',
				'comments',           // Comments
				'search',             // Search
			),
			'distraction_free' => true,
			'order'            => 2,
		));

		// Design Workspace - appearance and themes.
		$this->register( 'design', array(
			'label'            => __( 'Design', 'wp-workspaces' ),
			'icon'             => 'dashicons-admin-appearance',
			'sidebar_items'    => array(
				'themes.php',         // Appearance
				'upload.php',         // Media
			),
			'admin_bar_items'  => array(
				'appearance',         // Appearance group
				'customize',
				'themes',
				'widgets',
				'menus',
				'background',
				'header',
				'new-media',          // New Media
			),
			'distraction_free' => true,
			'order'            => 3,
		));

		// Commerce Workspace - WooCommerce (conditional).
		$this->register( 'commerce', array(
			'label'            => __( 'Commerce', 'wp-workspaces' ),
			'icon'             => 'dashicons-cart',
			'sidebar_items'    => array(
				'woocommerce',
				'edit.php?post_type=product',
				'edit.php?post_type=shop_order',
				'edit.php?post_type=shop_coupon',
			),
			'admin_bar_items'  => array(
				'new-product',
				'woocommerce',        // WooCommerce menu group
				'wc-reports',         // Reports
				'wc-orders',          // Orders
				'wc-customers',       // Customers
			),
			'distraction_free' => false,
			'order'            => 4,
			'condition'        => array( $this, 'is_woocommerce_active' ),
		));

		// Manage Workspace - settings and administration.
		$this->register( 'manage', array(
			'label'            => __( 'Manage', 'wp-workspaces' ),
			'icon'             => 'dashicons-admin-tools',
			'sidebar_items'    => array(
				'users.php',          // Users
				'tools.php',          // Tools
				'options-general.php', // Settings
				'plugins.php',        // Plugins
				'update-core.php',    // Updates
			),
			'admin_bar_items'  => array(
				'new-user',
				'updates',
				'site-health',
				'debug-bar',          // Debug Bar (if installed)
				'query-monitor',      // Query Monitor (if installed)
			),
			'distraction_free' => false,
			'order'            => 5,
		));
	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool True if WooCommerce is active, false otherwise.
	 */
	public function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Scan for workspace.json files in plugin directories.
	 */
	public function scan_workspace_json_files() {
		// Get all active plugins.
		$active_plugins = get_option( 'active_plugins', array() );

		foreach ( $active_plugins as $plugin_file ) {
			$plugin_dir = WP_PLUGIN_DIR . '/' . dirname( $plugin_file );
			$json_file = $plugin_dir . '/workspace.json';

			// Check if workspace.json exists.
			if ( file_exists( $json_file ) ) {
				$this->load_workspace_json( $json_file );
			}
		}

		// Check multisite plugins if multisite is active.
		if ( is_multisite() ) {
			$network_plugins = get_site_option( 'active_sitewide_plugins', array() );

			foreach ( array_keys( $network_plugins ) as $plugin_file ) {
				$plugin_dir = WP_PLUGIN_DIR . '/' . dirname( $plugin_file );
				$json_file = $plugin_dir . '/workspace.json';

				if ( file_exists( $json_file ) ) {
					$this->load_workspace_json( $json_file );
				}
			}
		}
	}

	/**
	 * Load a workspace from a JSON file.
	 *
	 * @param string $json_file Path to the JSON file.
	 * @return bool True on success, false on failure.
	 */
	private function load_workspace_json( $json_file ) {
		// Read the JSON file.
		$json_content = file_get_contents( $json_file );

		if ( false === $json_content ) {
			return false;
		}

		// Decode JSON.
		$workspace_data = json_decode( $json_content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return false;
		}

		// Validate required fields.
		if ( empty( $workspace_data['id'] ) || empty( $workspace_data['label'] ) ) {
			return false;
		}

		// Extract workspace data.
		$id = $workspace_data['id'];
		$args = array(
			'label'            => $workspace_data['label'],
			'icon'             => isset( $workspace_data['icon'] ) ? $workspace_data['icon'] : 'dashicons-admin-generic',
			'sidebar_items'    => isset( $workspace_data['sidebar_items'] ) ? (array) $workspace_data['sidebar_items'] : array(),
			'admin_bar_items'  => isset( $workspace_data['admin_bar_items'] ) ? (array) $workspace_data['admin_bar_items'] : array(),
			'distraction_free' => isset( $workspace_data['distraction_free'] ) ? (bool) $workspace_data['distraction_free'] : false,
			'order'            => isset( $workspace_data['order'] ) ? (int) $workspace_data['order'] : 100,
		);

		// Register the workspace.
		return $this->register( $id, $args );
	}
}

