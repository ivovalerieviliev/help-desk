/**
 * Custom Fields Admin Page JavaScript
 *
 * @package WP_HelpDesk
 * @since 1.0.0
 */

(function($) {
	'use strict';

	let fieldCounter = 0;

	const CustomFields = {
		init: function() {
			this.bindEvents();
			this.initSortable();
			this.updateFieldOrders();
		},

		bindEvents: function() {
			// Add new field
			$('#wphd-add-custom-field').on('click', this.addField.bind(this));

			// Delete field
			$(document).on('click', '.wphd-delete-field-btn', this.deleteField.bind(this));

			// Field type change
			$(document).on('change', '.wphd-field-type', this.handleTypeChange.bind(this));

			// Configure options
			$(document).on('click', '.wphd-configure-options-btn', this.toggleOptions.bind(this));

			// Add option
			$(document).on('click', '.wphd-add-option-btn', this.addOption.bind(this));

			// Remove option
			$(document).on('click', '.wphd-remove-option-btn', this.removeOption.bind(this));

			// Field key validation
			$(document).on('input', '.wphd-field-key:not([readonly])', this.validateFieldKey.bind(this));

			// Form submit validation
			$('#wphd-custom-fields-form').on('submit', this.validateForm.bind(this));
		},

		initSortable: function() {
			$('#wphd-custom-fields-tbody').sortable({
				handle: '.wphd-drag-handle',
				items: '.wphd-field-row:not(.wphd-field-row-template)',
				placeholder: 'ui-sortable-placeholder',
				update: this.updateFieldOrders.bind(this),
				helper: function(e, tr) {
					const $originals = tr.children();
					const $helper = tr.clone();
					$helper.children().each(function(index) {
						$(this).width($originals.eq(index).width());
					});
					return $helper;
				}
			});
		},

		updateFieldOrders: function() {
			$('#wphd-custom-fields-tbody .wphd-field-row:not(.wphd-field-row-template)').each(function(index) {
				$(this).find('.wphd-field-order').val(index);
			});
		},

		addField: function(e) {
			e.preventDefault();

			const template = $('#wphd-field-row-template').html();
			const newKey = 'custom_field_' + (++fieldCounter);
			const newRow = template.replace(/__FIELD_KEY__/g, newKey);

			// Remove the "no fields" message if present
			$('#wphd-custom-fields-tbody .wphd-no-fields-row').remove();

			// Add the new row
			$(newRow).appendTo('#wphd-custom-fields-tbody');

			// Update orders
			this.updateFieldOrders();

			// Focus on the label field
			$('#wphd-custom-fields-tbody .wphd-field-row').last().find('.wphd-field-label').focus();
		},

		deleteField: function(e) {
			e.preventDefault();

			if (!confirm(wphdCustomFields.i18n.confirmDelete)) {
				return;
			}

			const $row = $(e.currentTarget).closest('.wphd-field-row');
			const fieldKey = $row.data('field-key');

			// Remove the field row and its options row
			$row.remove();
			$('.wphd-field-options-row[data-field-key="' + fieldKey + '"]').remove();

			// Update orders
			this.updateFieldOrders();

			// Show "no fields" message if no fields left
			if ($('#wphd-custom-fields-tbody .wphd-field-row').length === 0) {
				$('#wphd-custom-fields-tbody').html(
					'<tr class="wphd-no-fields-row">' +
					'<td colspan="8" style="text-align: center; padding: 40px;">No custom fields yet. Click "Add Custom Field" to create one.</td>' +
					'</tr>'
				);
			}
		},

		handleTypeChange: function(e) {
			const $select = $(e.currentTarget);
			const type = $select.val();
			const $row = $select.closest('.wphd-field-row');
			const fieldKey = $row.data('field-key');

			const $optionsBtn = $row.find('.wphd-configure-options-btn');
			const $optionsRow = $('.wphd-field-options-row[data-field-key="' + fieldKey + '"]');

			if (type === 'dropdown') {
				$optionsBtn.show();
				$optionsRow.show();
			} else {
				$optionsBtn.hide();
				$optionsRow.hide();
			}
		},

		toggleOptions: function(e) {
			e.preventDefault();

			const $btn = $(e.currentTarget);
			const fieldKey = $btn.data('field-key');
			const $optionsRow = $('.wphd-field-options-row[data-field-key="' + fieldKey + '"]');

			$optionsRow.toggle();
		},

		addOption: function(e) {
			e.preventDefault();

			const $btn = $(e.currentTarget);
			const fieldKey = $btn.data('field-key');
			const $optionsContainer = $btn.prev('.wphd-dropdown-options');
			const optionIndex = $optionsContainer.find('.wphd-dropdown-option').length;

			const optionHtml = 
				'<div class="wphd-dropdown-option">' +
				'<input type="text" name="fields[' + fieldKey + '][options][' + optionIndex + '][label]" placeholder="Option Label" class="regular-text">' +
				'<input type="text" name="fields[' + fieldKey + '][options][' + optionIndex + '][value]" placeholder="value" class="regular-text">' +
				'<button type="button" class="button button-small wphd-remove-option-btn">Remove</button>' +
				'</div>';

			$optionsContainer.append(optionHtml);
		},

		removeOption: function(e) {
			e.preventDefault();

			const $btn = $(e.currentTarget);
			const $option = $btn.closest('.wphd-dropdown-option');
			const $container = $option.closest('.wphd-dropdown-options');

			// Don't allow removing the last option
			if ($container.find('.wphd-dropdown-option').length <= 1) {
				alert('At least one option is required for dropdown fields.');
				return;
			}

			$option.remove();
		},

		validateFieldKey: function(e) {
			const $input = $(e.currentTarget);
			const value = $input.val();

			// Only allow lowercase letters, numbers, and underscores
			const sanitized = value.replace(/[^a-z0-9_]/g, '');

			if (value !== sanitized) {
				$input.val(sanitized);
			}
		},

		validateForm: function(e) {
			const $form = $(e.currentTarget);
			let isValid = true;
			const fieldKeys = {};

			// Check for duplicate field keys
			$form.find('.wphd-field-key').each(function() {
				const key = $(this).val();
				if (key && key !== '__FIELD_KEY__') {
					if (fieldKeys[key]) {
						alert('Duplicate field key found: ' + key + '. Each field must have a unique key.');
						isValid = false;
						return false;
					}
					fieldKeys[key] = true;
				}
			});

			if (!isValid) {
				e.preventDefault();
				return false;
			}

			// Validate dropdown fields have at least one option
			$form.find('.wphd-field-type').each(function() {
				if ($(this).val() === 'dropdown') {
					const $row = $(this).closest('.wphd-field-row');
					const fieldKey = $row.data('field-key');
					const $optionsRow = $('.wphd-field-options-row[data-field-key="' + fieldKey + '"]');
					const optionsCount = $optionsRow.find('.wphd-dropdown-option input[name*="[label]"]').filter(function() {
						return $(this).val().trim() !== '';
					}).length;

					if (optionsCount === 0) {
						const fieldLabel = $row.find('.wphd-field-label').val() || fieldKey;
						alert('Dropdown field "' + fieldLabel + '" must have at least one option.');
						isValid = false;
						return false;
					}
				}
			});

			if (!isValid) {
				e.preventDefault();
				return false;
			}

			return true;
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		CustomFields.init();
	});

})(jQuery);
