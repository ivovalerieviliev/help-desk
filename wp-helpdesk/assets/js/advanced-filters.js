/**
 * Advanced Filters JavaScript
 *
 * Handles client-side filter interactions, real-time preview, and AJAX operations
 *
 * @package WP_HelpDesk
 * @since   1.0.0
 */

(function($) {
    'use strict';

    var AdvancedFilters = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initFilterBuilder();
        },

        /**
         * Bind DOM events
         */
        bindEvents: function() {
            // Save filter
            $(document).on('click', '.wphd-save-filter-btn', this.saveFilter);
            
            // Update filter
            $(document).on('click', '.wphd-update-filter-btn', this.updateFilter);
            
            // Delete filter
            $(document).on('click', '.wphd-delete-filter-btn', this.deleteFilter);
            
            // Preview filter
            $(document).on('click', '.wphd-preview-filter-btn', this.previewFilter);
            
            // Set default filter
            $(document).on('click', '.wphd-set-default-filter-btn', this.setDefaultFilter);
            
            // Add filter group
            $(document).on('click', '.wphd-add-filter-group-btn', this.addFilterGroup);
            
            // Add filter condition
            $(document).on('click', '.wphd-add-condition-btn', this.addFilterCondition);
            
            // Remove filter group
            $(document).on('click', '.wphd-remove-group-btn', this.removeFilterGroup);
            
            // Remove filter condition
            $(document).on('click', '.wphd-remove-condition-btn', this.removeFilterCondition);
            
            // Apply filter
            $(document).on('click', '.wphd-apply-filter-btn', this.applyFilter);
            
            // Clear filter
            $(document).on('click', '.wphd-clear-filter-btn', this.clearFilter);
        },

        /**
         * Initialize filter builder
         */
        initFilterBuilder: function() {
            // Initialize any select2 dropdowns in filter builder
            if ($.fn.select2) {
                $('.wphd-filter-field-select').select2({
                    placeholder: wpHelpDesk.i18n.select_placeholder || 'Select...',
                    width: '100%'
                });
            }
        },

        /**
         * Build filter configuration from UI
         */
        buildFilterConfig: function() {
            var groups = [];
            
            $('.wphd-filter-group').each(function() {
                var $group = $(this);
                var logic = $group.find('.wphd-group-logic').val() || 'AND';
                var conditions = [];
                
                $group.find('.wphd-filter-condition').each(function() {
                    var $condition = $(this);
                    var field = $condition.find('.wphd-condition-field').val();
                    var operator = $condition.find('.wphd-condition-operator').val();
                    var value = $condition.find('.wphd-condition-value').val();
                    
                    if (field && operator && value) {
                        conditions.push({
                            field: field,
                            operator: operator,
                            value: value
                        });
                    }
                });
                
                if (conditions.length > 0) {
                    groups.push({
                        logic: logic,
                        conditions: conditions
                    });
                }
            });
            
            var sortField = $('#wphd-filter-sort-field').val() || 'created_at';
            var sortOrder = $('#wphd-filter-sort-order').val() || 'DESC';
            
            return {
                groups: groups,
                sort: {
                    field: sortField,
                    order: sortOrder
                }
            };
        },

        /**
         * Save filter
         */
        saveFilter: function(e) {
            e.preventDefault();
            
            var name = $('#wphd-filter-name').val();
            var description = $('#wphd-filter-description').val();
            var filterType = $('#wphd-filter-type').val() || 'user';
            var isDefault = $('#wphd-filter-default').is(':checked');
            
            if (!name) {
                alert(wpHelpDesk.i18n.filter_name_required || 'Filter name is required.');
                return;
            }
            
            var config = AdvancedFilters.buildFilterConfig();
            
            $.ajax({
                url: wpHelpDesk.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wphd_save_filter',
                    nonce: wpHelpDesk.nonce,
                    name: name,
                    description: description,
                    filter_config: JSON.stringify(config),
                    filter_type: filterType,
                    is_default: isDefault
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        // Reload page to show new filter
                        window.location.reload();
                    } else {
                        alert(response.data.message || wpHelpDesk.i18n.error);
                    }
                },
                error: function() {
                    alert(wpHelpDesk.i18n.error);
                }
            });
        },

        /**
         * Update filter
         */
        updateFilter: function(e) {
            e.preventDefault();
            
            var filterId = $(this).data('filter-id');
            var name = $('#wphd-filter-name').val();
            var description = $('#wphd-filter-description').val();
            var isDefault = $('#wphd-filter-default').is(':checked');
            
            if (!name) {
                alert(wpHelpDesk.i18n.filter_name_required || 'Filter name is required.');
                return;
            }
            
            var config = AdvancedFilters.buildFilterConfig();
            
            $.ajax({
                url: wpHelpDesk.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wphd_update_filter',
                    nonce: wpHelpDesk.nonce,
                    filter_id: filterId,
                    name: name,
                    description: description,
                    filter_config: JSON.stringify(config),
                    is_default: isDefault
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        window.location.reload();
                    } else {
                        alert(response.data.message || wpHelpDesk.i18n.error);
                    }
                },
                error: function() {
                    alert(wpHelpDesk.i18n.error);
                }
            });
        },

        /**
         * Delete filter
         */
        deleteFilter: function(e) {
            e.preventDefault();
            
            var confirmMsg = wpHelpDesk.i18n.confirm_delete_filter || 'Are you sure you want to delete this filter?';
            if (!confirm(confirmMsg)) {
                return;
            }
            
            var filterId = $(this).data('filter-id');
            
            $.ajax({
                url: wpHelpDesk.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wphd_delete_filter',
                    nonce: wpHelpDesk.nonce,
                    filter_id: filterId
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        window.location.reload();
                    } else {
                        alert(response.data.message || wpHelpDesk.i18n.error);
                    }
                },
                error: function() {
                    alert(wpHelpDesk.i18n.error);
                }
            });
        },

        /**
         * Preview filter (show count)
         */
        previewFilter: function(e) {
            e.preventDefault();
            
            var config = AdvancedFilters.buildFilterConfig();
            
            $('.wphd-filter-preview').html('<span class="spinner is-active"></span>');
            
            $.ajax({
                url: wpHelpDesk.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wphd_preview_filter',
                    nonce: wpHelpDesk.nonce,
                    filter_config: JSON.stringify(config)
                },
                success: function(response) {
                    if (response.success) {
                        $('.wphd-filter-preview').html(
                            '<span class="wphd-preview-count">' + 
                            response.data.message + 
                            '</span>'
                        );
                    } else {
                        $('.wphd-filter-preview').html(
                            '<span class="wphd-preview-error">' + 
                            (response.data.message || wpHelpDesk.i18n.error_preview) + 
                            '</span>'
                        );
                    }
                },
                error: function() {
                    $('.wphd-filter-preview').html(
                        '<span class="wphd-preview-error">' + 
                        wpHelpDesk.i18n.error_preview + 
                        '</span>'
                    );
                }
            });
        },

        /**
         * Set filter as default
         */
        setDefaultFilter: function(e) {
            e.preventDefault();
            
            var filterId = $(this).data('filter-id');
            
            $.ajax({
                url: wpHelpDesk.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wphd_set_default_filter',
                    nonce: wpHelpDesk.nonce,
                    filter_id: filterId
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        window.location.reload();
                    } else {
                        alert(response.data.message || wpHelpDesk.i18n.error);
                    }
                },
                error: function() {
                    alert(wpHelpDesk.i18n.error);
                }
            });
        },

        /**
         * Add filter group
         */
        addFilterGroup: function(e) {
            e.preventDefault();
            // Implementation would add a new filter group to the UI
            console.log('Add filter group');
        },

        /**
         * Add filter condition
         */
        addFilterCondition: function(e) {
            e.preventDefault();
            // Implementation would add a new condition to a group
            console.log('Add filter condition');
        },

        /**
         * Remove filter group
         */
        removeFilterGroup: function(e) {
            e.preventDefault();
            $(this).closest('.wphd-filter-group').remove();
        },

        /**
         * Remove filter condition
         */
        removeFilterCondition: function(e) {
            e.preventDefault();
            $(this).closest('.wphd-filter-condition').remove();
        },

        /**
         * Apply filter
         */
        applyFilter: function(e) {
            e.preventDefault();
            
            var config = AdvancedFilters.buildFilterConfig();
            
            // Store config in session storage
            sessionStorage.setItem('wphd_active_filter', JSON.stringify(config));
            
            // Reload tickets with filter
            window.location.reload();
        },

        /**
         * Clear filter
         */
        clearFilter: function(e) {
            e.preventDefault();
            
            // Clear session storage
            sessionStorage.removeItem('wphd_active_filter');
            
            // Reload page
            window.location.href = wpHelpDesk.ticketsUrl || window.location.pathname;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        AdvancedFilters.init();
    });

})(jQuery);
