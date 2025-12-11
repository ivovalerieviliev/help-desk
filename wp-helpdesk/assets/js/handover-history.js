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

	/**
	 * Initialize edit page functionality
	 */
	window.initHandoverEditPage = function() {
		let currentSection = '';
		const ticketData = {
			tasks_todo: [],
			follow_up: [],
			important_info: []
		};

		// Column configurations for different sections
		const sectionColumns = {
			tasks_todo: ['id', 'title', 'reporter', 'category', 'created_at', 'due_date'],
			follow_up: ['id', 'title', 'reporter', 'category', 'priority', 'created_at'],
			important_info: ['id', 'title', 'reporter', 'priority', 'special_instructions']
		};

		// Column labels
		const columnLabels = {
			id: 'Ticket ID',
			title: 'Ticket Title',
			reporter: 'Reporter',
			category: 'Category',
			priority: 'Priority',
			created_at: 'Creation Date & Time',
			due_date: 'Due Date',
			special_instructions: 'Special Instructions'
		};

		// Load existing tickets from the page
		loadExistingTickets();

		// Add ticket button click
		$('.wphd-add-ticket-btn').on('click', function(e) {
			e.preventDefault();
			currentSection = $(this).data('section');
			openTicketSearchModal();
		});

		// Close modal
		$('.wphd-modal-close').on('click', function() {
			closeTicketSearchModal();
		});

		// Click outside modal to close
		$(window).on('click', function(e) {
			if ($(e.target).is('#wphd-ticket-search-modal')) {
				closeTicketSearchModal();
			}
		});

		// Ticket search input with debounce
		let searchTimeout;
		$('#wphd-ticket-search-input').on('keyup', function() {
			clearTimeout(searchTimeout);
			const searchQuery = $(this).val();

			if (searchQuery.length < 2) {
				$('#wphd-ticket-search-results').html('');
				return;
			}

			searchTimeout = setTimeout(function() {
				searchTickets(searchQuery);
			}, 300);
		});

		// Form submission
		$('#wphd-handover-edit-form').on('submit', function(e) {
			e.preventDefault();
			updateReport();
			return false;
		});

		// Remove ticket buttons - using delegated event handler
		$(document).on('click', '.wphd-remove-ticket-btn', function() {
			const section = $(this).data('section');
			const ticketId = $(this).data('ticket-id');
			removeTicketFromSection(section, ticketId);
		});

		// Special instructions input events
		$(document).on('change', '.wphd-special-instructions', function() {
			const section = $(this).closest('tr').find('.wphd-remove-ticket').data('section');
			const ticketId = $(this).closest('tr').data('ticket-id');
			const value = $(this).val();
			
			// Update the ticket data
			const ticket = ticketData[section].find(t => t.ticket_id === ticketId);
			if (ticket) {
				ticket.special_instructions = value;
			}
		});

		/**
		 * Load existing tickets from the page
		 */
		function loadExistingTickets() {
			['tasks_todo', 'follow_up', 'important_info'].forEach(function(section) {
				ticketData[section] = [];
				$('#' + section + '_list table tbody tr').each(function() {
					const ticketId = $(this).data('ticket-id');
					if (ticketId) {
						const ticket = {
							ticket_id: ticketId,
							special_instructions: $(this).find('.wphd-special-instructions').val() || ''
						};
						ticketData[section].push(ticket);
					}
				});
			});
		}

		/**
		 * Open the ticket search modal
		 */
		function openTicketSearchModal() {
			$('#wphd-ticket-search-modal').fadeIn();
			$('#wphd-ticket-search-input').val('').focus();
			$('#wphd-ticket-search-results').html('');
		}

		/**
		 * Close the ticket search modal
		 */
		function closeTicketSearchModal() {
			$('#wphd-ticket-search-modal').fadeOut();
			$('#wphd-ticket-search-input').val('');
			$('#wphd-ticket-search-results').html('');
		}

		/**
		 * Search for tickets via AJAX
		 */
		function searchTickets(query) {
			$('#wphd-ticket-search-results').html('<p class="wphd-loading">Searching...</p>');

			// Use handoverNonce if available, otherwise fall back to nonce
			const nonce = wpHelpDesk.handoverNonce || wpHelpDesk.nonce;
			if (!nonce) {
				$('#wphd-ticket-search-results').html('<p class="error">Security token missing. Please refresh the page.</p>');
				return;
			}

			$.ajax({
				url: wpHelpDesk.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wphd_search_tickets_for_handover',
					nonce: nonce,
					search: query
				},
				success: function(response) {
					if (response.success && response.data.tickets) {
						displaySearchResults(response.data.tickets);
					} else {
						$('#wphd-ticket-search-results').html('<p>No tickets found.</p>');
					}
				},
				error: function() {
					$('#wphd-ticket-search-results').html('<p class="error">Search failed. Please try again.</p>');
				}
			});
		}

		/**
		 * Display search results
		 */
		function displaySearchResults(tickets) {
			if (tickets.length === 0) {
				$('#wphd-ticket-search-results').html('<p>No tickets found.</p>');
				return;
			}

			let html = '<div class="wphd-search-results-list">';
			
			tickets.forEach(function(ticket) {
				const alreadyAdded = ticketData[currentSection].some(t => t.ticket_id === ticket.id);
				const disabledClass = alreadyAdded ? 'disabled' : '';
				const disabledAttr = alreadyAdded ? 'disabled' : '';

				html += '<div class="wphd-search-result-item ' + disabledClass + '">';
				html += '<div class="wphd-ticket-info">';
				html += '<strong>#' + ticket.id + ' - ' + escapeHtml(ticket.title) + '</strong><br>';
				html += '<span class="wphd-ticket-meta">';
				html += 'Status: ' + escapeHtml(ticket.status_label) + ' | ';
				html += 'Priority: ' + escapeHtml(ticket.priority_label) + ' | ';
				html += 'Reporter: ' + escapeHtml(ticket.reporter);
				html += '</span>';
				html += '</div>';
				html += '<button type="button" class="button button-small wphd-add-ticket-result" ';
				html += 'data-ticket-id="' + ticket.id + '" ' + disabledAttr + '>';
				html += alreadyAdded ? 'Added' : 'Add';
				html += '</button>';
				html += '</div>';
			});

			html += '</div>';

			$('#wphd-ticket-search-results').html(html);

			$('.wphd-add-ticket-result').on('click', function() {
				const ticketId = $(this).data('ticket-id');
				const ticket = tickets.find(t => t.id === ticketId);
				if (ticket) {
					addTicketToSection(ticket);
					$(this).prop('disabled', true).text('Added');
					$(this).closest('.wphd-search-result-item').addClass('disabled');
				}
			});
		}

		/**
		 * Add ticket to a section
		 */
		function addTicketToSection(ticket) {
			const ticketEntry = {
				ticket_id: ticket.id,
				title: ticket.title,
				status: ticket.status,
				status_label: ticket.status_label,
				priority: ticket.priority,
				priority_label: ticket.priority_label,
				category: ticket.category,
				category_label: ticket.category_label,
				reporter: ticket.reporter,
				created_at: ticket.created_at,
				due_date: ticket.due_date || '',
				special_instructions: ''
			};

			ticketData[currentSection].push(ticketEntry);
			renderTicketList(currentSection);
			showNotice('Ticket #' + ticket.id + ' added successfully!', 'success');
		}

		/**
		 * Remove ticket from section
		 */
		function removeTicketFromSection(section, ticketId) {
			if (confirm('Are you sure you want to remove this ticket from the section?')) {
				ticketData[section] = ticketData[section].filter(t => t.ticket_id !== ticketId);
				renderTicketList(section);
				showNotice('Ticket removed successfully', 'success');
			}
		}

		/**
		 * Render ticket list for a section
		 */
		function renderTicketList(section) {
			const listContainer = $('#' + section + '_list');
			const tickets = ticketData[section];
			const columns = sectionColumns[section];

			if (tickets.length === 0) {
				listContainer.html('<p class="description">No tickets added yet.</p>');
				return;
			}

			let html = '<table class="wp-list-table widefat fixed striped wphd-ticket-table">';
			html += '<thead><tr>';
			
			columns.forEach(function(col) {
				html += '<th>' + columnLabels[col] + '</th>';
			});
			
			html += '<th>Actions</th>';
			html += '</tr></thead>';
			html += '<tbody>';

			tickets.forEach(function(ticket, index) {
				html += '<tr data-ticket-id="' + ticket.ticket_id + '">';
				
				columns.forEach(function(col) {
					if (col === 'id') {
						html += '<td><strong>#' + ticket.ticket_id + '</strong></td>';
					} else if (col === 'special_instructions') {
						html += '<td>';
						html += '<input type="text" class="regular-text wphd-special-instructions" ';
						html += 'value="' + escapeHtml(ticket.special_instructions || '') + '" ';
						html += 'placeholder="Add special instructions...">';
						html += '</td>';
					} else if (col === 'title') {
						html += '<td>' + escapeHtml(ticket.title) + '</td>';
					} else if (col === 'priority') {
						html += '<td>' + escapeHtml(ticket.priority_label || ticket.priority || 'N/A') + '</td>';
					} else if (col === 'category') {
						html += '<td>' + escapeHtml(ticket.category_label || ticket.category || 'N/A') + '</td>';
					} else {
						html += '<td>' + escapeHtml(ticket[col] || 'N/A') + '</td>';
					}
				});

				html += '<td class="wphd-ticket-actions">';
				html += '<button type="button" class="button-link wphd-remove-ticket" ';
				html += 'data-section="' + section + '" data-ticket-id="' + ticket.ticket_id + '">';
				html += '<span class="dashicons dashicons-no-alt"></span>';
				html += '</button>';
				html += '</td>';
				html += '</tr>';
			});

			html += '</tbody></table>';

			listContainer.html(html);
		}

		/**
		 * Update report via AJAX
		 */
		function updateReport() {
			const reportId = $('#wphd-report-id').val();
			const shiftType = $('#wphd-shift-type').val();
			
			// Get additional instructions from editor
			let additionalInstructions = '';
			if (typeof tinyMCE !== 'undefined' && tinyMCE.get('wphd-additional-instructions')) {
				additionalInstructions = tinyMCE.get('wphd-additional-instructions').getContent();
			} else {
				additionalInstructions = $('#wphd-additional-instructions').val();
			}

			$.ajax({
				url: wpHelpDesk.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wphd_update_handover_report',
					nonce: wpHelpDesk.nonce,
					report_id: reportId,
					shift_type: shiftType,
					tickets_data: JSON.stringify(ticketData),
					additional_instructions: additionalInstructions
				},
				success: function(response) {
					if (response.success) {
						showNotice(response.data.message, 'success');
						setTimeout(function() {
							window.location.href = wpHelpDesk.adminUrl + 'admin.php?page=wp-helpdesk-handover-reports';
						}, 1500);
					} else {
						showNotice(response.data.message || 'Failed to update report', 'error');
					}
				},
				error: function() {
					showNotice('Network error. Please try again.', 'error');
				}
			});
		}

		/**
		 * Show notification message
		 */
		function showNotice(message, type) {
			const noticeClass = 'notice notice-' + type + ' is-dismissible';
			const notice = $('<div class="' + noticeClass + '"><p>' + message + '</p></div>');
			
			$('.wphd-handover-edit-wrap h1').after(notice);
			
			setTimeout(function() {
				notice.fadeOut(function() {
					$(this).remove();
				});
			}, 5000);
		}

		/**
		 * Escape HTML to prevent XSS
		 */
		function escapeHtml(text) {
			if (!text) return '';
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return text.replace(/[&<>"']/g, function(m) { return map[m]; });
		}
	};

})(jQuery);
