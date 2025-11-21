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
		$('#wpadminbar').on('click', '.wp-workspace-item', function(e) {
			e.preventDefault();
			
			var $item = $(this);
			var workspaceId = $item.data('workspace');
			
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
				workspace_id: workspaceId
			},
			success: function(response) {
				if (response.success) {
					// Update body class.
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
					
					// Reload the page to apply sidebar filtering.
					location.reload();
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

	// Initialize on document ready
	$(document).ready(init);

})(jQuery);

