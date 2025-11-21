<?php
/**
 * Distraction Free Mode for WP Workspaces.
 *
 * @package WP_Workspaces
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Distraction Free Mode class.
 */
class WP_Workspaces_Distraction_Free {

	/**
	 * Single instance of the class.
	 *
	 * @var WP_Workspaces_Distraction_Free
	 */
	private static $instance = null;

	/**
	 * Critical notice types that should always be shown.
	 *
	 * @var array
	 */
	private $critical_notices = array(
		'error',
		'update-nag',
	);

	/**
	 * Critical notice sources that should always be shown.
	 *
	 * @var array
	 */
	private $critical_sources = array(
		'wordpress',
		'wp-core',
		'core',
	);

	/**
	 * Get instance of the class.
	 *
	 * @return WP_Workspaces_Distraction_Free
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
		// Check if we're in distraction-free mode.
		if ( $this->is_distraction_free_mode() ) {
			// Filter admin notices at early priority.
			add_action( 'admin_notices', array( $this, 'filter_admin_notices' ), -999 );
			add_action( 'network_admin_notices', array( $this, 'filter_admin_notices' ), -999 );
			add_action( 'user_admin_notices', array( $this, 'filter_admin_notices' ), -999 );
			add_action( 'all_admin_notices', array( $this, 'filter_admin_notices' ), -999 );
			
			// Add distraction-free CSS.
			add_action( 'admin_head', array( $this, 'add_distraction_free_css' ) );
		}
	}

	/**
	 * Check if current workspace has distraction-free mode enabled.
	 *
	 * @return bool True if in distraction-free mode, false otherwise.
	 */
	public function is_distraction_free_mode() {
		$admin = WP_Workspaces_Admin::get_instance();
		$active_workspace_id = $admin->get_active_workspace();
		
		$registry = WP_Workspace_Registry::get_instance();
		$workspace = $registry->get( $active_workspace_id );

		if ( ! $workspace ) {
			return false;
		}

		return ! empty( $workspace['distraction_free'] );
	}

	/**
	 * Filter admin notices to hide non-critical plugin notices.
	 */
	public function filter_admin_notices() {
		global $wp_filter;

		// Get all admin notice hooks.
		$notice_hooks = array(
			'admin_notices',
			'network_admin_notices',
			'user_admin_notices',
			'all_admin_notices',
		);

		foreach ( $notice_hooks as $hook ) {
			if ( ! isset( $wp_filter[ $hook ] ) ) {
				continue;
			}

			// Iterate through all registered callbacks.
			foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $key => $callback ) {
					// Skip our own filter.
					if ( is_array( $callback['function'] ) && $callback['function'][0] === $this ) {
						continue;
					}

					// Check if this notice should be hidden.
					if ( $this->should_hide_notice( $callback ) ) {
						// Remove the notice callback.
						remove_action( $hook, $callback['function'], $priority );
					}
				}
			}
		}
	}

	/**
	 * Determine if a notice callback should be hidden.
	 *
	 * @param array $callback The callback array.
	 * @return bool True if should be hidden, false if should be shown.
	 */
	private function should_hide_notice( $callback ) {
		// Get the callback function.
		$function = $callback['function'];

		// Check if it's a critical WordPress core notice.
		if ( $this->is_critical_notice( $function ) ) {
			return false;
		}

		// If it's a closure or complex callback, hide it (likely from plugins).
		if ( $function instanceof Closure ) {
			return true;
		}

		// If it's an array (class method).
		if ( is_array( $function ) ) {
			$class = is_object( $function[0] ) ? get_class( $function[0] ) : $function[0];
			
			// Check if it's from WordPress core.
			if ( $this->is_core_class( $class ) ) {
				return false;
			}

			// Check if it's a critical update notice.
			if ( $this->is_update_notice( $class, $function[1] ) ) {
				return false;
			}

			// Hide plugin notices.
			return true;
		}

		// If it's a string function name.
		if ( is_string( $function ) ) {
			// Check if it's a core function.
			if ( $this->is_core_function( $function ) ) {
				return false;
			}

			// Hide plugin function notices.
			return true;
		}

		// Default: hide it.
		return true;
	}

	/**
	 * Check if a notice is critical and should be shown.
	 *
	 * @param mixed $function The callback function.
	 * @return bool True if critical, false otherwise.
	 */
	private function is_critical_notice( $function ) {
		// Check for WordPress core update notices.
		if ( is_array( $function ) ) {
			$class = is_object( $function[0] ) ? get_class( $function[0] ) : $function[0];
			
			// Critical WordPress classes.
			$critical_classes = array(
				'WP_Admin_Notices',
				'WP_Automatic_Updater',
				'WP_Site_Health',
			);

			foreach ( $critical_classes as $critical_class ) {
				if ( false !== strpos( $class, $critical_class ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check if a class is from WordPress core.
	 *
	 * @param string $class Class name.
	 * @return bool True if core class, false otherwise.
	 */
	private function is_core_class( $class ) {
		// WordPress core classes typically start with 'WP_'.
		return 0 === strpos( $class, 'WP_' );
	}

	/**
	 * Check if a callback is an update notice.
	 *
	 * @param string $class Class name.
	 * @param string $method Method name.
	 * @return bool True if update notice, false otherwise.
	 */
	private function is_update_notice( $class, $method ) {
		$update_keywords = array( 'update', 'upgrade', 'version', 'core' );

		foreach ( $update_keywords as $keyword ) {
			if ( false !== stripos( $class, $keyword ) || false !== stripos( $method, $keyword ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a function is from WordPress core.
	 *
	 * @param string $function Function name.
	 * @return bool True if core function, false otherwise.
	 */
	private function is_core_function( $function ) {
		// WordPress core functions typically start with 'wp_' or '_wp_'.
		return 0 === strpos( $function, 'wp_' ) || 0 === strpos( $function, '_wp_' );
	}

	/**
	 * Add CSS for distraction-free mode.
	 */
	public function add_distraction_free_css() {
		?>
		<style id="wp-workspaces-distraction-free">
			/* Reduce visual clutter in distraction-free mode */
			body.wp-workspaces-distraction-free .update-nag,
			body.wp-workspaces-distraction-free .updated,
			body.wp-workspaces-distraction-free .notice:not(.notice-error) {
				/* Allow critical notices but reduce prominence */
			}
			
			/* Minimal footer */
			body.wp-workspaces-distraction-free #wpfooter {
				opacity: 0.5;
				transition: opacity 0.2s ease;
			}
			
			body.wp-workspaces-distraction-free #wpfooter:hover {
				opacity: 1;
			}
			
			/* Reduce screen options and help tabs prominence */
			body.wp-workspaces-distraction-free #screen-meta-links {
				opacity: 0.6;
			}
			
			/* Focus mode indicator */
			body.wp-workspaces-distraction-free::before {
				content: '';
				position: fixed;
				top: 0;
				left: 160px;
				right: 0;
				height: 2px;
				background: linear-gradient(90deg, 
					rgba(33, 150, 243, 0.5) 0%, 
					rgba(156, 39, 176, 0.5) 100%);
				z-index: 99999;
				pointer-events: none;
			}
			
			/* Adjust for folded menu */
			body.folded.wp-workspaces-distraction-free::before {
				left: 36px;
			}
			
			/* Mobile adjustments */
			@media screen and (max-width: 782px) {
				body.wp-workspaces-distraction-free::before {
					display: none;
				}
			}
		</style>
		<?php
	}

	/**
	 * Add distraction-free body class.
	 *
	 * @param string $classes Current body classes.
	 * @return string Modified body classes.
	 */
	public function add_body_class( $classes ) {
		if ( $this->is_distraction_free_mode() ) {
			$classes .= ' wp-workspaces-distraction-free';
		}
		return $classes;
	}
}

