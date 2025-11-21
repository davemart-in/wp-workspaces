<?php
/**
 * Admin functionality for WP Workspaces.
 *
 * @package WP_Workspaces
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class for WP Workspaces.
 */
class WP_Workspaces_Admin {

	/**
	 * Single instance of the class.
	 *
	 * @var WP_Workspaces_Admin
	 */
	private static $instance = null;

	/**
	 * Get instance of the class.
	 *
	 * @return WP_Workspaces_Admin
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
		// Enqueue admin scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Add admin body classes.
		add_filter( 'admin_body_class', array( $this, 'add_admin_body_class' ) );
	}

	/**
	 * Enqueue admin scripts and styles.
	 */
	public function enqueue_admin_assets() {
		// Enqueue CSS.
		wp_enqueue_style(
			'wp-workspaces-admin',
			WP_WORKSPACES_PLUGIN_URL . 'assets/css/workspaces-admin.css',
			array(),
			WP_WORKSPACES_VERSION
		);

		// Enqueue JavaScript.
		wp_enqueue_script(
			'wp-workspaces-admin',
			WP_WORKSPACES_PLUGIN_URL . 'assets/js/workspace-switcher.js',
			array( 'jquery' ),
			WP_WORKSPACES_VERSION,
			true
		);

		// Get available workspaces.
		$registry = WP_Workspace_Registry::get_instance();
		$workspaces = $registry->get_all( true ); // Filter by condition.

		// Prepare workspace data for JS.
		$workspace_data = array();
		foreach ( $workspaces as $id => $workspace ) {
			$workspace_data[ $id ] = array(
				'id'    => $workspace['id'],
				'label' => $workspace['label'],
				'icon'  => $workspace['icon'],
			);
		}

		// Pass data to JavaScript.
		wp_localize_script(
			'wp-workspaces-admin',
			'wpWorkspaces',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'currentUser'      => get_current_user_id(),
				'activeWorkspace'  => $this->get_active_workspace(),
				'workspaces'       => $workspace_data,
			)
		);
	}

	/**
	 * Add admin body class for current workspace.
	 *
	 * @param string $classes Current body classes.
	 * @return string Modified body classes.
	 */
	public function add_admin_body_class( $classes ) {
		$active_workspace = $this->get_active_workspace();
		$classes .= ' admin-workspace-' . esc_attr( $active_workspace );
		return $classes;
	}

	/**
	 * Get the active workspace for the current user.
	 *
	 * @return string Active workspace ID.
	 */
	public function get_active_workspace() {
		$user_id = get_current_user_id();
		$active_workspace = get_user_meta( $user_id, 'wp_workspaces_active', true );

		// Default to 'all' if no workspace is set.
		if ( empty( $active_workspace ) ) {
			$active_workspace = 'all';
			update_user_meta( $user_id, 'wp_workspaces_active', 'all' );
		}

		return $active_workspace;
	}

	/**
	 * Set the active workspace for the current user.
	 *
	 * @param string $workspace_id Workspace ID to set as active.
	 * @return bool True on success, false on failure.
	 */
	public function set_active_workspace( $workspace_id ) {
		$user_id = get_current_user_id();
		return update_user_meta( $user_id, 'wp_workspaces_active', sanitize_key( $workspace_id ) );
	}
}

