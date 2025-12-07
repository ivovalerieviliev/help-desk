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
        }
    };

    $(document).ready(function() { 
        WPHD.init(); 
        WPHD.ActionItems.init();
    });

})(jQuery);