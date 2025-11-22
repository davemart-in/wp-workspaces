/**
 * WP Workspaces Admin JavaScript
 *
 * @package WP_Workspaces
 */

(function($) {
	'use strict';

	/**
	 * Initialize workspace functionality.
	 */
	function init() {
		// Handle workspace switching clicks.
		$('#wpadminbar').on('click', '#wp-admin-bar-wp-workspace-switcher .ab-submenu a', function(e) {
			e.preventDefault();
			
			var $link = $(this);
			var $item = $link.parent('li');
			
			// Try multiple methods to get workspace ID
			var workspaceId = null;
			
			// Method 1: From data attribute on li
			workspaceId = $item.data('workspace-id') || $item.data('workspace');
			
			// Method 2: From href fragment
			if (!workspaceId) {
				var href = $link.attr('href');
				if (href && href.indexOf('#workspace-') === 0) {
					workspaceId = href.replace('#workspace-', '');
				}
			}
			
			// Method 3: From li id attribute
			if (!workspaceId) {
				var itemId = $item.attr('id');
				if (itemId && itemId.indexOf('wp-admin-bar-wp-workspace-') === 0) {
					workspaceId = itemId.replace('wp-admin-bar-wp-workspace-', '');
				}
			}

			// Check if workspace ID was found
			if (!workspaceId) {
				console.error('No workspace ID found. Element:', $item);
				return;
			}

			// Don't switch if already active.
			if ($item.hasClass('active')) {
				return;
			}
			
			switchWorkspace(workspaceId);
		});
	}

	/**
	 * Switch to a different workspace.
	 *
	 * @param {string} workspaceId The workspace ID to switch to.
	 */
	function switchWorkspace(workspaceId) {
		// Show loading state.
		$('body').addClass('wp-workspace-switching');
		
		// Send AJAX request.
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
					// Update body class for instant CSS-based filtering.
					var bodyClasses = document.body.className;
					bodyClasses = bodyClasses.replace(/admin-workspace-\S+/g, '');
					document.body.className = bodyClasses + ' admin-workspace-' + workspaceId;
					
					// Update active state in menu.
					$('#wpadminbar .wp-workspace-item').removeClass('active');
					$('#wpadminbar .wp-workspace-item[data-workspace="' + workspaceId + '"]').addClass('active');

					// Update the switcher label (icon stays the same).
					var workspace = response.data.workspace;
					$('#wp-admin-bar-wp-workspace-switcher .wp-workspace-label')
						.text(workspace.label);
					
					// Remove loading state.
					$('body').removeClass('wp-workspace-switching');
					
					// Check if current page should be visible in new workspace.
					// If the current menu item is now hidden, we should show a notice or redirect.
					checkCurrentPageVisibility();
				} else {
					console.error('Workspace switch failed:', response.data.message);
					$('body').removeClass('wp-workspace-switching');
				}
			},
			error: function() {
				console.error('AJAX request failed');
				$('body').removeClass('wp-workspace-switching');
			}
		});
	}
	
	/**
	 * Check if the current page is visible in the active workspace.
	 * If not, reload to show the soft redirect notice.
	 */
	function checkCurrentPageVisibility() {
		// Get the current page's menu item.
		var $currentMenuItem = $('#adminmenu li.current, #adminmenu li.wp-has-current-submenu');
		
		// If current menu item is hidden by CSS, reload to show soft redirect notice.
		if ($currentMenuItem.length > 0 && $currentMenuItem.is(':hidden')) {
			location.reload();
		}
	}

	/**
	 * Position workspace switcher after WordPress logo.
	 */
	function positionWorkspaceSwitcher() {
		var $switcher = $('#wp-admin-bar-wp-workspace-switcher');
		var $wpLogo = $('#wp-admin-bar-wp-logo');
		
		if ($switcher.length && $wpLogo.length) {
			// Move switcher right after WordPress logo
			$switcher.insertAfter($wpLogo);
		}
	}

	// Initialize on document ready
	$(document).ready(function() {
		init();
		positionWorkspaceSwitcher();
	});

})(jQuery);

