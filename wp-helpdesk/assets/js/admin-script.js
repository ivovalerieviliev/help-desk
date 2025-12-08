/**
 * WP Help Desk Admin JavaScript
 */

(function($) {
    'use strict';

    var WPHD = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#wphd-filter-status, #wphd-filter-priority').on('change', this.filterTickets);
            $('#wphd-search').on('keyup', this.debounce(this.filterTickets, 300));
            $('#wphd-change-status').on('change', this.updateTicketStatus);
            $('#wphd-change-assignee').on('change', this.updateTicketAssignee);
            $('#wphd-comment-form').on('submit', this.addComment);
            $('#wphd-add-status').on('click', this.addStatusRow);
            $('#wphd-add-category').on('click', this.addCategoryRow);
        },

        filterTickets: function() {
            var data = {
                action: 'wphd_filter_tickets',
                nonce: wpHelpDesk.nonce,
                status: $('#wphd-filter-status').val(),
                priority: $('#wphd-filter-priority').val(),
                search: $('#wphd-search').val()
            };
            $.post(wpHelpDesk.ajaxUrl, data, function(response) {
                if (response.success) {
                    $('#wphd-tickets-list').html(response.data.html);
                }
            });
        },

        updateTicketStatus: function() {
            var ticketId = $('.wphd-ticket-details').data('ticket-id');
            $.post(wpHelpDesk.ajaxUrl, {
                action: 'wphd_update_ticket',
                nonce: wpHelpDesk.nonce,
                ticket_id: ticketId,
                field: 'status',
                value: $(this).val()
            }, function(response) {
                if (response.success) {
                    WPHD.showNotice('Status updated', 'success');
                }
            });
        },

        updateTicketAssignee: function() {
            var ticketId = $('.wphd-ticket-details').data('ticket-id');
            $.post(wpHelpDesk.ajaxUrl, {
                action: 'wphd_update_ticket',
                nonce: wpHelpDesk.nonce,
                ticket_id: ticketId,
                field: 'assignee',
                value: $(this).val()
            }, function(response) {
                if (response.success) {
                    WPHD.showNotice('Assignee updated', 'success');
                }
            });
        },

        addComment: function(e) {
            e.preventDefault();
            var ticketId = $('.wphd-ticket-details').data('ticket-id');
            var content = $('#wphd-comment-content').val();
            var isInternal = $('#wphd-comment-internal').is(':checked') ? 1 : 0;
            $.post(wpHelpDesk.ajaxUrl, {
                action: 'wphd_add_comment',
                nonce: wpHelpDesk.nonce,
                ticket_id: ticketId,
                content: content,
                is_internal: isInternal
            }, function(response) {
                if (response.success) {
                    $('#wphd-comments-list').append(response.data.html);
                    $('#wphd-comment-content').val('');
                }
            });
        },

        addStatusRow: function() {
            var index = $('#wphd-statuses-table tbody tr').length;
            var html = '<tr><td><input type="text" name="statuses[' + index + '][name]"></td>';
            html += '<td><input type="color" name="statuses[' + index + '][color]" value="#999999"></td>';
            html += '<td><button class="button wphd-remove-status">Remove</button></td></tr>';
            $('#wphd-statuses-table tbody').append(html);
        },

        addCategoryRow: function() {
            var index = $('#wphd-categories-table tbody tr').length;
            var html = '<tr><td><input type="text" name="categories[' + index + '][name]"></td>';
            html += '<td><button class="button wphd-remove-category">Remove</button></td></tr>';
            $('#wphd-categories-table tbody').append(html);
        },

        showNotice: function(message, type) {
            var notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wphd-wrap h1').first().after(notice);
            setTimeout(function() { notice.fadeOut(); }, 3000);
        },

        debounce: function(func, wait) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(function() { func.apply(context, args); }, wait);
            };
        }
    };

    // Action Items Management
    WPHD.ActionItems = {
        init: function() {
            // Bind event listeners
            $(document).on('click', '.wphd-add-action-item', this.addItem);
            $(document).on('change', '.wphd-toggle-action-item', this.toggleItem);
            $(document).on('click', '.wphd-edit-action-item', this.editItem);
            $(document).on('click', '.wphd-delete-action-item', this.deleteItem);
            $(document).on('click', '.wphd-save-action-item', this.saveItem);
            $(document).on('click', '.wphd-cancel-edit-action-item', this.cancelEdit);
        },

        addItem: function(e) {
            e.preventDefault();
            var ticketId = $(this).data('ticket-id');
            var title = $('#wphd-new-action-item-title').val().trim();
            var assignedTo = $('#wphd-new-action-item-assignee').val();

            if (!title) {
                alert('Please enter an action item title');
                return;
            }

            $.post(wpHelpDesk.ajaxUrl, {
                action: 'wphd_add_action_item',
                nonce: wpHelpDesk.nonce,
                ticket_id: ticketId,
                title: title,
                assigned_to: assignedTo
            }, function(response) {
                if (response.success) {
                    WPHD.showNotice(response.data.message, 'success');
                    $('#wphd-new-action-item-title').val('');
                    $('#wphd-new-action-item-assignee').val('0');
                    WPHD.ActionItems.refreshList(ticketId);
                } else {
                    WPHD.showNotice(response.data.message, 'error');
                }
            });
        },

        toggleItem: function(e) {
            var $item = $(this).closest('.wphd-action-item');
            var itemId = $item.data('item-id');

            $.post(wpHelpDesk.ajaxUrl, {
                action: 'wphd_toggle_action_item',
                nonce: wpHelpDesk.nonce,
                item_id: itemId
            }, function(response) {
                if (response.success) {
                    if (response.data.is_completed) {
                        $item.addClass('completed');
                    } else {
                        $item.removeClass('completed');
                    }
                    WPHD.ActionItems.updateCounter();
                } else {
                    WPHD.showNotice(response.data.message, 'error');
                }
            });
        },

        editItem: function(e) {
            e.preventDefault();
            var $item = $(this).closest('.wphd-action-item');
            $item.find('.wphd-action-item-content').hide();
            $item.find('.wphd-action-item-actions').hide();
            $item.find('.wphd-action-item-edit-form').show();
        },

        saveItem: function(e) {
            e.preventDefault();
            var $item = $(this).closest('.wphd-action-item');
            var itemId = $item.data('item-id');
            var title = $item.find('.wphd-edit-action-item-title').val().trim();
            var assignedTo = $item.find('.wphd-edit-action-item-assignee').val();

            if (!title) {
                alert('Please enter an action item title');
                return;
            }

            $.post(wpHelpDesk.ajaxUrl, {
                action: 'wphd_update_action_item',
                nonce: wpHelpDesk.nonce,
                item_id: itemId,
                title: title,
                assigned_to: assignedTo
            }, function(response) {
                if (response.success) {
                    WPHD.showNotice(response.data.message, 'success');
                    // Get ticket ID from add button
                    var ticketId = $('.wphd-add-action-item').data('ticket-id');
                    WPHD.ActionItems.refreshList(ticketId);
                } else {
                    WPHD.showNotice(response.data.message, 'error');
                }
            });
        },

        deleteItem: function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to delete this action item?')) {
                return;
            }

            var $item = $(this).closest('.wphd-action-item');
            var itemId = $item.data('item-id');

            $.post(wpHelpDesk.ajaxUrl, {
                action: 'wphd_delete_action_item',
                nonce: wpHelpDesk.nonce,
                item_id: itemId
            }, function(response) {
                if (response.success) {
                    WPHD.showNotice(response.data.message, 'success');
                    $item.fadeOut(300, function() {
                        $(this).remove();
                        WPHD.ActionItems.updateCounter();
                    });
                } else {
                    WPHD.showNotice(response.data.message, 'error');
                }
            });
        },

        cancelEdit: function(e) {
            e.preventDefault();
            var $item = $(this).closest('.wphd-action-item');
            $item.find('.wphd-action-item-edit-form').hide();
            $item.find('.wphd-action-item-content').show();
            $item.find('.wphd-action-item-actions').show();
        },

        updateCounter: function() {
            var total = $('.wphd-action-item').length;
            var completed = $('.wphd-action-item.completed').length;
            $('.wphd-action-items-counter').text('(' + completed + '/' + total + ')');
        },

        refreshList: function(ticketId) {
            // Reload action items via AJAX
            $.post(wpHelpDesk.ajaxUrl, {
                action: 'wphd_get_action_items',
                nonce: wpHelpDesk.nonce,
                ticket_id: ticketId
            }, function(response) {
                if (response.success) {
                    // For simplicity, reload the page
                    // TODO: Implement dynamic list update without page reload
                    location.reload();
                }
            });
        },
        
        // Shifts Module
        Shifts: {
            orgId: null,
            
            init: function() {
                var self = this;
                var $container = $('#wphd-shifts-container');
                
                self.orgId = $container.data('org-id');
                self.bindEvents();
                self.fetchList();
            },
            
            bindEvents: function() {
                var self = this;
                
                $(document).on('click', '#wphd-add-shift-btn', function(e) {
                    e.preventDefault();
                    self.addShift();
                });
                
                $(document).on('click', '.wphd-edit-shift-btn', function(e) {
                    e.preventDefault();
                    var $row = $(this).closest('tr');
                    self.showEditForm($row);
                });
                
                $(document).on('click', '.wphd-save-shift-btn', function(e) {
                    e.preventDefault();
                    var $row = $(this).closest('tr');
                    self.updateShift($row);
                });
                
                $(document).on('click', '.wphd-cancel-edit-shift-btn', function(e) {
                    e.preventDefault();
                    var $row = $(this).closest('tr');
                    self.cancelEdit($row);
                });
                
                $(document).on('click', '.wphd-delete-shift-btn', function(e) {
                    e.preventDefault();
                    if (confirm(wpHelpDesk.i18n.confirm_delete || 'Are you sure you want to delete this shift?')) {
                        var shiftId = $(this).data('shift-id');
                        self.deleteShift(shiftId);
                    }
                });
            },
            
            fetchList: function() {
                var self = this;
                
                $.ajax({
                    url: wpHelpDesk.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wphd_get_shifts',
                        nonce: wpHelpDesk.nonce,
                        org_id: self.orgId
                    },
                    success: function(response) {
                        if (response.success) {
                            self.renderList(response.data.shifts);
                        } else {
                            self.showMessage(response.data.message || 'Failed to load shifts', 'error');
                        }
                    },
                    error: function() {
                        self.showMessage('Network error. Please try again.', 'error');
                    }
                });
            },
            
            renderList: function(shifts) {
                var self = this;
                var $list = $('#wphd-shifts-list');
                
                if (!shifts || shifts.length === 0) {
                    $list.html('<p>' + (wpHelpDesk.i18n.no_shifts || 'No shifts found.') + '</p>');
                    return;
                }
                
                var html = '<table class="wp-list-table widefat fixed striped">';
                html += '<thead><tr>';
                html += '<th>' + (wpHelpDesk.i18n.name || 'Name') + '</th>';
                html += '<th>' + (wpHelpDesk.i18n.start_time || 'Start Time') + '</th>';
                html += '<th>' + (wpHelpDesk.i18n.end_time || 'End Time') + '</th>';
                html += '<th>' + (wpHelpDesk.i18n.timezone || 'Timezone') + '</th>';
                html += '<th>' + (wpHelpDesk.i18n.actions || 'Actions') + '</th>';
                html += '</tr></thead><tbody>';
                
                $.each(shifts, function(i, shift) {
                    html += '<tr data-shift-id="' + shift.id + '">';
                    html += '<td class="shift-name">' + self.escapeHtml(shift.name) + '</td>';
                    html += '<td class="shift-start">' + self.escapeHtml(shift.start_time) + '</td>';
                    html += '<td class="shift-end">' + self.escapeHtml(shift.end_time) + '</td>';
                    html += '<td class="shift-timezone">' + self.escapeHtml(shift.timezone || 'UTC') + '</td>';
                    html += '<td class="shift-actions">';
                    
                    // Check if user can manage (button visibility is controlled server-side, but we add them here)
                    if ($('#wphd-add-shift-btn').length > 0) {
                        html += '<button type="button" class="button button-small wphd-edit-shift-btn" data-shift-id="' + shift.id + '">' + (wpHelpDesk.i18n.edit || 'Edit') + '</button> ';
                        html += '<button type="button" class="button button-small button-link-delete wphd-delete-shift-btn" data-shift-id="' + shift.id + '">' + (wpHelpDesk.i18n.delete || 'Delete') + '</button>';
                    }
                    
                    html += '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                $list.html(html);
            },
            
            addShift: function() {
                var self = this;
                var name = $('#wphd-shift-name').val().trim();
                var startTime = $('#wphd-shift-start').val();
                var endTime = $('#wphd-shift-end').val();
                var timezone = $('#wphd-shift-timezone').val();
                
                // Client-side validation
                if (!name || !startTime || !endTime) {
                    self.showMessage('Please fill in all required fields.', 'error');
                    return;
                }
                
                // Validate start < end
                if (startTime >= endTime) {
                    self.showMessage('Start time must be before end time.', 'error');
                    return;
                }
                
                var $btn = $('#wphd-add-shift-btn');
                $btn.prop('disabled', true).text(wpHelpDesk.i18n.saving || 'Saving...');
                
                $.ajax({
                    url: wpHelpDesk.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wphd_add_shift',
                        nonce: wpHelpDesk.nonce,
                        org_id: self.orgId,
                        name: name,
                        start_time: startTime,
                        end_time: endTime,
                        timezone: timezone
                    },
                    success: function(response) {
                        if (response.success) {
                            self.showMessage(response.data.message || 'Shift created successfully', 'success');
                            // Clear form
                            $('#wphd-shift-name').val('');
                            $('#wphd-shift-start').val('');
                            $('#wphd-shift-end').val('');
                            // Refresh list
                            self.fetchList();
                        } else {
                            self.showMessage(response.data.message || 'Failed to create shift', 'error');
                        }
                    },
                    error: function() {
                        self.showMessage('Network error. Please try again.', 'error');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text(wpHelpDesk.i18n.add_shift || 'Add Shift');
                    }
                });
            },
            
            showEditForm: function($row) {
                var shiftId = $row.data('shift-id');
                var name = $row.find('.shift-name').text();
                var startTime = $row.find('.shift-start').text();
                var endTime = $row.find('.shift-end').text();
                var timezone = $row.find('.shift-timezone').text();
                
                // Save original data
                $row.data('original', {
                    name: name,
                    start: startTime,
                    end: endTime,
                    tz: timezone
                });
                
                // Replace cells with inputs
                $row.find('.shift-name').html('<input type="text" class="edit-shift-name" value="' + this.escapeHtml(name) + '">');
                $row.find('.shift-start').html('<input type="time" class="edit-shift-start" value="' + this.escapeHtml(startTime) + '">');
                $row.find('.shift-end').html('<input type="time" class="edit-shift-end" value="' + this.escapeHtml(endTime) + '">');
                // Timezone select would be complex, so we'll keep it simple for now
                $row.find('.shift-timezone').html('<input type="text" class="edit-shift-tz" value="' + this.escapeHtml(timezone) + '" readonly>');
                
                // Replace action buttons
                var actionsHtml = '<button type="button" class="button button-small button-primary wphd-save-shift-btn" data-shift-id="' + shiftId + '">' + (wpHelpDesk.i18n.save || 'Save') + '</button> ';
                actionsHtml += '<button type="button" class="button button-small wphd-cancel-edit-shift-btn">' + (wpHelpDesk.i18n.cancel || 'Cancel') + '</button>';
                $row.find('.shift-actions').html(actionsHtml);
            },
            
            cancelEdit: function($row) {
                var original = $row.data('original');
                
                // Restore original values
                $row.find('.shift-name').text(original.name);
                $row.find('.shift-start').text(original.start);
                $row.find('.shift-end').text(original.end);
                $row.find('.shift-timezone').text(original.tz);
                
                // Restore action buttons
                var shiftId = $row.data('shift-id');
                var actionsHtml = '<button type="button" class="button button-small wphd-edit-shift-btn" data-shift-id="' + shiftId + '">' + (wpHelpDesk.i18n.edit || 'Edit') + '</button> ';
                actionsHtml += '<button type="button" class="button button-small button-link-delete wphd-delete-shift-btn" data-shift-id="' + shiftId + '">' + (wpHelpDesk.i18n.delete || 'Delete') + '</button>';
                $row.find('.shift-actions').html(actionsHtml);
            },
            
            updateShift: function($row) {
                var self = this;
                var shiftId = $row.data('shift-id');
                var name = $row.find('.edit-shift-name').val().trim();
                var startTime = $row.find('.edit-shift-start').val();
                var endTime = $row.find('.edit-shift-end').val();
                var timezone = $row.find('.edit-shift-tz').val();
                
                // Client-side validation
                if (!name || !startTime || !endTime) {
                    self.showMessage('Please fill in all required fields.', 'error');
                    return;
                }
                
                // Validate start < end
                if (startTime >= endTime) {
                    self.showMessage('Start time must be before end time.', 'error');
                    return;
                }
                
                var $btn = $row.find('.wphd-save-shift-btn');
                $btn.prop('disabled', true).text(wpHelpDesk.i18n.saving || 'Saving...');
                
                $.ajax({
                    url: wpHelpDesk.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wphd_update_shift',
                        nonce: wpHelpDesk.nonce,
                        shift_id: shiftId,
                        name: name,
                        start_time: startTime,
                        end_time: endTime,
                        timezone: timezone
                    },
                    success: function(response) {
                        if (response.success) {
                            self.showMessage(response.data.message || 'Shift updated successfully', 'success');
                            // Refresh list
                            self.fetchList();
                        } else {
                            self.showMessage(response.data.message || 'Failed to update shift', 'error');
                            $btn.prop('disabled', false).text(wpHelpDesk.i18n.save || 'Save');
                        }
                    },
                    error: function() {
                        self.showMessage('Network error. Please try again.', 'error');
                        $btn.prop('disabled', false).text(wpHelpDesk.i18n.save || 'Save');
                    }
                });
            },
            
            deleteShift: function(shiftId) {
                var self = this;
                
                $.ajax({
                    url: wpHelpDesk.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wphd_delete_shift',
                        nonce: wpHelpDesk.nonce,
                        shift_id: shiftId
                    },
                    success: function(response) {
                        if (response.success) {
                            self.showMessage(response.data.message || 'Shift deleted successfully', 'success');
                            // Refresh list
                            self.fetchList();
                        } else {
                            self.showMessage(response.data.message || 'Failed to delete shift', 'error');
                        }
                    },
                    error: function() {
                        self.showMessage('Network error. Please try again.', 'error');
                    }
                });
            },
            
            showMessage: function(message, type) {
                var $msg = $('.wphd-shift-message');
                $msg.removeClass('notice-success notice-error')
                    .addClass('notice notice-' + type)
                    .text(message)
                    .show();
                
                setTimeout(function() {
                    $msg.fadeOut();
                }, 5000);
            },
            
            escapeHtml: function(text) {
                var map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, function(m) { return map[m]; });
            }
        }
    };

    $(document).ready(function() {
        WPHD.init();
        WPHD.ActionItems.init();
        
        // Only initialize Shifts module if shifts container exists
        if ($('#wphd-shifts-container').length > 0) {
            WPHD.Shifts.init();
        }
    });

})(jQuery);