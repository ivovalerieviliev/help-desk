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
                nonce: wphd_ajax.nonce,
                status: $('#wphd-filter-status').val(),
                priority: $('#wphd-filter-priority').val(),
                search: $('#wphd-search').val()
            };
            $.post(wphd_ajax.ajax_url, data, function(response) {
                if (response.success) {
                    $('#wphd-tickets-list').html(response.data.html);
                }
            });
        },

        updateTicketStatus: function() {
            var ticketId = $('.wphd-ticket-details').data('ticket-id');
            $.post(wphd_ajax.ajax_url, {
                action: 'wphd_update_ticket',
                nonce: wphd_ajax.nonce,
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
            $.post(wphd_ajax.ajax_url, {
                action: 'wphd_update_ticket',
                nonce: wphd_ajax.nonce,
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
            $.post(wphd_ajax.ajax_url, {
                action: 'wphd_add_comment',
                nonce: wphd_ajax.nonce,
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

    $(document).ready(function() { WPHD.init(); });

})(jQuery);