<?php
/**
 * Plugin Name: WP Workspaces
 * Plugin URI: https://github.com/yourusername/wp-workspaces
 * Description: Context-aware admin workspaces for WordPress that simplify navigation and reduce clutter.
 * Version: 0.1.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-workspaces
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package WP_Workspaces
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'WP_WORKSPACES_VERSION', '0.1.0' );
define( 'WP_WORKSPACES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_WORKSPACES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_WORKSPACES_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class.
 */
class WP_Workspaces {

	/**
	 * Single instance of the class.
	 *
	 * @var WP_Workspaces
	 */
	private static $instance = null;

	/**
	 * Get instance of the class.
	 *
	 * @return WP_Workspaces
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
		// Initialize admin functionality.
		if ( is_admin() ) {
			add_action( 'plugins_loaded', array( $this, 'load_admin' ) );
		}
	}

	/**
	 * Load admin functionality.
	 */
	public function load_admin() {
		require_once WP_WORKSPACES_PLUGIN_DIR . 'includes/class-workspace-registry.php';
		require_once WP_WORKSPACES_PLUGIN_DIR . 'includes/class-wp-workspaces-admin.php';
		require_once WP_WORKSPACES_PLUGIN_DIR . 'includes/class-sidebar-filter.php';
		
		WP_Workspace_Registry::get_instance();
		WP_Workspaces_Admin::get_instance();
		WP_Workspaces_Sidebar_Filter::get_instance();
	}
}

/**
 * Register a workspace.
 *
 * This is a helper function that wraps the registry's register method.
 *
 * @param string $id Workspace ID.
 * @param array  $args Workspace arguments {
 *     Optional. Array of workspace arguments.
 *
 *     @type string   $label            Workspace label (human-readable name).
 *     @type string   $icon             Dashicon class for the workspace.
 *     @type array    $sidebar_items    Array of sidebar menu slugs to show.
 *     @type array    $admin_bar_items  Array of admin bar node IDs to show.
 *     @type bool     $distraction_free Whether to enable distraction-free mode.
 *     @type bool     $fallback         Whether this is a fallback workspace.
 *     @type int      $order            Sort order for the workspace.
 *     @type callable $condition        Callback to determine if workspace should be shown.
 * }
 * @return bool True on success, false on failure.
 */
function register_admin_workspace( $id, $args = array() ) {
	$registry = WP_Workspace_Registry::get_instance();
	return $registry->register( $id, $args );
}

/**
 * Plugin activation hook.
 */
function wp_workspaces_activate() {
	// Set default user meta for admin users.
	$admin_users = get_users( array( 'role' => 'administrator' ) );
	foreach ( $admin_users as $user ) {
		// Set default workspace to 'all' if not already set.
		if ( ! get_user_meta( $user->ID, 'wp_workspaces_active', true ) ) {
			update_user_meta( $user->ID, 'wp_workspaces_active', 'all' );
		}
	}

	// Flush rewrite rules (in case we need them later).
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wp_workspaces_activate' );

/**
 * Plugin deactivation hook.
 */
function wp_workspaces_deactivate() {
	// Flush rewrite rules on deactivation.
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wp_workspaces_deactivate' );

/**
 * Initialize the plugin.
 */
function wp_workspaces_init() {
	return WP_Workspaces::get_instance();
}

// Start the plugin.
wp_workspaces_init();

