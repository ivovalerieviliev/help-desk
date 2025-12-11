/**
 * Tickets JavaScript
 *
 * Handles ticket-related functionality including adding tickets to handover reports.
 *
 * @package WP_HelpDesk
 * @since 1.0.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        initTicketHandover();
    });

    /**
     * Initialize ticket handover functionality
     */
    function initTicketHandover() {
        // Add to handover button
        $('#wphd-add-to-handover-btn').on('click', function() {
            addTicketToHandover();
        });
    }

    /**
     * Add ticket to handover report
     */
    function addTicketToHandover() {
        const button = $('#wphd-add-to-handover-btn');
        const ticketId = button.data('ticket-id');
        const orgId = button.data('org-id');
        const specialInstructions = $('#wphd-handover-instructions').val();
        
        // Get selected sections
        const sections = [];
        $('.wphd-handover-section-checkbox:checked').each(function() {
            sections.push($(this).val());
        });
        
        if (sections.length === 0) {
            showNotice('error', 'Please select at least one handover section.');
            return;
        }
        
        // Disable button and show loading
        const originalText = button.html();
        button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Adding...');
        
        $.ajax({
            url: wpHelpDesk.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wphd_add_ticket_to_handover',
                nonce: wpHelpDesk.nonce,
                ticket_id: ticketId,
                org_id: orgId,
                sections: sections,
                special_instructions: specialInstructions
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    
                    // Clear form
                    $('.wphd-handover-section-checkbox').prop('checked', false);
                    $('#wphd-handover-instructions').val('');
                    
                    // Reload the page to show updated assignments
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotice('error', response.data.message || 'Failed to add ticket to handover.');
                }
                
                button.prop('disabled', false).html(originalText);
            },
            error: function() {
                showNotice('error', 'Network error. Please try again.');
                button.prop('disabled', false).html(originalText);
            }
        });
    }

    /**
     * Show notice message
     */
    function showNotice(type, message) {
        const noticeClass = 'notice notice-' + type + ' is-dismissible';
        const notice = $('<div class="' + noticeClass + '"><p>' + escapeHtml(message) + '</p></div>');
        
        // Find a suitable place to insert the notice
        if ($('.wrap > h1').length) {
            $('.wrap > h1').first().after(notice);
        } else {
            $('.wrap').prepend(notice);
        }
        
        // Auto-dismiss after 5 seconds
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

    // Add spinning animation for loading state
    if (!document.getElementById('wphd-tickets-spin-style')) {
        const style = document.createElement('style');
        style.id = 'wphd-tickets-spin-style';
        style.textContent = `
            .dashicons.spin {
                animation: wphd-spin 1s linear infinite;
            }
            @keyframes wphd-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    }

})(jQuery);
