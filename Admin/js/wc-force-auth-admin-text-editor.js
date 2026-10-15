/**
 * Visual text editor: tab switching + inline pencil editing for the OTP settings.
 *
 * Each editable text is bound to a hidden input (name `wc_force_auth_otp_*`) that
 * the settings form already saves. Editing a value updates the hidden input and
 * every live preview occurrence.
 *
 * @package WcForceAuth
 */
(function () {
	'use strict';

	var TEXT = (window.wcForceAuthEditor && window.wcForceAuthEditor.i18n) || {};

	document.addEventListener('DOMContentLoaded', function () {
		var editor = document.querySelector('.wcfa-editor');
		if (!editor) {
			return;
		}

		/* --- Tabs --------------------------------------------------------- */
		editor.querySelectorAll('.wcfa-editor__tab').forEach(function (tab) {
			tab.addEventListener('click', function () {
				var target = tab.getAttribute('data-goto');

				editor.querySelectorAll('.wcfa-editor__tab').forEach(function (t) {
					t.classList.toggle('is-active', t === tab);
				});
				editor.querySelectorAll('.wcfa-editor__panel').forEach(function (panel) {
					panel.classList.toggle('is-active', panel.getAttribute('data-panel') === target);
				});
			});
		});

		/* --- Value helpers ------------------------------------------------ */
		function hiddenInput(key) {
			return editor.querySelector('.wcfa-et-input[data-et="' + key + '"]');
		}

		function currentValue(key) {
			var input = hiddenInput(key);
			return input ? input.value : '';
		}

		function applyValue(key, value) {
			var input = hiddenInput(key);
			if (input) {
				input.value = value;
			}

			editor.querySelectorAll('.wcfa-et-text[data-et="' + key + '"]').forEach(function (el) {
				el.textContent = value;
			});
			editor.querySelectorAll('[data-et-ph="' + key + '"]').forEach(function (el) {
				el.setAttribute('placeholder', value);
			});
		}

		/* --- Popover ------------------------------------------------------ */
		var popover = null;

		function closePopover() {
			if (popover) {
				popover.remove();
				popover = null;
			}
		}

		function openPopover(pencil) {
			closePopover();

			var key = pencil.getAttribute('data-et');
			var label = pencil.getAttribute('data-label') || key;

			popover = document.createElement('div');
			popover.className = 'wcfa-et-popover';

			var title = document.createElement('label');
			title.className = 'wcfa-et-popover__label';
			title.textContent = label;

			var input = document.createElement('textarea');
			input.className = 'wcfa-et-popover__input';
			input.rows = 3;
			input.value = currentValue(key);

			var actions = document.createElement('div');
			actions.className = 'wcfa-et-popover__actions';

			var cancel = document.createElement('button');
			cancel.type = 'button';
			cancel.className = 'button';
			cancel.textContent = TEXT.cancel || 'Cancel';

			var save = document.createElement('button');
			save.type = 'button';
			save.className = 'button button-primary';
			save.textContent = TEXT.save || 'Save';

			actions.appendChild(cancel);
			actions.appendChild(save);
			popover.appendChild(title);
			popover.appendChild(input);
			popover.appendChild(actions);
			document.body.appendChild(popover);

			// Position below the pencil, clamped to the viewport.
			var rect = pencil.getBoundingClientRect();
			var width = 320;
			var left = Math.min(rect.left, window.innerWidth - width - 16);
			left = Math.max(16, left);
			var top = rect.bottom + 8;
			if (top + 200 > window.innerHeight) {
				top = Math.max(16, rect.top - 200);
			}
			popover.style.top = top + 'px';
			popover.style.left = left + 'px';

			input.focus();
			input.select();

			cancel.addEventListener('click', closePopover);
			save.addEventListener('click', function () {
				applyValue(key, input.value);
				closePopover();
			});

			input.addEventListener('keydown', function (e) {
				if (e.key === 'Escape') {
					closePopover();
				} else if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
					e.preventDefault();
					applyValue(key, input.value);
					closePopover();
				}
			});
		}

		/* --- Live appearance (color / brand / tagline / logo) --------------- */
		function bindAppearance() {
			var colorInput = document.querySelector('[name="wc_force_auth_otp_primary_color"]');
			var cardBgInput = document.querySelector('[name="wc_force_auth_otp_card_bg"]');
			var inputBgInput = document.querySelector('[name="wc_force_auth_otp_input_bg"]');
			var brandInput = document.querySelector('[name="wc_force_auth_otp_brand_name"]');
			var taglineInput = document.querySelector('[name="wc_force_auth_otp_brand_tagline"]');
			var logoInput = document.querySelector('[name="wc_force_auth_otp_logo_url"]');

			// Server-rendered fallbacks (site icon / site name / tagline). These
			// are the values used when the corresponding input is empty, so the
			// preview never loses the brand when the plugin options are unset.
			function initialText(sel) {
				var el = editor.querySelector(sel);
				return el ? el.textContent.trim() : '';
			}
			var initialLogoEl = editor.querySelector('.wcfa-logo');
			var initialBrand = initialText('.wcfa-brand-name') || initialText('.wcfa-mail__brand');
			var initialTagline = initialText('.wcfa-tagline');
			var initialLogo = initialLogoEl ? (initialLogoEl.getAttribute('src') || '') : '';

			function luminance(hex) {
				hex = String(hex || '').replace('#', '');
				if (hex.length === 3) {
					hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
				}
				if (hex.length !== 6 || !/^[0-9a-f]{6}$/i.test(hex)) {
					return 0;
				}
				function ch(v) {
					v = v / 255;
					return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
				}
				var r = ch(parseInt(hex.substr(0, 2), 16));
				var g = ch(parseInt(hex.substr(2, 2), 16));
				var b = ch(parseInt(hex.substr(4, 2), 16));
				return 0.2126 * r + 0.7152 * g + 0.0722 * b;
			}

			function darken(hex, ratio) {
				hex = String(hex || '').replace('#', '');
				if (hex.length === 3) {
					hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
				}
				var out = '#';
				for (var i = 0; i < 3; i++) {
					var c = parseInt(hex.substr(i * 2, 2), 16) || 0;
					var v = Math.round(c * (1 - ratio));
					out += ('0' + Math.max(0, Math.min(255, v)).toString(16)).slice(-2);
				}
				return out;
			}

			function primaryInk(color) {
				if (luminance(color) <= 0.5) {
					return color;
				}
				for (var i = 1; i <= 20; i++) {
					var mixed = darken(color, i / 20);
					if (luminance(mixed) <= 0.2) {
						return mixed;
					}
				}
				return darken(color, 1);
			}

			function applyColor(value) {
				if (!value) {
					return;
				}

				var onPrimary = luminance(value) > 0.5 ? '#111111' : '#ffffff';
				var ink = primaryInk(value);

				editor.style.setProperty('--wcfa-primary', value);
				editor.style.setProperty('--wcfa-on-primary', onPrimary);
				editor.style.setProperty('--wcfa-primary-ink', ink);

				editor.querySelectorAll('.wcfa-otp-wrapper, .wcfa-account-auth').forEach(function (el) {
					el.style.setProperty('--wcfa-primary', value);
					el.style.setProperty('--wcfa-on-primary', onPrimary);
					el.style.setProperty('--wcfa-primary-ink', ink);
				});
			}

			// Radio toggle value (Show/Hide) by option name.
			function checkedToggle(name) {
				var el = document.querySelector('[name="' + name + '"]:checked');
				return el ? el.value : 'yes';
			}

			// Render the brand block (logo / name / tagline) from the current
			// inputs AND the Show/Hide toggles.
			function renderBrand() {
				var logoVal = (logoInput && logoInput.value) || initialLogo;
				var brandVal = (brandInput && brandInput.value) || initialBrand;
				var taglineVal = (taglineInput && taglineInput.value) || initialTagline;

				var showLogo = checkedToggle('wc_force_auth_otp_logo_show') !== 'no';
				var showBrand = checkedToggle('wc_force_auth_otp_brand_name_show') !== 'no';
				var showTagline = checkedToggle('wc_force_auth_otp_tagline_show') !== 'no';

				var useLogo = showLogo && logoVal;

				editor.querySelectorAll('.wcfa-logo, .wcfa-mail__logo').forEach(function (el) {
					el.setAttribute('src', logoVal);
					el.style.display = useLogo ? '' : 'none';
				});
				editor.querySelectorAll('.wcfa-brand-name, .wcfa-mail__brand').forEach(function (el) {
					el.textContent = brandVal;
					el.style.display = (showBrand && brandVal) ? '' : 'none';
				});
				editor.querySelectorAll('.wcfa-tagline').forEach(function (el) {
					el.textContent = taglineVal;
					el.style.display = (showTagline && taglineVal) ? '' : 'none';
				});
			}

			function bindInput(input, handler) {
				if (!input) {
					return;
				}
				// Only react to user edits. The initial state is rendered by the
				// server (including fallbacks like the site icon / site name that
				// are NOT stored in these inputs), so applying the empty input
				// value on load would wipe the logo/brand out of the preview.
				input.addEventListener('input', function () {
					handler(input.value);
				});
				input.addEventListener('change', function () {
					handler(input.value);
				});
			}

			function applyCardBg(value) {
				value = value || '#ffffff';
				var onCard = luminance(value) > 0.5 ? '#121519' : '#f5f7fa';
				var muted = luminance(value) > 0.5 ? '#6b7280' : '#c9cdd3';
				editor.querySelectorAll('.wcfa-otp-wrapper, .wcfa-account-auth').forEach(function (el) {
					el.style.setProperty('--wcfa-card-bg', value);
					el.style.setProperty('--wcfa-text', onCard);
					el.style.setProperty('--wcfa-muted', muted);
				});
			}

			function applyInputBg(value) {
				value = value || '#ffffff';
				var onInput = luminance(value) > 0.5 ? '#121519' : '#f5f7fa';
				editor.querySelectorAll('.wcfa-otp-wrapper, .wcfa-account-auth').forEach(function (el) {
					el.style.setProperty('--wcfa-input-bg', value);
					el.style.setProperty('--wcfa-on-input', onInput);
				});
			}

			bindInput(colorInput, applyColor);
			bindInput(cardBgInput, applyCardBg);
			bindInput(inputBgInput, applyInputBg);
			bindInput(brandInput, renderBrand);
			bindInput(taglineInput, renderBrand);
			bindInput(logoInput, renderBrand);

			['wc_force_auth_otp_logo_show', 'wc_force_auth_otp_brand_name_show', 'wc_force_auth_otp_tagline_show'].forEach(function (name) {
				document.querySelectorAll('[name="' + name + '"]').forEach(function (radio) {
					radio.addEventListener('change', renderBrand);
				});
			});
		}

		bindAppearance();

		/* --- Live verification-code preview -------------------------------- */
		function bindCodePreview() {
			var container = editor.querySelector('.wcfa-code-preview');
			if (!container) {
				return;
			}

			var lengthSel = document.querySelector('[name="wc_force_auth_otp_code_length"]');
			var inputSel = document.querySelector('[name="wc_force_auth_otp_code_input"]');
			var formatSel = document.querySelector('[name="wc_force_auth_otp_code_format"]');
			var mailCode = editor.querySelector('.wcfa-mail__code');

			var lockIcon = '<span class="wcfa-input-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg></span>';

			function groupSizes(len, format) {
				if (format === 'plain') {
					return [len];
				}
				if (format === 'pair') {
					var g = [];
					for (var i = 0; i < len; i += 2) {
						g.push(Math.min(2, len - i));
					}
					return g;
				}
				var first = Math.ceil(len / 2);
				var second = len - first;
				return second > 0 ? [first, second] : [first];
			}

			function render() {
				var len = parseInt(lengthSel ? lengthSel.value : '6', 10) || 6;
				var input = inputSel ? inputSel.value : 'single';
				var format = formatSel ? formatSel.value : 'plain';
				var sep = format === 'dash' ? '-' : ' ';
				var groups = groupSizes(len, format);

				container.innerHTML = input === 'segmented'
					? segmentedHtml(len, format, groups)
					: singleHtml(len, groups, sep);

				if (mailCode) {
					mailCode.textContent = '12345678'.slice(0, len);
				}
			}

			function segmentedHtml(len, format, groups) {
				var html = '<div class="wcfa-otp-boxes" data-code-length="' + len + '" data-code-format="' + format + '" role="group" aria-hidden="true">';
				groups.forEach(function (size, gi) {
					if (gi > 0) {
						html += '<span class="wcfa-otp-sep" aria-hidden="true"></span>';
					}
					for (var i = 0; i < size; i++) {
						html += '<input type="text" class="wcfa-otp-box" maxlength="1" disabled tabindex="-1">';
					}
				});
				return html + '</div>';
			}

			function singleHtml(len, groups, sep) {
				var placeholder = groups.map(function (size) {
					return new Array(size + 1).join('\u2022');
				}).join(sep);
				var maxlength = len + (groups.length - 1);

				return '<div class="wcfa-input-wrap wcfa-input-wrap--code">' + lockIcon +
					'<input type="text" class="wcfa-input wcfa-input--code" maxlength="' + maxlength +
					'" placeholder="' + placeholder + '" disabled tabindex="-1"></div>';
			}

			[lengthSel, inputSel, formatSel].forEach(function (sel) {
				if (sel) {
					sel.addEventListener('change', render);
					sel.addEventListener('input', render);
				}
			});

			render();
		}

		bindCodePreview();

		editor.addEventListener('click', function (e) {
			var pencil = e.target.closest('.wcfa-et-pencil');
			if (pencil) {
				e.preventDefault();
				openPopover(pencil);
				return;
			}

			if (popover && !e.target.closest('.wcfa-et-popover')) {
				closePopover();
			}
		});

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				closePopover();
			}
		});
	});
})();
