/**
 * Handover History JavaScript
 *
 * Handles filter interactions, modal popup, and export functionality.
 *
 * @package WP_HelpDesk
 * @since 1.0.0
 */

(function($) {
	'use strict';

	let activeFilter = null;

	/**
	 * Initialize on document ready.
	 */
	$(document).ready(function() {
		initFilterButtons();
		initModal();
		initExportButtons();
		initSearchBar();
		initViewEditButtons();
	});

	/**
	 * Initialize filter buttons.
	 */
	function initFilterButtons() {
		// Quick filter buttons
		$('.wphd-filter-btn').on('click', function() {
			const filterType = $(this).data('filter');
			
			// Toggle active state
			$('.wphd-filter-btn').removeClass('active');
			$(this).addClass('active');
			
			activeFilter = filterType;
			
			// Show/hide custom date range
			if (filterType === 'custom') {
				$('.wphd-custom-date-range').slideDown();
			} else {
				$('.wphd-custom-date-range').slideUp();
			}
		});

		// Apply filter button
		$('.wphd-apply-filter').on('click', function() {
			applyFilter();
		});
	}

	/**
	 * Apply selected filter.
	 */
	function applyFilter() {
		if (!activeFilter) {
			showNotice('error', 'Please select a filter type.');
			return;
		}

		const data = {
			action: 'wphd_filter_handover_reports',
			nonce: wpHelpDesk.nonce,
			filter_type: activeFilter
		};

		// Add custom date range data if applicable
		if (activeFilter === 'custom') {
			const startDate = $('#wphd-start-date').val();
			const startTime = $('#wphd-start-time').val();
			const endDate = $('#wphd-end-date').val();
			const endTime = $('#wphd-end-time').val();

			if (!startDate || !endDate) {
				showNotice('error', 'Please select both start and end dates.');
				return;
			}

			// Validate that end date is after start date
			const startDateTime = new Date(startDate + ' ' + startTime);
			const endDateTime = new Date(endDate + ' ' + endTime);

			if (endDateTime < startDateTime) {
				showNotice('error', 'End date must be after start date.');
				return;
			}

			data.start_date = startDate;
			data.start_time = startTime;
			data.end_date = endDate;
			data.end_time = endTime;
		}

		// Show loading state
		$('#wphd-reports-table-container').html('<p>Loading...</p>');

		// Make AJAX request
		$.ajax({
			url: wpHelpDesk.ajaxUrl,
			type: 'POST',
			data: data,
			success: function(response) {
				if (response.success) {
					$('#wphd-reports-table-container').html(response.data.html);
					// Reinitialize event handlers for new content
					initViewInstructions();
					initExportButtons();
				} else {
					showNotice('error', response.data.message || 'Failed to filter reports.');
				}
			},
			error: function() {
				showNotice('error', 'An error occurred. Please try again.');
			}
		});
	}

	/**
	 * Initialize modal popup.
	 */
	function initModal() {
		initViewInstructions();

		// Close modal on X button click
		$(document).on('click', '.wphd-modal-close, .wphd-close-modal-btn', function() {
			$('#wphd-instructions-modal').fadeOut();
		});

		// Close modal on overlay click
		$(document).on('click', '#wphd-instructions-modal', function(e) {
			if ($(e.target).hasClass('wphd-modal')) {
				$(this).fadeOut();
			}
		});

		// Close modal on Escape key
		$(document).on('keydown', function(e) {
			if (e.key === 'Escape' && $('#wphd-instructions-modal').is(':visible')) {
				$('#wphd-instructions-modal').fadeOut();
			}
		});
	}

	/**
	 * Initialize view instructions links.
	 */
	function initViewInstructions() {
		$(document).on('click', '.wphd-view-instructions', function(e) {
			e.preventDefault();
			const reportId = $(this).data('report-id');
			
			// Show loading state
			$('#wphd-instructions-content').html('<p class="wphd-loading">Loading...</p>');
			$('#wphd-instructions-modal').fadeIn();
			
			// Fetch full instructions via AJAX
			$.ajax({
				url: wpHelpDesk.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wphd_get_report_instructions',
					nonce: wpHelpDesk.nonce,
					report_id: reportId
				},
				success: function(response) {
					if (response.success) {
						const instructions = response.data.instructions || '<p><em>No additional instructions.</em></p>';
						$('#wphd-instructions-content').html(instructions);
					} else {
						$('#wphd-instructions-content').html('<p class="error">' + (response.data.message || 'Failed to load instructions.') + '</p>');
					}
				},
				error: function() {
					$('#wphd-instructions-content').html('<p class="error">An error occurred while loading instructions.</p>');
				}
			});
		});
	}

	/**
	 * Initialize export buttons.
	 */
	function initExportButtons() {
		// Excel export
		$(document).on('click', '.wphd-export-excel', function() {
			const button = $(this);
			const reportId = button.data('report-id');
			const originalText = button.html();
			
			button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + wpHelpDesk.i18n.exporting);
			
			exportReport(reportId, 'excel', function(success, fileUrl) {
				button.prop('disabled', false).html(originalText);
				
				if (success && fileUrl) {
					// Trigger download
					window.location.href = fileUrl;
					showNotice('success', 'Excel file generated successfully.');
				} else {
					showNotice('error', 'Failed to generate Excel file.');
				}
			});
		});

		// PDF export
		$(document).on('click', '.wphd-export-pdf', function() {
			const button = $(this);
			const reportId = button.data('report-id');
			const originalText = button.html();
			
			button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + wpHelpDesk.i18n.exporting);
			
			exportReport(reportId, 'pdf', function(success, fileUrl) {
				button.prop('disabled', false).html(originalText);
				
				if (success && fileUrl) {
					// Open in new tab (can be printed to PDF)
					window.open(fileUrl, '_blank');
					showNotice('success', 'PDF file generated successfully.');
				} else {
					showNotice('error', 'Failed to generate PDF file.');
				}
			});
		});
	}

	/**
	 * Export report.
	 *
	 * @param {number} reportId Report ID.
	 * @param {string} format Export format (excel or pdf).
	 * @param {function} callback Callback function.
	 */
	function exportReport(reportId, format, callback) {
		const action = format === 'excel' ? 'wphd_export_handover_excel' : 'wphd_export_handover_pdf';
		
		$.ajax({
			url: wpHelpDesk.ajaxUrl,
			type: 'POST',
			data: {
				action: action,
				nonce: wpHelpDesk.nonce,
				report_id: reportId
			},
			success: function(response) {
				if (response.success) {
					callback(true, response.data.file_url);
				} else {
					callback(false, null);
				}
			},
			error: function() {
				callback(false, null);
			}
		});
	}

	/**
	 * Show admin notice.
	 *
	 * @param {string} type Notice type (success, error, warning, info).
	 * @param {string} message Notice message.
	 */
	function showNotice(type, message) {
		const noticeClass = 'notice notice-' + type + ' is-dismissible';
		const notice = $('<div class="' + noticeClass + '"><p>' + message + '</p></div>');
		
		$('.wphd-handover-history-wrap h1').after(notice);
		
		// Auto-dismiss after 5 seconds
		setTimeout(function() {
			notice.fadeOut(function() {
				$(this).remove();
			});
		}, 5000);
	}

	// Add spinning animation for loading state
	const style = document.createElement('style');
	style.textContent = `
		.dashicons.spin {
			animation: wphd-spin 1s linear infinite;
		}
	`;
	document.head.appendChild(style);
	
	/**
	 * Initialize search bar
	 */
	function initSearchBar() {
		let searchTimeout;
		
		$('#wphd-handover-search-input').on('keyup', function() {
			const searchTerm = $(this).val();
			
			// Clear previous timeout
			clearTimeout(searchTimeout);
			
			// If search is less than 2 characters, don't search
			if (searchTerm.length > 0 && searchTerm.length < 2) {
				return;
			}
			
			// If search is empty, reload with current filter
			if (searchTerm.length === 0) {
				if (activeFilter) {
					applyFilter();
				}
				return;
			}
			
			// Debounce search - wait 300ms after user stops typing
			searchTimeout = setTimeout(function() {
				performSearch(searchTerm);
			}, 300);
		});
	}
	
	/**
	 * Perform search
	 */
	function performSearch(searchTerm) {
		// Limit search term length to prevent DoS
		if (searchTerm.length > 200) {
			showNotice('error', 'Search term is too long. Please use fewer than 200 characters.');
			return;
		}
		
		// Show loading state
		$('#wphd-reports-table-container').html('<p class="wphd-loading">Searching...</p>');
		
		$.ajax({
			url: wpHelpDesk.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wphd_search_handover_reports',
				nonce: wpHelpDesk.nonce,
				search_term: searchTerm
			},
			success: function(response) {
				if (response.success) {
					$('#wphd-reports-table-container').html(response.data.html);
					// Reinitialize event handlers for new content
					initViewInstructions();
					initExportButtons();
					initViewEditButtons();
				} else {
					showNotice('error', response.data.message || 'Search failed.');
				}
			},
			error: function() {
				showNotice('error', 'An error occurred during search.');
			}
		});
	}
	
	/**
	 * Initialize View and Edit buttons
	 */
	function initViewEditButtons() {
		// View button
		$(document).on('click', '.wphd-view-btn', function() {
			const reportId = $(this).data('report-id');
			window.location.href = wpHelpDesk.adminUrl + 'admin.php?page=wp-helpdesk-handover-view&report_id=' + reportId;
		});
		
		// Edit button
		$(document).on('click', '.wphd-edit-btn', function() {
			const reportId = $(this).data('report-id');
			window.location.href = wpHelpDesk.adminUrl + 'admin.php?page=wp-helpdesk-handover-edit&report_id=' + reportId;
		});
	}

})(jQuery);
