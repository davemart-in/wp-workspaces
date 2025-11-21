<?php
/**
 * Admin Bar Filter for WP Workspaces.
 *
 * @package WP_Workspaces
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Bar Filter class.
 */
class WP_Workspaces_Admin_Bar {

	/**
	 * Single instance of the class.
	 *
	 * @var WP_Workspaces_Admin_Bar
	 */
	private static $instance = null;

	/**
	 * Essential admin bar items that should never be hidden.
	 *
	 * @var array
	 */
	private $essential_items = array(
		'my-account',
		'user-actions',
		'user-info',
		'edit-profile',
		'logout',
		'wp-logo',
		'about',
		'wporg',
		'documentation',
		'support-forums',
		'feedback',
		'site-name',
		'view-site',
	);

	/**
	 * Get instance of the class.
	 *
	 * @return WP_Workspaces_Admin_Bar
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
		// Filter admin bar nodes at late priority.
		add_action( 'admin_bar_menu', array( $this, 'filter_admin_bar_nodes' ), 999 );
	}

	/**
	 * Filter admin bar nodes based on active workspace.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function filter_admin_bar_nodes( $wp_admin_bar ) {
		$admin = WP_Workspaces_Admin::get_instance();
		$active_workspace_id = $admin->get_active_workspace();
		
		$registry = WP_Workspace_Registry::get_instance();
		$workspace = $registry->get( $active_workspace_id );

		// If no workspace or 'all' workspace, show everything.
		if ( ! $workspace || 'all' === $active_workspace_id || empty( $workspace['admin_bar_items'] ) ) {
			return;
		}

		// Get allowed admin bar items for this workspace.
		$allowed_items = $workspace['admin_bar_items'];
		
		// Get user customizations (will be implemented in Phase 7).
		$user_id = get_current_user_id();
		$user_customizations = get_user_meta( $user_id, 'wp_workspaces_customizations', true );
		
		if ( is_array( $user_customizations ) && isset( $user_customizations[ $active_workspace_id ] ) ) {
			if ( isset( $user_customizations[ $active_workspace_id ]['admin_bar_added'] ) ) {
				$allowed_items = array_merge( $allowed_items, $user_customizations[ $active_workspace_id ]['admin_bar_added'] );
			}
			if ( isset( $user_customizations[ $active_workspace_id ]['admin_bar_removed'] ) ) {
				$allowed_items = array_diff( $allowed_items, $user_customizations[ $active_workspace_id ]['admin_bar_removed'] );
			}
		}

		// Get all admin bar nodes.
		$all_nodes = $wp_admin_bar->get_nodes();
		
		// Remove nodes that aren't allowed.
		foreach ( $all_nodes as $node ) {
			// Skip essential items and the workspace switcher.
			if ( $this->is_essential_item( $node->id ) || 'wp-workspace-switcher' === $node->id || 0 === strpos( $node->id, 'wp-workspace-' ) ) {
				continue;
			}

			// Check if this node should be visible.
			if ( ! $this->is_admin_bar_item_allowed( $node->id, $allowed_items ) ) {
				$wp_admin_bar->remove_node( $node->id );
			}
		}
	}

	/**
	 * Check if an admin bar item is essential and should never be hidden.
	 *
	 * @param string $item_id Admin bar item ID.
	 * @return bool True if essential, false otherwise.
	 */
	private function is_essential_item( $item_id ) {
		return in_array( $item_id, $this->essential_items, true );
	}

	/**
	 * Check if an admin bar item is allowed in the current workspace.
	 *
	 * @param string $item_id Admin bar item ID.
	 * @param array  $allowed_items Array of allowed admin bar item IDs.
	 * @return bool True if allowed, false if hidden.
	 */
	private function is_admin_bar_item_allowed( $item_id, $allowed_items ) {
		// Direct match.
		if ( in_array( $item_id, $allowed_items, true ) ) {
			return true;
		}

		// Check for partial matches (e.g., 'new-' matches 'new-post', 'new-page').
		foreach ( $allowed_items as $allowed ) {
			// Check if the allowed item is a prefix.
			if ( 0 === strpos( $item_id, $allowed ) ) {
				return true;
			}
			
			// Check if the allowed item contains wildcards or is a parent.
			if ( 0 === strpos( $allowed, $item_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get list of essential admin bar items.
	 *
	 * @return array List of essential item IDs.
	 */
	public function get_essential_items() {
		return apply_filters( 'wp_workspaces_essential_admin_bar_items', $this->essential_items );
	}
}

