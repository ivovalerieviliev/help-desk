/**
 * Handover Report JavaScript
 *
 * Handles ticket search, add/remove functionality, and form validation
 * for the Create Handover Report page.
 */

(function($) {
    'use strict';

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
        title: 'Title',
        reporter: 'Reporter',
        category: 'Category',
        priority: 'Priority',
        created_at: 'Created',
        due_date: 'Due Date',
        special_instructions: 'Special Instructions'
    };

    $(document).ready(function() {
        initHandoverReport();
    });

    /**
     * Initialize handover report functionality
     */
    function initHandoverReport() {
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
            }, 300); // 300ms debounce
        });

        // Form submission validation
        $('#wphd-handover-report-form').on('submit', function(e) {
            const shiftType = $('#shift_type').val();
            const orgId = $('input[name="organization_id"]').val();
            
            if (!shiftType) {
                e.preventDefault();
                showNotification('Please select a shift type.', 'error');
                return false;
            }

            // Update hidden fields with ticket data before submission
            updateHiddenFields();

            // Check for duplicate report before submitting
            e.preventDefault();
            checkDuplicateReport(shiftType, orgId);
            return false;
        });

        // Cancel button - check for unsaved data using specific class
        $('.wphd-cancel-handover-btn').on('click', function(e) {
            const hasData = $('#shift_type').val() || 
                          ticketData.tasks_todo.length > 0 || 
                          ticketData.follow_up.length > 0 || 
                          ticketData.important_info.length > 0;

            if (hasData) {
                e.preventDefault();
                showCancelConfirmation($(this).attr('href'));
                return false;
            }
        });
    }

    /**
     * Show cancel confirmation modal
     */
    function showCancelConfirmation(cancelUrl) {
        // Create a simple confirmation using notification system
        const confirmHtml = '<div class="wphd-confirm-overlay">' +
            '<div class="wphd-confirm-dialog">' +
            '<h3>Confirm Cancellation</h3>' +
            '<p>Are you sure you want to cancel? Any unsaved data will be lost.</p>' +
            '<div class="wphd-confirm-actions">' +
            '<button class="button button-primary wphd-confirm-yes">Yes, Cancel</button>' +
            '<button class="button wphd-confirm-no">No, Continue Editing</button>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        $('body').append(confirmHtml);
        
        $('.wphd-confirm-yes').on('click', function() {
            window.location.href = cancelUrl;
        });
        
        $('.wphd-confirm-no, .wphd-confirm-overlay').on('click', function(e) {
            if (e.target === this) {
                $('.wphd-confirm-overlay').remove();
            }
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

        // Ensure handover nonce is available
        if (!wpHelpDesk.handoverNonce) {
            $('#wphd-ticket-search-results').html('<p class="error">Security token missing. Please refresh the page.</p>');
            return;
        }

        $.ajax({
            url: wpHelpDesk.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wphd_search_tickets_for_handover',
                nonce: wpHelpDesk.handoverNonce,
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
            // Check if ticket is already added to current section
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

        // Bind click events to add buttons
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
        updateHiddenFields();

        // Show success feedback
        showNotification('Ticket #' + ticket.id + ' added successfully!', 'success');
    }

    /**
     * Remove ticket from section
     */
    function removeTicketFromSection(section, ticketId) {
        // Show simple confirmation
        showRemoveConfirmation(function() {
            ticketData[section] = ticketData[section].filter(t => t.ticket_id !== ticketId);
            renderTicketList(section);
            updateHiddenFields();
            showNotification('Ticket removed successfully', 'success');
        });
    }

    /**
     * Show remove confirmation
     */
    function showRemoveConfirmation(callback) {
        const confirmHtml = '<div class="wphd-confirm-overlay">' +
            '<div class="wphd-confirm-dialog">' +
            '<h3>Confirm Removal</h3>' +
            '<p>Are you sure you want to remove this ticket from the section?</p>' +
            '<div class="wphd-confirm-actions">' +
            '<button class="button button-primary wphd-confirm-yes">Yes, Remove</button>' +
            '<button class="button wphd-confirm-no">Cancel</button>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        $('body').append(confirmHtml);
        
        $('.wphd-confirm-yes').on('click', function() {
            $('.wphd-confirm-overlay').remove();
            callback();
        });
        
        $('.wphd-confirm-no, .wphd-confirm-overlay').on('click', function(e) {
            if (e.target === this) {
                $('.wphd-confirm-overlay').remove();
            }
        });
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
                    // Make ticket ID clickable
                    html += '<td><a href="' + wpHelpDesk.adminUrl + 'admin.php?page=wp-helpdesk-tickets&ticket_id=' + ticket.ticket_id + '" target="_blank" class="wphd-ticket-link"><strong>#' + ticket.ticket_id + '</strong></a></td>';
                } else if (col === 'special_instructions') {
                    html += '<td>';
                    html += '<input type="text" class="regular-text wphd-special-instructions" ';
                    html += 'data-section="' + section + '" data-index="' + index + '" ';
                    html += 'value="' + escapeHtml(ticket.special_instructions || '') + '" ';
                    html += 'placeholder="Add special instructions...">';
                    html += '</td>';
                } else if (col === 'title') {
                    // Make ticket title clickable
                    html += '<td><a href="' + wpHelpDesk.adminUrl + 'admin.php?page=wp-helpdesk-tickets&ticket_id=' + ticket.ticket_id + '" target="_blank" class="wphd-ticket-link">' + escapeHtml(ticket.title) + '</a></td>';
                } else if (col === 'priority') {
                    html += '<td>' + escapeHtml(ticket.priority_label || ticket.priority || 'N/A') + '</td>';
                } else if (col === 'category') {
                    html += '<td>' + escapeHtml(ticket.category_label || ticket.category || 'N/A') + '</td>';
                } else {
                    html += '<td>' + escapeHtml(ticket[col] || 'N/A') + '</td>';
                }
            });

            html += '<td>';
            html += '<button type="button" class="button button-small wphd-remove-ticket" ';
            html += 'data-section="' + section + '" data-ticket-id="' + ticket.ticket_id + '">';
            html += '&times; Remove';
            html += '</button>';
            html += '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';

        listContainer.html(html);

        // Bind remove button events
        $('.wphd-remove-ticket').on('click', function() {
            const section = $(this).data('section');
            const ticketId = $(this).data('ticket-id');
            removeTicketFromSection(section, ticketId);
        });

        // Bind special instructions input events
        $('.wphd-special-instructions').on('change', function() {
            const section = $(this).data('section');
            const index = $(this).data('index');
            const value = $(this).val();
            ticketData[section][index].special_instructions = value;
            updateHiddenFields();
        });
    }

    /**
     * Update hidden fields with ticket data
     */
    function updateHiddenFields() {
        $('#tasks_todo_tickets').val(JSON.stringify(ticketData.tasks_todo));
        $('#follow_up_tickets').val(JSON.stringify(ticketData.follow_up));
        $('#important_info_tickets').val(JSON.stringify(ticketData.important_info));
    }

    /**
     * Show notification message
     */
    function showNotification(message, type) {
        const notification = $('<div class="wphd-notification ' + type + '">' + message + '</div>');
        $('body').append(notification);
        
        notification.fadeIn(300).delay(3000).fadeOut(300, function() {
            $(this).remove();
        });
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
    
    /**
     * Check for duplicate report before submitting
     */
    function checkDuplicateReport(shiftType, orgId) {
        if (!wpHelpDesk.createHandoverNonce) {
            // Show error - don't submit without nonce
            showNotification('Security token missing. Please refresh the page.', 'error');
            return;
        }

        $.ajax({
            url: wpHelpDesk.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wphd_check_duplicate_report',
                nonce: wpHelpDesk.createHandoverNonce,
                organization_id: orgId,
                shift_type: shiftType,
                shift_date: getCurrentDate()
            },
            success: function(response) {
                if (response.success && response.data.exists) {
                    // Show duplicate dialog
                    showDuplicateDialog(response.data, shiftType);
                } else {
                    // No duplicate, submit the form
                    $('#wphd-handover-report-form').off('submit').submit();
                }
            },
            error: function() {
                // On error, just submit the form
                $('#wphd-handover-report-form').off('submit').submit();
            }
        });
    }
    
    /**
     * Show duplicate report dialog
     */
    function showDuplicateDialog(reportData, shiftType) {
        const shiftLabels = {
            'morning': 'Morning',
            'afternoon': 'Afternoon',
            'night': 'Night'
        };
        
        const shiftLabel = shiftLabels[shiftType] || shiftType;
        const currentDate = getCurrentDateFormatted();
        
        const dialogHtml = '<div class="wphd-confirm-overlay">' +
            '<div class="wphd-duplicate-dialog">' +
            '<h3>Report Already Exists</h3>' +
            '<p>A handover report for the <strong>' + shiftLabel + '</strong> shift on <strong>' + currentDate + '</strong> has already been created for your organization.</p>' +
            '<div class="report-info">' +
            '<strong>Created by:</strong> ' + escapeHtml(reportData.created_by) + '<br>' +
            '<strong>Created at:</strong> ' + escapeHtml(reportData.created_at) +
            '</div>' +
            '<p>Would you like to update the existing report with the new content you\'ve added?</p>' +
            '<ul>' +
            '<li>New tickets will be added (duplicates ignored)</li>' +
            '<li>Additional instructions will be appended</li>' +
            '</ul>' +
            '<div class="wphd-confirm-actions">' +
            '<button class="button button-primary wphd-update-report-yes">Yes, Update Report</button>' +
            '<button class="button wphd-update-report-no">No, Cancel</button>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        $('body').append(dialogHtml);
        
        $('.wphd-update-report-yes').on('click', function() {
            $('.wphd-confirm-overlay').remove();
            // Submit the form - the backend will handle merging
            $('#wphd-handover-report-form').off('submit').submit();
        });
        
        $('.wphd-update-report-no, .wphd-confirm-overlay').on('click', function(e) {
            if (e.target === this) {
                $('.wphd-confirm-overlay').remove();
            }
        });
    }
    
    /**
     * Get current date in Y-m-d format
     */
    function getCurrentDate() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }
    
    /**
     * Get current date formatted for display
     */
    function getCurrentDateFormatted() {
        const now = new Date();
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        return now.toLocaleDateString('en-US', options);
    }

})(jQuery);
