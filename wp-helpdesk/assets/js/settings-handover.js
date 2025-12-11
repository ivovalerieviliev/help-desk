/**
 * Handover Settings JavaScript
 *
 * Handles handover section management in settings.
 *
 * @package WP_HelpDesk
 * @since 1.0.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        initHandoverSettings();
    });

    /**
     * Initialize handover settings functionality
     */
    function initHandoverSettings() {
        // Add section button
        $('.wphd-add-section-btn').on('click', function() {
            openSectionModal();
        });

        // Edit section button
        $(document).on('click', '.wphd-edit-section-btn', function() {
            const sectionId = $(this).data('section-id');
            loadSectionData(sectionId);
        });

        // Delete section button
        $(document).on('click', '.wphd-delete-section-btn', function() {
            const sectionId = $(this).data('section-id');
            deleteSection(sectionId);
        });

        // Close modal
        $('.wphd-modal-close, .wphd-cancel-section-btn').on('click', function() {
            closeSectionModal();
        });

        // Click outside modal to close
        $(window).on('click', function(e) {
            if ($(e.target).is('#wphd-section-modal')) {
                closeSectionModal();
            }
        });

        // Auto-generate slug from name
        $('#wphd-section-name').on('input', function() {
            const name = $(this).val();
            const slug = generateSlug(name);
            $('#wphd-section-slug').val(slug);
        });

        // Form submission
        $('#wphd-section-form').on('submit', function(e) {
            e.preventDefault();
            saveSection();
            return false;
        });
    }

    /**
     * Open section modal for adding/editing
     */
    function openSectionModal(sectionId = 0) {
        // Reset form
        $('#wphd-section-form')[0].reset();
        $('#wphd-section-id').val('');
        $('#wphd-section-active').prop('checked', true);
        
        if (sectionId) {
            $('#wphd-section-modal-title').text('Edit Section');
        } else {
            $('#wphd-section-modal-title').text('Add New Section');
        }
        
        $('#wphd-section-modal').fadeIn();
    }

    /**
     * Close section modal
     */
    function closeSectionModal() {
        $('#wphd-section-modal').fadeOut();
    }

    /**
     * Load section data for editing
     */
    function loadSectionData(sectionId) {
        $.ajax({
            url: wpHelpDesk.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wphd_get_handover_sections',
                nonce: wpHelpDesk.nonce
            },
            success: function(response) {
                if (response.success && response.data.sections) {
                    const section = response.data.sections.find(s => s.id == sectionId);
                    
                    if (section) {
                        $('#wphd-section-id').val(section.id);
                        $('#wphd-section-name').val(section.name);
                        $('#wphd-section-slug').val(section.slug);
                        $('#wphd-section-description').val(section.description);
                        $('#wphd-section-order').val(section.display_order);
                        $('#wphd-section-active').prop('checked', section.is_active == 1);
                        
                        openSectionModal(sectionId);
                    }
                } else {
                    showNotice('error', 'Failed to load section data.');
                }
            },
            error: function() {
                showNotice('error', 'Network error. Please try again.');
            }
        });
    }

    /**
     * Save section (create or update)
     */
    function saveSection() {
        const sectionId = $('#wphd-section-id').val();
        const name = $('#wphd-section-name').val();
        const slug = $('#wphd-section-slug').val();
        const description = $('#wphd-section-description').val();
        const displayOrder = $('#wphd-section-order').val();
        const isActive = $('#wphd-section-active').is(':checked') ? 1 : 0;

        if (!name || !slug) {
            showNotice('error', 'Name and slug are required.');
            return;
        }

        $.ajax({
            url: wpHelpDesk.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wphd_save_handover_section',
                nonce: wpHelpDesk.nonce,
                section_id: sectionId,
                name: name,
                slug: slug,
                description: description,
                display_order: displayOrder,
                is_active: isActive
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    closeSectionModal();
                    // Reload the page to show updated sections
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showNotice('error', response.data.message || 'Failed to save section.');
                }
            },
            error: function() {
                showNotice('error', 'Network error. Please try again.');
            }
        });
    }

    /**
     * Delete a section
     */
    function deleteSection(sectionId) {
        if (!confirm('Are you sure you want to delete this section? This action cannot be undone.')) {
            return;
        }

        $.ajax({
            url: wpHelpDesk.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wphd_delete_handover_section',
                nonce: wpHelpDesk.nonce,
                section_id: sectionId
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    // Remove the row from the table
                    $('tr[data-section-id="' + sectionId + '"]').fadeOut(function() {
                        $(this).remove();
                    });
                } else {
                    showNotice('error', response.data.message || 'Failed to delete section.');
                }
            },
            error: function() {
                showNotice('error', 'Network error. Please try again.');
            }
        });
    }

    /**
     * Generate slug from text
     */
    function generateSlug(text) {
        return text
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
    }

    /**
     * Show notice message
     */
    function showNotice(type, message) {
        const noticeClass = 'notice notice-' + type + ' is-dismissible';
        const notice = $('<div class="' + noticeClass + '"><p>' + escapeHtml(message) + '</p></div>');
        
        $('.wphd-settings-handover-wrap h1').after(notice);
        
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

})(jQuery);
