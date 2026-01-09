(function($) {
	'use strict';

	const WPHD_QueueFilters = {
		init: function() {
			this.bindEvents();
			this.initSelect2();
			this.handleURLParams();
		},

		bindEvents: function() {
			// Filter selection
			$('#wphd-filter-selector').on('change', this.applyFilter.bind(this));

			// Create/Edit modal
			$('.wphd-create-filter-btn').on('click', this.openFilterModal.bind(this));
			$(document).on('click', '.wphd-edit-filter-btn', this.editFilter.bind(this));

			// Delete filter
			$(document).on('click', '.wphd-delete-filter-btn', this.deleteFilter.bind(this));

			// Set default
			$(document).on('click', '.wphd-set-default-btn', this.setDefaultFilter.bind(this));

			// Preview button
			$('#wphd-preview-filter').on('click', this.previewFilter.bind(this));

			// Save filter form
			$('#wphd-filter-form').on('submit', this.saveFilter.bind(this));

			// Modal close
			$('.wphd-modal-close').on('click', this.closeModal.bind(this));
			$(window).on('click', this.handleOutsideClick.bind(this));

			// Assignee type toggle
			$('input[name="assignee_type"]').on('change', this.toggleAssigneeFields.bind(this));

			// Date operator change
			$('select[name="date_created_operator"]').on('change', this.toggleDateFields.bind(this));

			// Initial state
			this.toggleAssigneeFields();
			this.toggleDateFields();
		},

		initSelect2: function() {
			if ($.fn.select2) {
				$('.wphd-select2').select2({
					width: '100%',
					placeholder: wpHelpDesk.i18n.select_placeholder || 'Select...'
				});
			}
		},

		handleURLParams: function() {
			// If editing a filter from URL, open the modal
			const urlParams = new URLSearchParams(window.location.search);
			if (urlParams.get('action') === 'edit' && urlParams.get('filter_id')) {
				this.openFilterModal();
			}
		},

		applyFilter: function(e) {
			const filterId = $(e.target).val();
			if (!filterId) {
				// Go to unfiltered view - handle both ? and & for filter_id
				const currentUrl = window.location.href;
				const cleanUrl = currentUrl.replace(/[?&]filter_id=\d+/, '').replace(/\?&/, '?');
				window.location.href = cleanUrl || wpHelpDesk.ticketsUrl || window.location.pathname;
				return;
			}

			const currentUrl = new URL(window.location.href);
			currentUrl.searchParams.set('filter_id', filterId);
			window.location.href = currentUrl.toString();
		},

		openFilterModal: function(e) {
			if (e) {
				e.preventDefault();
			}
			$('#wphd-filter-modal').fadeIn();
			this.initSelect2();
			this.toggleAssigneeFields();
			this.toggleDateFields();
		},

		closeModal: function(e) {
			if (e) {
				e.preventDefault();
			}
			$('#wphd-filter-modal').fadeOut();
		},

		handleOutsideClick: function(e) {
			if ($(e.target).is('#wphd-filter-modal')) {
				this.closeModal();
			}
		},

		editFilter: function(e) {
			e.preventDefault();
			const filterId = $(e.currentTarget).data('filter-id');

			$.ajax({
				url: wpHelpDesk.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wphd_get_filter',
					nonce: wpHelpDesk.nonce,
					filter_id: filterId
				},
				success: (response) => {
					if (response.success) {
						this.populateForm(response.data);
						this.openFilterModal();
					} else {
						alert(response.data.message || wpHelpDesk.i18n.error_loading_filter);
					}
				},
				error: () => {
					alert(wpHelpDesk.i18n.error_loading_filter || 'Failed to load filter.');
				}
			});
		},

		populateForm: function(filter) {
			// Reset form first
			this.resetForm();

			// Basic fields
			$('#filter_name').val(filter.name);
			$('#filter_description').val(filter.description);
			$('#filter_type').val(filter.filter_type);
			$('#sort_field').val(filter.sort_field);
			$('#sort_order').val(filter.sort_order);
			$('input[name="is_default"]').prop('checked', filter.is_default == 1);

			// Add hidden filter_id
			if (!$('#wphd-filter-form input[name="filter_id"]').length) {
				$('#wphd-filter-form').prepend('<input type="hidden" name="filter_id" value="' + filter.id + '">');
			} else {
				$('#wphd-filter-form input[name="filter_id"]').val(filter.id);
			}

			const config = filter.filter_config;

			// Status
			if (config.status) {
				$('#filter_status').val(config.status).trigger('change');
			}

			// Priority
			if (config.priority) {
				$('#filter_priority').val(config.priority).trigger('change');
			}

			// Category
			if (config.category) {
				$('#filter_category').val(config.category).trigger('change');
			}

			// Assignee
			if (config.assignee_type) {
				$('input[name="assignee_type"][value="' + config.assignee_type + '"]').prop('checked', true);
				if (config.assignee_type === 'specific' && config.assignee_ids) {
					$('#filter_assignee_ids').val(config.assignee_ids).trigger('change');
				}
			}
			this.toggleAssigneeFields();

			// Date created
			if (config.date_created && config.date_created.operator) {
				$('#date_created_operator').val(config.date_created.operator);
				if (config.date_created.operator === 'between') {
					$('input[name="date_created_start"]').val(config.date_created.start || '');
					$('input[name="date_created_end"]').val(config.date_created.end || '');
				}
			}
			this.toggleDateFields();

			// Search phrase
			if (config.search_phrase) {
				$('#search_phrase').val(config.search_phrase);
			}
		},

		resetForm: function() {
			$('#wphd-filter-form')[0].reset();
			$('#wphd-filter-form input[name="filter_id"]').remove();
			$('.wphd-select2').val(null).trigger('change');
			$('input[name="assignee_type"][value="any"]').prop('checked', true);
			this.toggleAssigneeFields();
			this.toggleDateFields();
		},

		deleteFilter: function(e) {
			e.preventDefault();
			
			if (!confirm(wpHelpDesk.i18n.confirm_delete || 'Are you sure you want to delete this filter?')) {
				return;
			}

			const filterId = $(e.currentTarget).data('filter-id');
			const $button = $(e.currentTarget);
			
			$button.prop('disabled', true);

			$.ajax({
				url: wpHelpDesk.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wphd_delete_queue_filter',
					nonce: wpHelpDesk.nonce,
					filter_id: filterId
				},
				success: (response) => {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || wpHelpDesk.i18n.error_deleting_filter);
						$button.prop('disabled', false);
					}
				},
				error: () => {
					alert(wpHelpDesk.i18n.error_deleting_filter || 'Failed to delete filter.');
					$button.prop('disabled', false);
				}
			});
		},

		setDefaultFilter: function(e) {
			e.preventDefault();
			
			const filterId = $(e.currentTarget).data('filter-id');
			const $link = $(e.currentTarget);

			$.ajax({
				url: wpHelpDesk.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wphd_set_default_filter',
					nonce: wpHelpDesk.nonce,
					filter_id: filterId
				},
				success: (response) => {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || wpHelpDesk.i18n.error_setting_default);
					}
				},
				error: () => {
					alert(wpHelpDesk.i18n.error_setting_default || 'Failed to set default filter.');
				}
			});
		},

		previewFilter: function(e) {
			e.preventDefault();
			const filterConfig = this.getFormData();

			$('#wphd-filter-preview').show();
			$('#wphd-preview-results').html('<p>' + (wpHelpDesk.i18n.loading || 'Loading...') + '</p>');

			$.ajax({
				url: wpHelpDesk.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wphd_preview_filter',
					nonce: wpHelpDesk.nonce,
					filter_config: JSON.stringify(filterConfig)
				},
				success: (response) => {
					if (response.success) {
						$('#wphd-preview-results').html(response.data.html);
						$('#wphd-preview-count').text(response.data.count);
					} else {
						$('#wphd-preview-results').html('<p>' + (response.data.message || wpHelpDesk.i18n.error_preview) + '</p>');
					}
				},
				error: () => {
					$('#wphd-preview-results').html('<p>' + (wpHelpDesk.i18n.error_preview || 'Failed to preview filter.') + '</p>');
				}
			});
		},

		saveFilter: function(e) {
			e.preventDefault();
			
			// Basic validation
			if (!$('#filter_name').val().trim()) {
				alert(wpHelpDesk.i18n.filter_name_required || 'Filter name is required.');
				$('#filter_name').focus();
				return;
			}

			// Submit the form normally (non-AJAX)
			e.target.submit();
		},

		getFormData: function() {
			const config = {};

			// Status
			const status = $('#filter_status').val();
			if (status && status.length > 0) {
				config.status = status;
			}

			// Priority
			const priority = $('#filter_priority').val();
			if (priority && priority.length > 0) {
				config.priority = priority;
			}

			// Category
			const category = $('#filter_category').val();
			if (category && category.length > 0) {
				config.category = category;
			}

			// Assignee
			const assigneeType = $('input[name="assignee_type"]:checked').val();
			if (assigneeType && assigneeType !== 'any') {
				config.assignee_type = assigneeType;
				if (assigneeType === 'specific') {
					const assigneeIds = $('#filter_assignee_ids').val();
					if (assigneeIds && assigneeIds.length > 0) {
						config.assignee_ids = assigneeIds.map(Number);
					}
				}
			}

			// Date created
			const dateOperator = $('#date_created_operator').val();
			if (dateOperator) {
				config.date_created = { operator: dateOperator };
				if (dateOperator === 'between') {
					const start = $('input[name="date_created_start"]').val();
					const end = $('input[name="date_created_end"]').val();
					if (start) config.date_created.start = start;
					if (end) config.date_created.end = end;
				}
			}

			// Search phrase
			const searchPhrase = $('#search_phrase').val();
			if (searchPhrase) {
				config.search_phrase = searchPhrase;
			}

			return config;
		},

		toggleAssigneeFields: function() {
			const selectedType = $('input[name="assignee_type"]:checked').val();
			if (selectedType === 'specific') {
				$('#filter_assignee_ids').prop('disabled', false).closest('tr').find('select').show();
			} else {
				$('#filter_assignee_ids').prop('disabled', true);
			}
		},

		toggleDateFields: function() {
			const operator = $('#date_created_operator').val();
			if (operator === 'between') {
				$('#date_created_range').show();
			} else {
				$('#date_created_range').hide();
			}
		}
	};

	$(document).ready(() => WPHD_QueueFilters.init());

})(jQuery);
