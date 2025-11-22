<?php
/**
 * Sidebar Filter for WP Workspaces.
 *
 * @package WP_Workspaces
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sidebar Filter class.
 */
class WP_Workspaces_Sidebar_Filter {

	/**
	 * Single instance of the class.
	 *
	 * @var WP_Workspaces_Sidebar_Filter
	 */
	private static $instance = null;

	/**
	 * Get instance of the class.
	 *
	 * @return WP_Workspaces_Sidebar_Filter
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
		// Filter admin menu at late priority to catch all registered menus.
		add_action( 'admin_menu', array( $this, 'filter_admin_menu' ), 999 );
		
		// Add inline CSS for hiding menu items.
		add_action( 'admin_head', array( $this, 'add_menu_hiding_css' ) );
		
		// Check for soft redirect when accessing hidden pages.
		add_action( 'admin_init', array( $this, 'check_soft_redirect' ) );
	}

	/**
	 * Filter the admin menu based on active workspace.
	 */
	public function filter_admin_menu() {
		global $menu, $submenu;

		$admin = WP_Workspaces_Admin::get_instance();
		$active_workspace_id = $admin->get_active_workspace();
		
		$registry = WP_Workspace_Registry::get_instance();
		$workspace = $registry->get( $active_workspace_id );

		// If no workspace or 'all' workspace, show everything.
		if ( ! $workspace || 'all' === $active_workspace_id || empty( $workspace['sidebar_items'] ) ) {
			return;
		}

		// Get allowed menu items for this workspace.
		$allowed_items = $workspace['sidebar_items'];
		$user_id = get_current_user_id();

		// Build a list of menu slugs to hide.
		$hidden_menus = array();

		foreach ( $menu as $key => $item ) {
			// Skip separators.
			if ( false !== strpos( $item[2], 'separator' ) ) {
				continue;
			}

			$menu_slug = $item[2];
			
			// Check if this menu should be visible.
			if ( ! $this->is_menu_allowed( $menu_slug, $allowed_items ) ) {
				$hidden_menus[] = $menu_slug;
			}
		}

		// Store hidden menus for CSS and soft redirect.
		set_transient( 'wp_workspaces_hidden_menus_' . $user_id, $hidden_menus, HOUR_IN_SECONDS );
	}

	/**
	 * Check if a menu item is allowed in the current workspace.
	 *
	 * @param string $menu_slug Menu slug to check.
	 * @param array  $allowed_items Array of allowed menu slugs.
	 * @return bool True if allowed, false if hidden.
	 */
	private function is_menu_allowed( $menu_slug, $allowed_items ) {
		// Direct match.
		if ( in_array( $menu_slug, $allowed_items, true ) ) {
			return true;
		}

		// Check for wildcard matches (e.g., 'edit.php' matches 'edit.php?post_type=page').
		foreach ( $allowed_items as $allowed ) {
			if ( 0 === strpos( $menu_slug, $allowed ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add inline CSS to hide filtered menu items based on workspace body classes.
	 * This allows instant switching without page reload.
	 */
	public function add_menu_hiding_css() {
		global $menu;
		
		$registry = WP_Workspace_Registry::get_instance();
		$workspaces = $registry->get_all( false ); // Get all workspaces, don't filter by condition.
		
		echo '<style id="wp-workspaces-menu-filter">';
		
		// Generate CSS rules for each workspace.
		foreach ( $workspaces as $workspace_id => $workspace ) {
			// Skip 'all' workspace - it shows everything.
			if ( 'all' === $workspace_id || empty( $workspace['sidebar_items'] ) ) {
				continue;
			}
			
			$allowed_items = $workspace['sidebar_items'];
			
			// Build CSS selectors for hidden menus in this workspace.
			foreach ( $menu as $key => $item ) {
				// Skip separators.
				if ( false !== strpos( $item[2], 'separator' ) ) {
					continue;
				}
				
				$menu_slug = $item[2];
				
				// Check if this menu should be hidden in this workspace.
				if ( ! $this->is_menu_allowed( $menu_slug, $allowed_items ) ) {
					$escaped_slug = esc_attr( $menu_slug );
					
					// Generate workspace-specific hiding rules.
					echo 'body.admin-workspace-' . esc_attr( $workspace_id ) . ' #adminmenu a[href="' . $escaped_slug . '"] { display: none !important; }';
					echo 'body.admin-workspace-' . esc_attr( $workspace_id ) . ' #adminmenu li.menu-top:has(> a[href="' . $escaped_slug . '"]) { display: none !important; }';
				}
			}
		}
		
		echo '</style>';
	}

	/**
	 * Check if current page is hidden and show soft redirect notice.
	 */
	public function check_soft_redirect() {
		// Only check on admin pages.
		if ( ! is_admin() || wp_doing_ajax() ) {
			return;
		}

		// Get active workspace - never show notice in 'all' (Default) workspace.
		$admin = WP_Workspaces_Admin::get_instance();
		$active_workspace_id = $admin->get_active_workspace();

		if ( 'all' === $active_workspace_id ) {
			return;
		}

		$user_id = get_current_user_id();
		$hidden_menus = get_transient( 'wp_workspaces_hidden_menus_' . $user_id );

		if ( empty( $hidden_menus ) || ! is_array( $hidden_menus ) ) {
			return;
		}

		// Get current page.
		global $pagenow;
		$current_page = $pagenow;
		
		// Add query string if present.
		if ( ! empty( $_GET ) ) {
			$query_string = http_build_query( $_GET );
			if ( $query_string ) {
				$current_page .= '?' . $query_string;
			}
		}

		// Check if current page is hidden.
		$is_hidden = false;
		foreach ( $hidden_menus as $hidden_slug ) {
			if ( 0 === strpos( $current_page, $hidden_slug ) || $current_page === $hidden_slug ) {
				$is_hidden = true;
				break;
			}
		}

		if ( $is_hidden ) {
			add_action( 'admin_notices', array( $this, 'show_soft_redirect_notice' ) );
		}
	}

	/**
	 * Show soft redirect notice for hidden pages.
	 */
	public function show_soft_redirect_notice() {
		$admin = WP_Workspaces_Admin::get_instance();
		$active_workspace_id = $admin->get_active_workspace();
		
		$registry = WP_Workspace_Registry::get_instance();
		$workspace = $registry->get( $active_workspace_id );
		
		$workspace_name = $workspace ? $workspace['label'] : $active_workspace_id;
		
		?>
		<div class="notice notice-warning wp-workspace-soft-redirect">
			<p>
				<strong><?php esc_html_e( 'This page is not available in your current workspace.', 'wp-workspaces' ); ?></strong>
			</p>
			<p>
				<?php
				printf(
					/* translators: %s: workspace name */
					esc_html__( 'You are currently in the "%s" workspace. This page is hidden to reduce clutter.', 'wp-workspaces' ),
					esc_html( $workspace_name )
				);
				?>
			</p>
			<p>
				<a href="#" class="button button-primary wp-workspace-switch-to-all" data-workspace="all">
					<?php esc_html_e( 'Switch to "Default" Workspace', 'wp-workspaces' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url() ); ?>" class="button">
					<?php esc_html_e( 'Go to Dashboard', 'wp-workspaces' ); ?>
				</a>
			</p>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			$('.wp-workspace-switch-to-all').on('click', function(e) {
				e.preventDefault();
				var workspaceId = $(this).data('workspace');
				
				// Use the existing switch function from workspace-switcher.js
				$.ajax({
					url: wpWorkspaces.ajaxUrl,
					type: 'POST',
					data: {
						action: 'wp_workspaces_switch',
						workspace_id: workspaceId,
						nonce: wpWorkspaces.nonce
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						}
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Get list of hidden menus for the current user.
	 *
	 * @return array List of hidden menu slugs.
	 */
	public function get_hidden_menus() {
		$user_id = get_current_user_id();
		$hidden_menus = get_transient( 'wp_workspaces_hidden_menus_' . $user_id );
		
		return is_array( $hidden_menus ) ? $hidden_menus : array();
	}
}

