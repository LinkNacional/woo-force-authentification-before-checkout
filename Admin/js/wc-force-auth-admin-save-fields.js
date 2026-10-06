/**
 * Saves a plugin settings screen via Ajax and gives feedback with SweetAlert2.
 *
 * @package WcForceAuth
 */
(function ($) {
	'use strict';

	function showError(message) {
		var i18n = (window.wcForceAuthSettings && window.wcForceAuthSettings.i18n) || {};

		window.Swal.fire({
			icon: 'error',
			title: 'Error',
			text: message || i18n.error || 'An error occurred while saving the settings.',
		});
	}

	$(document).on('click', '.admin-layout-submit-wrapper button', function (e) {
		e.preventDefault();

		var config = window.wcForceAuthSettings || {};
		var form = document.getElementById('wcfa-settings-form');

		// Native HTML5 validation first.
		if (form && form.checkValidity && !form.checkValidity()) {
			var invalid = form.querySelector(':invalid');
			if (invalid) {
				invalid.scrollIntoView({ block: 'center' });
				setTimeout(function () {
					invalid.reportValidity();
				}, 200);
			}
			return;
		}

		if (!config.action || !config.nonce) {
			showError(config.i18n ? config.i18n.security : null);
			return;
		}

		var prefix = config.prefix || 'wc_force_auth_';
		var settings = {};

		$(form).find('[name^="' + prefix + '"]').each(function () {
			var name = $(this).attr('name');
			var type = $(this).attr('type');

			if (type === 'checkbox') {
				settings[name] = $(this).is(':checked') ? 'yes' : 'no';
			} else if (type === 'radio') {
				if ($(this).is(':checked')) {
					settings[name] = $(this).val();
				}
			} else {
				settings[name] = $(this).val();
			}
		});

		var formData = new FormData();
		formData.append('action', config.action);
		formData.append('_ajax_nonce', config.nonce);
		formData.append('settings', JSON.stringify(settings));

		var $button = $(this);
		$button.prop('disabled', true);

		$.ajax({
			url: config.ajaxUrl || window.ajaxurl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
		})
			.done(function (response) {
				if (response && response.success) {
					window.Swal.fire({
						icon: 'success',
						title: (response.data && response.data.message) || (config.i18n && config.i18n.saved) || 'Saved!',
					});
				} else {
					showError(response && response.data ? response.data.message : null);
				}
			})
			.fail(function (xhr) {
				var message = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : null;
				showError(message);
			})
			.always(function () {
				$button.prop('disabled', false);
			});
	});

	// Avoid accidental submits via Enter inside the settings form.
	$(document).on('keydown', '#wcfa-settings-form', function (e) {
		if (e.key === 'Enter' && e.target && e.target.tagName !== 'TEXTAREA') {
			e.preventDefault();
		}
	});

	// Joined fields controlled by a parent field:
	//  - data-wcfa-show-when="custom": show the child only when the parent value
	//    matches (used by the URL fields hidden behind a "Custom" mode).
	//  - data-wcfa-dim-when="no": dim the child when the parent value matches
	//    (used by the "Display" Show/Hide toggles). The input is NOT actually
	//    disabled — a real `disabled` would drop its value from the POST.
	$(function () {
		$('[data-wcfa-controlled-by]').each(function () {
			var $child = $(this);
			var name = $child.attr('data-wcfa-controlled-by');
			var $parent = $('[name="' + name + '"]');

			if (!$parent.length || !name) {
				return;
			}

			var showWhen = $child.attr('data-wcfa-show-when');
			var dimWhen = $child.attr('data-wcfa-dim-when');

			function currentValue() {
				var $checked = $parent.filter(':checked');
				if ($checked.length) {
					return $checked.val();
				}
				return $parent.val();
			}

			function apply() {
				var value = currentValue();

				if (typeof showWhen !== 'undefined') {
					$child.toggle(value === showWhen);
				}
				if (typeof dimWhen !== 'undefined') {
					$child.toggleClass('wcfa-child--disabled', value === dimWhen);
				}
			}

			$parent.on('change', apply);
			apply();
		});
	});
})(jQuery);
