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
			
			// Debug: log the workspace ID
			console.log('Clicked workspace:', workspaceId, 'Link:', $link.attr('href'), 'Item ID:', $item.attr('id'));
			
			// Check if workspace ID was found
			if (!workspaceId) {
				console.error('No workspace ID found. Element:', $item);
				return;
			}
			
			// Don't switch if already active.
			if ($item.hasClass('active')) {
				console.log('Workspace already active');
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
					
					// Update the switcher label and icon.
					var workspace = response.data.workspace;
					$('#wp-admin-bar-wp-workspace-switcher .wp-workspace-icon')
						.attr('class', 'wp-workspace-icon ' + workspace.icon);
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
	 * Initialize customize mode functionality.
	 */
	function initCustomizeMode() {
		// Toggle customize mode.
		$('.wp-workspaces-customize-button').on('click', function(e) {
			e.preventDefault();
			toggleCustomizeMode();
		});
		
		// Close customize panel.
		$('.close-customize-panel').on('click', function(e) {
			e.preventDefault();
			closeCustomizeMode();
		});
		
		// Reset workspace customizations.
		$('.wp-workspaces-reset-button').on('click', function(e) {
			e.preventDefault();
			resetWorkspaceCustomizations();
		});
		
		// Add eye icons to menu items when in customize mode.
		$(document).on('customizeModeActive', function() {
			addEyeIconsToMenuItems();
		});
		
		// Handle eye icon clicks.
		$(document).on('click', '.wp-workspace-eye-icon', function(e) {
			e.preventDefault();
			e.stopPropagation();
			toggleMenuItem($(this));
		});
	}
	
	/**
	 * Toggle customize mode on/off.
	 */
	function toggleCustomizeMode() {
		var $panel = $('#wp-workspaces-customize-panel');
		var $toggle = $('.wp-workspaces-customize-button');
		
		if ($panel.is(':visible')) {
			closeCustomizeMode();
		} else {
			$panel.slideDown(200);
			$toggle.addClass('active');
			$('body').addClass('wp-workspaces-customize-mode');
			addEyeIconsToMenuItems();
		}
	}
	
	/**
	 * Close customize mode.
	 */
	function closeCustomizeMode() {
		var $panel = $('#wp-workspaces-customize-panel');
		var $toggle = $('.wp-workspaces-customize-button');
		
		$panel.slideUp(200);
		$toggle.removeClass('active');
		$('body').removeClass('wp-workspaces-customize-mode');
		removeEyeIconsFromMenuItems();
	}
	
	/**
	 * Add eye icons to all sidebar menu items.
	 */
	function addEyeIconsToMenuItems() {
		$('#adminmenu > li.menu-top').each(function() {
			var $menuItem = $(this);
			
			// Skip if already has eye icon.
			if ($menuItem.find('.wp-workspace-eye-icon').length > 0) {
				return;
			}
			
			// Skip separators.
			if ($menuItem.hasClass('wp-menu-separator')) {
				return;
			}
			
			var $link = $menuItem.find('> a');
			var isVisible = $menuItem.is(':visible');
			var iconClass = isVisible ? 'dashicons-visibility' : 'dashicons-hidden';
			var title = isVisible ? 'Hide from this workspace' : 'Show in this workspace';
			
			var $eyeIcon = $('<span class="wp-workspace-eye-icon" title="' + title + '"><span class="dashicons ' + iconClass + '"></span></span>');
			
			$link.append($eyeIcon);
		});
	}
	
	/**
	 * Remove eye icons from sidebar menu items.
	 */
	function removeEyeIconsFromMenuItems() {
		$('.wp-workspace-eye-icon').remove();
	}
	
	/**
	 * Toggle a menu item's visibility in the workspace.
	 */
	function toggleMenuItem($eyeIcon) {
		var $menuItem = $eyeIcon.closest('li.menu-top');
		var $link = $menuItem.find('> a');
		var href = $link.attr('href');
		var isVisible = $menuItem.is(':visible');
		var actionType = isVisible ? 'remove' : 'add';
		
		// Show loading state.
		$eyeIcon.addClass('loading');
		
		$.ajax({
			url: wpWorkspaces.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wp_workspaces_toggle_item',
				menu_slug: href,
				workspace_id: wpWorkspaces.activeWorkspace,
				action_type: actionType,
				nonce: wpWorkspaces.nonce
			},
			success: function(response) {
				if (response.success) {
					// Update the menu item visibility.
					if (actionType === 'remove') {
						$menuItem.hide();
						$eyeIcon.find('.dashicons')
							.removeClass('dashicons-visibility')
							.addClass('dashicons-hidden');
						$eyeIcon.attr('title', 'Show in this workspace');
					} else {
						$menuItem.show();
						$eyeIcon.find('.dashicons')
							.removeClass('dashicons-hidden')
							.addClass('dashicons-visibility');
						$eyeIcon.attr('title', 'Hide from this workspace');
					}
					
					// Show toast message.
					showToast(response.data.message);
				}
				
				$eyeIcon.removeClass('loading');
			},
			error: function() {
				$eyeIcon.removeClass('loading');
				showToast('Error updating menu item', 'error');
			}
		});
	}
	
	/**
	 * Reset workspace customizations to defaults.
	 */
	function resetWorkspaceCustomizations() {
		if (!confirm(wpWorkspacesCustomize.i18n.resetConfirm)) {
			return;
		}
		
		$.ajax({
			url: wpWorkspaces.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wp_workspaces_reset_customizations',
				workspace_id: wpWorkspaces.activeWorkspace,
				nonce: wpWorkspaces.nonce
			},
			success: function(response) {
				if (response.success) {
					showToast(response.data.message);
					
					// Reload to apply defaults.
					setTimeout(function() {
						location.reload();
					}, 1000);
				}
			},
			error: function() {
				showToast('Error resetting workspace', 'error');
			}
		});
	}
	
	/**
	 * Show a toast notification.
	 */
	function showToast(message, type) {
		var $toast = $('#wp-workspaces-toast');
		var $message = $toast.find('.toast-message');
		
		$message.text(message);
		$toast.removeClass('error success').addClass(type || 'success');
		$toast.fadeIn(200);
		
		setTimeout(function() {
			$toast.fadeOut(200);
		}, 3000);
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
		initCustomizeMode();
		positionWorkspaceSwitcher();
	});

})(jQuery);

