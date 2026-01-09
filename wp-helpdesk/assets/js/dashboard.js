/**
 * WP Help Desk Dashboard JavaScript
 * 
 * Handles real-time updates and interactions on the dashboard page.
 */

(function($) {
    'use strict';

    var WPHD_Dashboard = {
        refreshInterval: null,
        refreshDelay: 30000, // 30 seconds

        /**
         * Initialize dashboard functionality
         */
        init: function() {
            this.setupAutoRefresh();
            this.setupTabSwitching();
        },

        /**
         * Setup auto-refresh for Take Action section
         */
        setupAutoRefresh: function() {
            var self = this;
            
            // Start auto-refresh
            this.refreshInterval = setInterval(function() {
                self.refreshUrgentTickets();
            }, this.refreshDelay);

            // Refresh on visibility change (when tab becomes visible)
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    self.refreshUrgentTickets();
                }
            });
        },

        /**
         * Refresh urgent tickets via AJAX
         */
        refreshUrgentTickets: function() {
            var $container = $('#wphd-take-action');
            
            if ($container.length === 0) {
                return; // Not on dashboard page
            }

            $.ajax({
                url: wpHelpDesk.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wphd_refresh_urgent_tickets',
                    nonce: wpHelpDesk.nonce
                },
                success: function(response) {
                    if (response.success && response.data.html) {
                        // Update the Take Action section
                        $container.html(response.data.html);
                        
                        // Update last refresh time
                        $('#wphd-last-refresh-time').text(response.data.timestamp);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Failed to refresh urgent tickets:', error);
                }
            });
        },

        /**
         * Setup tab switching for Created Tickets section
         */
        setupTabSwitching: function() {
            $('.wphd-status-tab').on('click', function(e) {
                var $tab = $(this);
                var isActive = $tab.hasClass('active');
                
                // Allow default link behavior to load page with status tab parameter
                // The PHP will handle filtering
                
                // Just update UI state
                $('.wphd-status-tab').removeClass('active');
                $tab.addClass('active');
            });
        },

        /**
         * Stop auto-refresh (cleanup)
         */
        stopAutoRefresh: function() {
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
                this.refreshInterval = null;
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        // Only initialize on dashboard page
        if ($('#wphd-take-action').length > 0) {
            WPHD_Dashboard.init();
        }
    });

    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        WPHD_Dashboard.stopAutoRefresh();
    });

})(jQuery);
