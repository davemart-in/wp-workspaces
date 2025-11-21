<?php
/**
 * User Customization for WP Workspaces.
 *
 * @package WP_Workspaces
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User Customization class.
 */
class WP_Workspaces_User_Customization {

	/**
	 * Single instance of the class.
	 *
	 * @var WP_Workspaces_User_Customization
	 */
	private static $instance = null;

	/**
	 * Get instance of the class.
	 *
	 * @return WP_Workspaces_User_Customization
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
		// Add customize mode toggle to admin footer.
		add_action( 'admin_footer', array( $this, 'add_customize_toggle' ) );
		
		// Add hidden menu items in customize mode.
		add_action( 'admin_head', array( $this, 'add_customize_mode_items' ) );
		
		// AJAX handlers.
		add_action( 'wp_ajax_wp_workspaces_toggle_item', array( $this, 'ajax_toggle_item' ) );
		add_action( 'wp_ajax_wp_workspaces_reset_customizations', array( $this, 'ajax_reset_customizations' ) );
	}

	/**
	 * Add customize mode toggle button.
	 */
	public function add_customize_toggle() {
		$admin = WP_Workspaces_Admin::get_instance();
		$active_workspace = $admin->get_active_workspace();
		
		// Don't show in 'all' workspace.
		if ( 'all' === $active_workspace ) {
			return;
		}
		
		?>
		<div id="wp-workspaces-customize-toggle" class="wp-workspaces-customize-toggle">
			<button type="button" class="wp-workspaces-customize-button" aria-label="<?php esc_attr_e( 'Customize Workspace', 'wp-workspaces' ); ?>" title="<?php esc_attr_e( 'Customize this workspace', 'wp-workspaces' ); ?>">
				<span class="dashicons dashicons-admin-generic"></span>
			</button>
		</div>
		
		<div id="wp-workspaces-customize-panel" class="wp-workspaces-customize-panel" style="display: none;">
			<div class="customize-panel-header">
				<h3><?php esc_html_e( 'Customize Workspace', 'wp-workspaces' ); ?></h3>
				<button type="button" class="close-customize-panel">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
			<div class="customize-panel-content">
				<p class="description">
					<?php esc_html_e( 'Click the eye icons in the sidebar to show or hide menu items in this workspace. Your changes are saved automatically.', 'wp-workspaces' ); ?>
				</p>
				<div class="customize-panel-actions">
					<button type="button" class="button button-secondary wp-workspaces-reset-button">
						<?php esc_html_e( 'Reset to Defaults', 'wp-workspaces' ); ?>
					</button>
				</div>
			</div>
		</div>
		
		<div id="wp-workspaces-toast" class="wp-workspaces-toast" style="display: none;">
			<span class="toast-message"></span>
		</div>
		<?php
	}

	/**
	 * Add hidden menu items and eye icons in customize mode.
	 */
	public function add_customize_mode_items() {
		global $menu;
		
		$admin = WP_Workspaces_Admin::get_instance();
		$active_workspace_id = $admin->get_active_workspace();
		
		// Don't show in 'all' workspace.
		if ( 'all' === $active_workspace_id ) {
			return;
		}
		
		$registry = WP_Workspace_Registry::get_instance();
		$workspace = $registry->get( $active_workspace_id );
		
		if ( ! $workspace ) {
			return;
		}
		
		$sidebar_filter = WP_Workspaces_Sidebar_Filter::get_instance();
		
		// Get all menu items.
		$all_menus = array();
		foreach ( $menu as $key => $item ) {
			if ( false !== strpos( $item[2], 'separator' ) ) {
				continue;
			}
			$all_menus[ $item[2] ] = $item[0]; // slug => title
		}
		
		// Pass data to JavaScript.
		wp_localize_script(
			'wp-workspaces-admin',
			'wpWorkspacesCustomize',
			array(
				'allMenus'        => $all_menus,
				'activeWorkspace' => $active_workspace_id,
				'i18n'            => array(
					'addedToWorkspace'   => __( 'Added to workspace', 'wp-workspaces' ),
					'removedFromWorkspace' => __( 'Removed from workspace', 'wp-workspaces' ),
					'resetSuccess'       => __( 'Workspace reset to defaults', 'wp-workspaces' ),
					'resetConfirm'       => __( 'Are you sure you want to reset this workspace to its default settings? This cannot be undone.', 'wp-workspaces' ),
				),
			)
		);
	}

	/**
	 * AJAX handler for toggling menu items.
	 */
	public function ajax_toggle_item() {
		// Verify request.
		if ( ! isset( $_POST['menu_slug'] ) || ! isset( $_POST['workspace_id'] ) || ! isset( $_POST['action_type'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-workspaces' ) ) );
		}

		$menu_slug = sanitize_text_field( $_POST['menu_slug'] );
		$workspace_id = sanitize_key( $_POST['workspace_id'] );
		$action_type = sanitize_key( $_POST['action_type'] ); // 'add' or 'remove'
		
		$user_id = get_current_user_id();
		
		// Get current customizations.
		$customizations = get_user_meta( $user_id, 'wp_workspaces_customizations', true );
		if ( ! is_array( $customizations ) ) {
			$customizations = array();
		}
		
		if ( ! isset( $customizations[ $workspace_id ] ) ) {
			$customizations[ $workspace_id ] = array(
				'added'   => array(),
				'removed' => array(),
			);
		}
		
		// Toggle the item.
		if ( 'add' === $action_type ) {
			// Add to workspace (remove from removed list, add to added list).
			$customizations[ $workspace_id ]['removed'] = array_diff(
				$customizations[ $workspace_id ]['removed'],
				array( $menu_slug )
			);
			
			if ( ! in_array( $menu_slug, $customizations[ $workspace_id ]['added'], true ) ) {
				$customizations[ $workspace_id ]['added'][] = $menu_slug;
			}
			
			$message = __( 'Item added to workspace', 'wp-workspaces' );
		} else {
			// Remove from workspace (remove from added list, add to removed list).
			$customizations[ $workspace_id ]['added'] = array_diff(
				$customizations[ $workspace_id ]['added'],
				array( $menu_slug )
			);
			
			if ( ! in_array( $menu_slug, $customizations[ $workspace_id ]['removed'], true ) ) {
				$customizations[ $workspace_id ]['removed'][] = $menu_slug;
			}
			
			$message = __( 'Item removed from workspace', 'wp-workspaces' );
		}
		
		// Save customizations.
		update_user_meta( $user_id, 'wp_workspaces_customizations', $customizations );
		
		wp_send_json_success( array(
			'message'        => $message,
			'customizations' => $customizations[ $workspace_id ],
		) );
	}

	/**
	 * AJAX handler for resetting customizations.
	 */
	public function ajax_reset_customizations() {
		if ( ! isset( $_POST['workspace_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-workspaces' ) ) );
		}

		$workspace_id = sanitize_key( $_POST['workspace_id'] );
		$user_id = get_current_user_id();
		
		// Get current customizations.
		$customizations = get_user_meta( $user_id, 'wp_workspaces_customizations', true );
		if ( ! is_array( $customizations ) ) {
			$customizations = array();
		}
		
		// Remove customizations for this workspace.
		unset( $customizations[ $workspace_id ] );
		
		// Save updated customizations.
		update_user_meta( $user_id, 'wp_workspaces_customizations', $customizations );
		
		wp_send_json_success( array(
			'message' => __( 'Workspace reset to defaults', 'wp-workspaces' ),
		) );
	}

	/**
	 * Get user customizations for a workspace.
	 *
	 * @param string $workspace_id Workspace ID.
	 * @param int    $user_id User ID (optional, defaults to current user).
	 * @return array Array with 'added' and 'removed' keys.
	 */
	public function get_customizations( $workspace_id, $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}
		
		$customizations = get_user_meta( $user_id, 'wp_workspaces_customizations', true );
		
		if ( ! is_array( $customizations ) || ! isset( $customizations[ $workspace_id ] ) ) {
			return array(
				'added'   => array(),
				'removed' => array(),
			);
		}
		
		return $customizations[ $workspace_id ];
	}
}

