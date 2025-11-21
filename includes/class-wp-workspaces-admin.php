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

		// Add workspace switcher to admin bar.
		add_action( 'admin_bar_menu', array( $this, 'add_workspace_switcher' ), 5 );

		// AJAX handler for switching workspaces.
		add_action( 'wp_ajax_wp_workspaces_switch', array( $this, 'ajax_switch_workspace' ) );
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
				'nonce'            => wp_create_nonce( 'wp_workspaces_nonce' ),
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
		
		// Add distraction-free class if applicable.
		$distraction_free = WP_Workspaces_Distraction_Free::get_instance();
		$classes = $distraction_free->add_body_class( $classes );
		
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

	/**
	 * Add workspace switcher to admin bar.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function add_workspace_switcher( $wp_admin_bar ) {
		$registry = WP_Workspace_Registry::get_instance();
		$workspaces = $registry->get_all( true ); // Filter by condition.
		$active_workspace = $this->get_active_workspace();

		// Get active workspace data.
		$active = $registry->get( $active_workspace );
		if ( ! $active ) {
			$active = $registry->get( 'all' );
		}

		// Add parent menu item (the switcher pill) - positioned after wp-logo.
		$wp_admin_bar->add_node( array(
			'id'     => 'wp-workspace-switcher',
			'parent' => false,
			'title'  => '<span class="wp-workspace-icon dashicons ' . esc_attr( $active['icon'] ) . '"></span> <span class="wp-workspace-label">' . esc_html( $active['label'] ) . '</span>',
			'href'   => '#',
			'meta'   => array(
				'class' => 'wp-workspace-switcher-parent',
			),
		));

		// Add each workspace as a submenu item.
		foreach ( $workspaces as $workspace_id => $workspace ) {
			$is_active = ( $workspace_id === $active_workspace );

			$wp_admin_bar->add_node( array(
				'parent' => 'wp-workspace-switcher',
				'id'     => 'wp-workspace-' . $workspace_id,
				'title'  => '<span class="dashicons ' . esc_attr( $workspace['icon'] ) . '"></span> <span class="wp-workspace-label-text">' . esc_html( $workspace['label'] ) . '</span>',
				'href'   => '#workspace-' . esc_attr( $workspace_id ),
				'meta'   => array(
					'class'    => 'wp-workspace-item' . ( $is_active ? ' active' : '' ),
					'onclick'  => 'return false;',
					'data-workspace-id' => $workspace_id,
				),
			));
		}
	}

	/**
	 * AJAX handler for switching workspaces.
	 */
	public function ajax_switch_workspace() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wp_workspaces_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-workspaces' ) ) );
		}

		// Check if workspace ID is provided.
		if ( ! isset( $_POST['workspace_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Workspace ID not provided.', 'wp-workspaces' ) ) );
		}

		$workspace_id = sanitize_key( $_POST['workspace_id'] );
		$registry = WP_Workspace_Registry::get_instance();

		// Verify workspace exists.
		if ( ! $registry->is_registered( $workspace_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid workspace.', 'wp-workspaces' ) ) );
		}

		// Set the active workspace.
		$this->set_active_workspace( $workspace_id );

		// Return success with workspace data.
		$workspace = $registry->get( $workspace_id );
		wp_send_json_success( array(
			'workspace_id' => $workspace_id,
			'workspace'    => $workspace,
			'message'      => sprintf( __( 'Switched to %s workspace', 'wp-workspaces' ), $workspace['label'] ),
		));
	}
}

