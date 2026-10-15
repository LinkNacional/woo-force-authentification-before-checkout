/**
 * OTP authentication flow (login request + code verification) on the account page.
 *
 * Notifications are rendered with SweetAlert2 (bundled). If the library is not
 * available the messages fall back to an inline status region.
 *
 * @package WcForceAuth
 */
(function () {
	'use strict';

	var cfg = window.wcForceAuthOtp || {};

	document.addEventListener('DOMContentLoaded', function () {
		var wrapper = document.querySelector('.wcfa-otp-wrapper');
		if (!wrapper) {
			return;
		}

		// Outer wrapper that may hold the register column alongside the OTP card.
		var authWrapper = wrapper.closest('.wcfa-account-auth') || wrapper.parentElement;

		var loginStep = wrapper.querySelector('.wcfa-step--login');
		var verifyStep = wrapper.querySelector('.wcfa-step--verify');
		var loginForm = document.getElementById('wcfa-login-form');
		var verifyForm = document.getElementById('wcfa-verify-form');
		var emailInput = document.getElementById('wcfa_email');
		var emailDisplay = document.getElementById('wcfa-email-display');
		var codeInput = document.getElementById('wcfa_code');
		var otpBoxes = document.getElementById('wcfa-otp-boxes');
		var codeBoxes = otpBoxes ? Array.prototype.slice.call(otpBoxes.querySelectorAll('.wcfa-otp-box')) : [];
		var segmented = codeBoxes.length > 0;
		var expiresEl = document.getElementById('wcfa-expires');
		var verifyBtn = document.getElementById('wcfa-verify-btn');
		var loginBtn = document.getElementById('wcfa-login-btn');
		var resendBtn = document.getElementById('wcfa-resend');
		var backBtn = document.getElementById('wcfa-back');
		var reportBtn = document.getElementById('wcfa-report');
		var messages = document.getElementById('wcfa-messages');
		var loginMessage = document.getElementById('wcfa-login-message');
		var verifyMessage = document.getElementById('wcfa-verify-message');

		var i18n = cfg.i18n || {};
		var codeLength = parseInt(cfg.codeLength, 10) || 6;
		var codeFormat = cfg.codeFormat || 'plain';
		var primaryColor = cfg.primaryColor || '#22C1DC';
		var inlineNotifications = cfg.notificationStyle === 'inline';
		var currentEmail = '';
		var timerHandle = null;

		function hasSwal() {
			return !!(window.Swal && typeof window.Swal.fire === 'function');
		}

		// Wrapper around Swal.fire() that disables the scrollbar-padding
		// compensation. On themes whose scrollbar lives on `html` (e.g. Woodmart,
		// which uses `overflow-x: clip/clip` on body), SweetAlert2's automatic
		// `padding-right` on `<body>` does not compensate for anything — the
		// document scrollbar never disappears — so it only shrinks the page
		// content by the scrollbar width, giving the "squeezed" flick on open.
		function swalFire(options) {
			return window.Swal.fire(Object.assign({ scrollbarPadding: false }, options || {}));
		}

		function escapeHtml(value) {
			return String(value == null ? '' : value)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#039;');
		}

		// Message region of the active step (below the email or the code input);
		// falls back to the shared region when neither step is available.
		function activeMessageEl() {
			if (verifyStep && !verifyStep.hidden && verifyMessage) {
				return verifyMessage;
			}
			if (loginStep && !loginStep.hidden && loginMessage) {
				return loginMessage;
			}
			return messages;
		}

		function clearMessages() {
			[messages, loginMessage, verifyMessage].forEach(function (el) {
				if (el) {
					el.innerHTML = '';
				}
			});
		}

		// Inline message region, used as a fallback and as the primary channel in
		// "inline" notification mode.
		function setMessage(text, type) {
			var target = activeMessageEl();
			if (!target) {
				return;
			}

			// Always clear the other steps so a stale message never lingers.
			[messages, loginMessage, verifyMessage].forEach(function (el) {
				if (el && el !== target) {
					el.innerHTML = '';
				}
			});

			if (!text) {
				target.innerHTML = '';
				return;
			}

			target.innerHTML = '<div class="wcfa-msg wcfa-msg--' + (type || 'info') + '"></div>';
			target.firstChild.textContent = text;

			if (inlineNotifications && target.scrollIntoView) {
				target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			}
		}

		// Non-blocking notification. In "inline" mode everything is rendered inside
		// the component; otherwise it uses floating SweetAlert2 toasts/modals.
		// `forceToast` keeps even errors as a toast (used for the native
		// registration notices, which should not block the page). `toastTimer`
		// overrides the auto-dismiss time (ms) for the toast variant.
		function notify(type, message, forceToast, toastTimer) {
			if (!message) {
				return;
			}

			if (inlineNotifications) {
				setMessage(message, type);
				return;
			}

			if (!hasSwal()) {
				setMessage(message, type);
				return;
			}

			if (type === 'error' && !forceToast) {
				swalFire({
					icon: 'error',
					title: i18n.errorTitle || 'Error',
					text: message,
					confirmButtonColor: primaryColor,
					customClass: { popup: 'wcfa-swal-modal' },
				});
				return;
			}

			swalFire({
				toast: true,
				position: 'top-end',
				icon: type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info'),
				title: message,
				showConfirmButton: false,
				timer: toastTimer || (type === 'success' ? 12000 : 6000),
				timerProgressBar: true,
				customClass: { popup: 'wcfa-swal-toast' },
			});
		}

		function showLoading(text) {
			if (!hasSwal()) {
				setMessage(text, 'info');
				return;
			}

			swalFire({
				title: text,
				allowOutsideClick: false,
				allowEscapeKey: false,
				showConfirmButton: false,
				customClass: { popup: 'wcfa-swal-modal' },
				didOpen: function () {
					window.Swal.showLoading();
				},
			});
		}

		function closeLoading() {
			if (hasSwal() && window.Swal.isVisible()) {
				window.Swal.close();
			}
		}

		function successThenRedirect(message, target) {
			if (!inlineNotifications && hasSwal()) {
				swalFire({
					icon: 'success',
					title: message,
					showConfirmButton: false,
					timer: 1300,
					timerProgressBar: true,
					customClass: { popup: 'wcfa-swal-modal' },
				}).then(function () {
					window.location.href = target;
				});
				return;
			}

			setMessage(message, 'success');
			setTimeout(function () {
				window.location.href = target;
			}, 800);
		}

		// Server-rendered WooCommerce notices (e.g. the native registration
		// errors shown after a non-AJAX submit) follow the same notification
		// style as the OTP flow: in "floating" mode they are shown through
		// SweetAlert2 and the static WooCommerce notice box is hidden.
		function bridgeServerNotices() {
			if (inlineNotifications || !hasSwal()) {
				return;
			}

			var wrapper = document.querySelector('.woocommerce-notices-wrapper');
			if (!wrapper) {
				return;
			}

			var groups = [
				{ selector: '.woocommerce-error', type: 'error' },
				{ selector: '.woocommerce-message', type: 'success' },
				{ selector: '.woocommerce-info', type: 'info' },
			];
			var bridged = false;

			groups.forEach(function (group) {
				var texts = [];

				Array.prototype.forEach.call(wrapper.querySelectorAll(group.selector), function (list) {
					var items = list.querySelectorAll('li');
					var nodes = items.length ? items : [list];

					Array.prototype.forEach.call(nodes, function (node) {
						var text = (node.textContent || '').replace(/\s+/g, ' ').trim();

						if (text) {
							texts.push(text);
						}
					});
				});

				if (texts.length) {
					bridged = true;
					notify(group.type, texts.join(' '), true);
				}
			});

			if (bridged) {
				wrapper.style.display = 'none';
			}
		}

		function post(path, body) {
			var payload = Object.assign({ nonce: cfg.nonce }, body);

			return fetch(cfg.restUrl + path, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				credentials: 'same-origin',
				body: JSON.stringify(payload),
			}).then(function (response) {
				return response.json().then(function (data) {
					return { ok: response.ok, status: response.status, data: data || {} };
				}).catch(function () {
					return { ok: response.ok, status: response.status, data: {} };
				});
			});
		}

		function messageFrom(result) {
			if (result.data && result.data.message) {
				return result.data.message;
			}
			return i18n.connection || 'Connection error.';
		}

		function setResendReady() {
			if (!resendBtn) {
				return;
			}
			resendBtn.disabled = false;
			resendBtn.classList.remove('wcfa-btn--muted');
			resendBtn.textContent = i18n.resendButton || 'Resend code';
		}

		function setResendCountdown(seconds) {
			if (!resendBtn) {
				return;
			}
			resendBtn.disabled = true;
			resendBtn.classList.add('wcfa-btn--muted');
			resendBtn.textContent = (i18n.resendIn || 'You can resend in %ds').replace('%d', seconds);
		}

		function startTimer(seconds) {
			if (timerHandle) {
				clearInterval(timerHandle);
				timerHandle = null;
			}

			seconds = parseInt(seconds, 10) || 0;

			if (seconds <= 0) {
				setResendReady();
				return;
			}

			// The button is always shown: gray with the countdown text inside while
			// the interval runs, then brand-colored with the "Resend code" label.
			var remaining = seconds;

			function tick() {
				if (remaining <= 0) {
					clearInterval(timerHandle);
					timerHandle = null;
					setResendReady();
					return;
				}

				setResendCountdown(remaining);
				remaining -= 1;
			}

			tick();
			timerHandle = setInterval(tick, 1000);
		}

		// Show the code expiration time (formatted server-side with wp_date).
		function setExpires(label) {
			if (!expiresEl) {
				return;
			}

			if (!label) {
				expiresEl.hidden = true;
				expiresEl.textContent = '';
				return;
			}

			expiresEl.textContent = (i18n.expiresAt || 'Code expires at %s').replace('%s', label);
			expiresEl.hidden = false;
		}

		// Inline loading state for a button (spinner + disabled). Turning it off
		// only removes the spinner — the caller decides the disabled state.
		function setButtonLoading(button, on) {
			if (!button) {
				return;
			}

			button.classList.toggle('wcfa-loading', !!on);

			if (on) {
				button.disabled = true;
			}
		}

		function goToVerify(email, resendIn, expiresLabel) {
			currentEmail = email;

			if (emailDisplay) {
				emailDisplay.textContent = email;
			}
			if (loginStep) {
				loginStep.hidden = true;
			}
			if (verifyStep) {
				verifyStep.hidden = false;
			}

			// The register column does not belong to the verification step, so it
			// is hidden and the OTP card takes the full width (centered).
			if (authWrapper) {
				authWrapper.classList.add('wcfa-account-auth--verify');
			}

			clearCode();
			focusCode();
			if (verifyBtn) {
				verifyBtn.disabled = true;
			}

			clearMessages();
			setExpires(expiresLabel);
			startTimer(resendIn);
			scheduleClipboardCheck();
		}

		function sendCode(email) {
			return post('/send-code', { email: email });
		}

		function isValidEmail(value) {
			return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim());
		}

		// Highlight the field + its submit button once a valid email is typed, so
		// the button stands out in the brand color instead of looking disabled.
		function bindEmailState(input, wrap, button) {
			if (!input) {
				return;
			}

			function update() {
				var valid = isValidEmail(input.value);

				if (wrap) {
					wrap.classList.toggle('wcfa-valid', valid);
				}
				if (button) {
					button.classList.toggle('wcfa-btn--muted', !valid);
				}
			}

			input.addEventListener('input', update);
			input.addEventListener('change', update);
			update();
		}

		// Password strength gate for the native registration form: the submit is
		// only enabled when the email is valid, the required fields are filled and
		// the password reaches the strength WooCommerce requires (respecting the
		// store's `woocommerce_min_password_strength` setting).
		function bindRegisterState() {
			var registerForm = document.querySelector('.wcfa-account-register .woocommerce-form-register');
			if (!registerForm) {
				return;
			}

			var emailField = registerForm.querySelector('#reg_email');
			var passwordField = registerForm.querySelector('#reg_password');
			var usernameField = registerForm.querySelector('#reg_username');
			var submit = registerForm.querySelector('.woocommerce-form-register__submit');
			var emailWrap = registerForm.querySelector('#wcfa-reg-email-wrap');
			var passwordBox = passwordField ? passwordField.closest('.wcfa-password') : null;

			var minStrength = 3;
			if (window.wc_password_strength_meter_params && window.wc_password_strength_meter_params.min_password_strength) {
				minStrength = parseInt(window.wc_password_strength_meter_params.min_password_strength, 10) || 3;
			}

			function passwordStrength() {
				if (!passwordField) {
					return Infinity; // No password field => nothing to gate on.
				}

				var value = passwordField.value || '';
				if ('' === value) {
					return 0;
				}

				if (window.wp && window.wp.passwordStrength && typeof window.wp.passwordStrength.meter === 'function') {
					var disallowed = window.wp.passwordStrength.userInputDisallowedList ? window.wp.passwordStrength.userInputDisallowedList() : [];
					return window.wp.passwordStrength.meter(value, disallowed);
				}

				return 0;
			}

			function update() {
				var emailOk = emailField ? isValidEmail(emailField.value) : false;
				var usernameOk = usernameField ? ('' !== String(usernameField.value || '').trim()) : true;
				var passwordOk = passwordField ? (passwordStrength() >= minStrength) : true;
				var ready = emailOk && usernameOk && passwordOk;

				if (emailWrap) {
					emailWrap.classList.toggle('wcfa-valid', emailOk);
				}
				if (passwordBox) {
					passwordBox.classList.toggle('wcfa-valid', passwordOk);
				}
				if (submit) {
					submit.disabled = !ready;
					submit.classList.toggle('wcfa-btn--muted', !ready);
				}
			}

			[emailField, passwordField, usernameField].forEach(function (field) {
				if (field) {
					field.addEventListener('input', update);
					field.addEventListener('change', update);
					field.addEventListener('keyup', update);
					field.addEventListener('blur', update);
				}
			});

			// Native submit: show the loading spinner. The button is NOT disabled
			// on purpose — WooCommerce's process_registration() requires the
			// `register` field to be present in the POST, and a disabled submit
			// button is excluded from the form data.
			registerForm.addEventListener('submit', function () {
				if (submit) {
					submit.classList.remove('wcfa-btn--muted');
					submit.classList.add('wcfa-loading');
				}
			});

			update();
		}

		// Password strength feedback follows the notification style. In "floating"
		// (toast) mode the inline WooCommerce meter is hidden and each strength
		// change is surfaced as a SweetAlert2 toast; in "inline" mode the meter
		// stays visible in place (like the OTP inline messages).
		function bridgePasswordStrength() {
			if (inlineNotifications) {
				return;
			}

			var registerForm = document.querySelector('.wcfa-account-register .woocommerce-form-register');
			if (!registerForm) {
				return;
			}

			var passwordBox = registerForm.querySelector('.wcfa-password');
			if (!passwordBox) {
				return;
			}

			var lastMessage = '';

			function sync() {
				var meter = passwordBox.querySelector('.woocommerce-password-strength');
				if (!meter) {
					return;
				}

				// Keep the inline box hidden; feedback goes to the toast.
				if (!meter.classList.contains('wcfa-meter-hidden')) {
					meter.classList.add('wcfa-meter-hidden');
				}

				// WooCommerce toggles the inline style to hide the meter while the
				// field is empty.
				if ('none' === meter.style.display) {
					lastMessage = '';
					return;
				}

				var text = (meter.textContent || '').replace(/\s+/g, ' ').trim();
				if (!text || text === lastMessage) {
					return;
				}

				lastMessage = text;

				var strong = meter.classList.contains('good') || meter.classList.contains('strong');
				notify(strong ? 'success' : 'error', text, true, 4000);
			}

			var observer = new MutationObserver(sync);
			observer.observe(passwordBox, {
				childList: true,
				subtree: true,
				characterData: true,
				attributes: true,
				attributeFilter: ['class', 'style'],
			});

			sync();
		}

		function applyCode(code) {
			if (!codeInput && !segmented) {
				return;
			}

			fillCode(code);

			if (verifyForm) {
				if (typeof verifyForm.requestSubmit === 'function') {
					verifyForm.requestSubmit();
				} else {
					verifyForm.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
				}
			}
		}

		// ── Code value helpers (single field or segmented boxes) ──────────────

		function readCodeValue() {
			if (segmented) {
				return codeBoxes.map(function (box) {
					return box.value.replace(/\D/g, '');
				}).join('').slice(0, codeLength);
			}

			return codeInput ? digitsOnly(codeInput.value) : '';
		}

		function clearCode() {
			if (segmented) {
				codeBoxes.forEach(function (box) {
					box.value = '';
				});
			} else if (codeInput) {
				codeInput.value = '';
			}
		}

		function focusCode() {
			if (segmented && codeBoxes.length) {
				codeBoxes[0].focus();
			} else if (codeInput) {
				codeInput.focus();
			}
		}

		// Fill the code programmatically (single field or segmented boxes).
		function fillCode(code) {
			var digits = digitsOnly(code);

			if (segmented) {
				codeBoxes.forEach(function (box, i) {
					box.value = digits.charAt(i) || '';
				});
			} else if (codeInput) {
				codeInput.value = formatCodeValue(digits);
			}

			updateVerifyButton();
		}

		function updateVerifyButton() {
			if (verifyBtn) {
				verifyBtn.disabled = readCodeValue().length !== codeLength;
			}
		}

		function digitsOnly(value) {
			return String(value == null ? '' : value).replace(/\D/g, '').slice(0, codeLength);
		}

		function codeGroupSizes() {
			if (codeFormat === 'plain') {
				return [codeLength];
			}

			if (codeFormat === 'pair') {
				var groups = [];
				for (var i = 0; i < codeLength; i += 2) {
					groups.push(Math.min(2, codeLength - i));
				}
				return groups;
			}

			var first = Math.ceil(codeLength / 2);
			var second = codeLength - first;
			return second > 0 ? [first, second] : [first];
		}

		function codeSeparator() {
			return codeFormat === 'dash' ? '-' : ' ';
		}

		// Turns raw digits into the display value with separators inserted.
		function formatCodeValue(value) {
			var digits = digitsOnly(value);

			if (codeFormat === 'plain') {
				return digits;
			}

			var sizes = codeGroupSizes();
			var sep = codeSeparator();
			var out = '';
			var index = 0;

			for (var i = 0; i < sizes.length; i++) {
				var chunk = digits.slice(index, index + sizes[i]);
				if (i > 0 && chunk.length) {
					out += sep;
				}
				out += chunk;
				index += sizes[i];
			}

			return out;
		}

		// A code detected in the clipboard, so we only offer each one once.
		var lastOfferedCode = '';

		// "Didn't receive the code?" — open a report popup that emails the admins.
		function openReport() {
			var prefill = (i18n.reportPrefill || 'I did not receive the verification code for {{email}}.')
				.replace('{{email}}', currentEmail || '');

			var formHtml =
				'<label class="wcfa-report-field">' +
					'<span>' + escapeHtml(i18n.reportEmail || 'Your email') + '</span>' +
					'<input id="wcfa-report-email" class="wcfa-report-input" type="email" value="' + escapeHtml(currentEmail || '') + '" />' +
				'</label>' +
				'<label class="wcfa-report-field">' +
					'<span>' + escapeHtml(i18n.reportContact || 'Contact (optional)') + '</span>' +
					'<input id="wcfa-report-contact" class="wcfa-report-input" type="text" value="" />' +
				'</label>' +
				'<label class="wcfa-report-field">' +
					'<span>' + escapeHtml(i18n.reportMessage || 'Message') + '</span>' +
					'<textarea id="wcfa-report-message" class="wcfa-report-textarea">' + escapeHtml(prefill) + '</textarea>' +
				'</label>';

			function submitReport(payload) {
				showLoading(i18n.sending || 'Sending...');

				post('/report-code', payload)
					.then(function (result) {
						closeLoading();

						if (result.data && result.data.success) {
							notify('success', result.data.message || '');
						} else {
							notify('error', messageFrom(result));
						}
					})
					.catch(function () {
						closeLoading();
						notify('error', i18n.connection || 'Connection error.');
					});
			}

			if (!hasSwal()) {
				// Minimal fallback without SweetAlert2.
				var message = window.prompt(i18n.reportMessage || 'Message', prefill);

				if (message !== null) {
					submitReport({ email: currentEmail, contact: '', message: message });
				}
				return;
			}

			swalFire({
				title: i18n.reportTitle || 'Report a problem',
				html: formHtml,
				width: 'min(560px, 92vw)',
				focusConfirm: false,
				showCancelButton: true,
				confirmButtonText: i18n.reportSend || 'Send',
				cancelButtonText: i18n.cancel || 'Cancel',
				confirmButtonColor: primaryColor,
				customClass: { popup: 'wcfa-swal-modal wcfa-report-modal' },
				didOpen: function (popup) {
					popup.style.setProperty('--wcfa-primary', primaryColor);
				},
				preConfirm: function () {
					var emailEl = document.getElementById('wcfa-report-email');
					var contactEl = document.getElementById('wcfa-report-contact');
					var messageEl = document.getElementById('wcfa-report-message');
					var email = (emailEl && emailEl.value || '').trim();
					var message = (messageEl && messageEl.value || '').trim();

					if (!isValidEmail(email)) {
						window.Swal.showValidationMessage(i18n.invalidEmail || 'Invalid email.');
						return false;
					}
					if (!message) {
						window.Swal.showValidationMessage(i18n.reportMessage || 'Message');
						return false;
					}

					return {
						email: email,
						contact: (contactEl && contactEl.value || '').trim(),
						message: message,
					};
				},
			}).then(function (result) {
				if (result.isConfirmed && result.value) {
					submitReport(result.value);
				}
			});
		}

		function codeFromClipboard(text) {
			if (!text) {
				return '';
			}

			// Accept the raw code and any separator-grouped variant (e.g. copied
			// straight from the formatted field: "123-456" or "12 34 56").
			var trimmed = String(text).trim();

			if (!/^[\d\s-]+$/.test(trimmed)) {
				return '';
			}

			var digits = trimmed.replace(/\D/g, '');

			return digits.length === codeLength ? digits : '';
		}

		// Minimum viewport width from which the "code detected" toast is glued to
		// the OTP card's bottom-right corner. Below it (phones) the toast keeps
		// SweetAlert2's default edge position.
		var CLIPBOARD_TOAST_ANCHOR_MIN_WIDTH = 768;

		// Anchor the open "code detected" toast to the bottom-right corner of the
		// OTP card on tablet/desktop. The toast container is `position: fixed`
		// with `inset: auto 0 0 auto`, so overriding `right`/`bottom` moves it.
		// Returns a cleanup that detaches the scroll/resize listeners.
		function anchorClipboardToast(popup) {
			if (!popup || !wrapper) {
				return function () {};
			}

			var container = popup.closest('.swal2-container');
			if (!container) {
				return function () {};
			}

			function apply() {
				if (window.innerWidth < CLIPBOARD_TOAST_ANCHOR_MIN_WIDTH) {
					container.style.right = '';
					container.style.bottom = '';
					return;
				}

				var card = wrapper.querySelector('.wcfa-otp-card') || wrapper;
				var rect = card.getBoundingClientRect();
				if (!rect.width || !rect.height) {
					return;
				}

				// The toast sits inside the fixed container with a small inner
				// padding; offset by it so the POPUP (not the container) ends up
				// flush with the card's bottom-right corner. Read it from the
				// computed style so the show animation (which transforms the
				// popup) never skews the measurement.
				var cs = window.getComputedStyle(container);
				var padRight = parseFloat(cs.paddingRight) || 0;
				var padBottom = parseFloat(cs.paddingBottom) || 0;

				var clientW = document.documentElement.clientWidth;
				var clientH = document.documentElement.clientHeight;

				var right = clientW - rect.right - padRight;
				var bottom = clientH - rect.bottom - padBottom;

				container.style.right = Math.max(0, Math.round(right)) + 'px';
				container.style.bottom = Math.max(0, Math.round(bottom)) + 'px';
			}

			apply();
			window.addEventListener('scroll', apply, { passive: true });
			window.addEventListener('resize', apply);

			return function cleanup() {
				window.removeEventListener('scroll', apply);
				window.removeEventListener('resize', apply);
			};
		}

		var cleanupClipboardAnchor = null;

		// Proactively offer a code found in the clipboard (e.g. after the customer
		// copies it from their email and comes back to the tab).
		function offerCode(code) {
			if (!code || code === lastOfferedCode) {
				return;
			}
			lastOfferedCode = code;

			if (!hasSwal()) {
				setMessage((i18n.clipboardText || 'We found your access code in the clipboard.'), 'info');
				return;
			}

			swalFire({
				toast: true,
				position: 'bottom-end',
				icon: 'info',
				title: i18n.clipboardTitle || 'Code detected',
				html: '<strong style="letter-spacing:6px;font-size:20px;">' + escapeHtml(code) + '</strong>',
				showConfirmButton: true,
				confirmButtonText: i18n.useCode || 'Use code',
				confirmButtonColor: primaryColor,
				showCloseButton: true,
				timer: 60000,
				timerProgressBar: true,
				customClass: { popup: 'wcfa-swal-toast' },
				didOpen: function (popup) {
					popup.style.setProperty('--wcfa-primary', primaryColor);
					if (cleanupClipboardAnchor) {
						cleanupClipboardAnchor();
					}
					cleanupClipboardAnchor = anchorClipboardToast(popup);
				},
				willClose: function () {
					if (cleanupClipboardAnchor) {
						cleanupClipboardAnchor();
						cleanupClipboardAnchor = null;
					}
				},
			}).then(function (result) {
				if (result.isConfirmed) {
					applyCode(code);
				}
			});
		}

		function checkClipboard() {
			if (!verifyStep || verifyStep.hidden) {
				return;
			}
			if (document.hasFocus && !document.hasFocus()) {
				return;
			}
			if (!navigator.clipboard || typeof navigator.clipboard.readText !== 'function') {
				return;
			}

			navigator.clipboard.readText().then(function (text) {
				var code = codeFromClipboard(text);
				if (code) {
					offerCode(code);
				}
			}).catch(function () {
				// Permission not granted or unsupported — silently ignore.
			});
		}

		var clipboardTimer = null;

		function scheduleClipboardCheck() {
			if (clipboardTimer) {
				clearTimeout(clipboardTimer);
			}
			// Small delay so the tab gains focus before reading the clipboard.
			clipboardTimer = setTimeout(checkClipboard, 350);
		}

		window.addEventListener('focus', scheduleClipboardCheck);
		document.addEventListener('visibilitychange', function () {
			if (!document.hidden) {
				scheduleClipboardCheck();
			}
		});

		// Some browsers only allow reading the clipboard within a user gesture;
		// any interaction on the OTP screen is a good moment to re-check.
		wrapper.addEventListener('click', scheduleClipboardCheck);

		// Email field: OTP login step.
		bindEmailState(emailInput, document.getElementById('wcfa-email-wrap'), loginBtn);

		// Native registration form (login-only): gate the submit on a valid email
		// plus an accepted password strength.
		bindRegisterState();
		bridgePasswordStrength();

		if (loginForm) {
			loginForm.addEventListener('submit', function (e) {
				e.preventDefault();

				var email = (emailInput && emailInput.value || '').trim();

				if (!isValidEmail(email)) {
					notify('error', i18n.invalidEmail || 'Invalid email.');
					return;
				}

				setButtonLoading(loginBtn, true);

				sendCode(email)
					.then(function (result) {
						if (result.data && result.data.success) {
							if (!inlineNotifications) {
								notify('success', result.data.message || '');
							}
							goToVerify(result.data.email || email, result.data.resend_in, result.data.expires_label);
						} else {
							notify('error', messageFrom(result));
						}
					})
					.catch(function () {
						notify('error', i18n.connection || 'Connection error.');
					})
					.finally(function () {
						setButtonLoading(loginBtn, false);
						if (loginBtn) {
							loginBtn.disabled = false;
						}
					});
			});
		}

		if (resendBtn) {
			resendBtn.addEventListener('click', function () {
				if (resendBtn.disabled) {
					return;
				}

				resendBtn.classList.add('wcfa-btn--muted');
				setButtonLoading(resendBtn, true);
				sendCode(currentEmail)
					.then(function (result) {
						if (result.data && result.data.success) {
							notify('success', result.data.message || '');
							setExpires(result.data.expires_label);
							startTimer(result.data.resend_in);
						} else {
							notify('error', messageFrom(result));

							if (result.data && result.data.resend_in) {
								startTimer(result.data.resend_in);
							} else {
								setResendReady();
							}
						}
					})
					.catch(function () {
						notify('error', i18n.connection || 'Connection error.');
						setResendReady();
					})
					.finally(function () {
						setButtonLoading(resendBtn, false);
					});
			});
		}

		if (codeInput) {
			codeInput.addEventListener('input', function () {
				var digits = digitsOnly(codeInput.value);

				codeInput.value = formatCodeValue(digits);

				if (verifyBtn) {
					verifyBtn.disabled = digits.length !== codeLength;
				}
			});
		}

		// Segmented (one box per digit) behavior: keep one digit per box, move
		// focus forward on input, back on backspace, and support paste.
		if (segmented) {
			function focusBox(index) {
				if (index >= 0 && index < codeBoxes.length) {
					codeBoxes[index].focus();
					codeBoxes[index].select();
				}
			}

			function boxIndexOf(box) {
				return codeBoxes.indexOf(box);
			}

			function fillBoxesFrom(startIndex, digits) {
				var i = startIndex;

				for (var d = 0; d < digits.length && i < codeBoxes.length; d++, i++) {
					codeBoxes[i].value = digits.charAt(d);
				}

				updateVerifyButton();
				focusBox(Math.min(i, codeBoxes.length - 1));
			}

			codeBoxes.forEach(function (box, index) {
				box.addEventListener('input', function () {
					var digits = box.value.replace(/\D/g, '');

					if (digits.length > 1) {
						// Multiple digits (e.g. autofill/drop): distribute them.
						box.value = '';
						fillBoxesFrom(index, digits);
						return;
					}

					box.value = digits;
					updateVerifyButton();

					if (digits) {
						focusBox(index + 1);
					}
				});

				box.addEventListener('keydown', function (e) {
					if (e.key === 'Backspace' && !box.value) {
						e.preventDefault();
						focusBox(index - 1);
						if (codeBoxes[index - 1]) {
							codeBoxes[index - 1].value = '';
							updateVerifyButton();
						}
					} else if (e.key === 'ArrowLeft') {
						e.preventDefault();
						focusBox(index - 1);
					} else if (e.key === 'ArrowRight') {
						e.preventDefault();
						focusBox(index + 1);
					}
				});

				box.addEventListener('paste', function (e) {
					var clipboard = e.clipboardData || window.clipboardData;
					var text = clipboard ? clipboard.getData('text') : '';
					var digits = String(text || '').replace(/\D/g, '');

					if (digits) {
						e.preventDefault();
						fillBoxesFrom(boxIndexOf(box), digits.slice(0, codeLength - boxIndexOf(box)));
					}
				});

				box.addEventListener('focus', function () {
					box.select();
				});
			});
		}

		if (backBtn) {
			backBtn.addEventListener('click', function () {
				function reset() {
					if (timerHandle) {
						clearInterval(timerHandle);
						timerHandle = null;
					}

					if (verifyStep) {
						verifyStep.hidden = true;
					}
					if (loginStep) {
						loginStep.hidden = false;
					}

					// Back to the login step: bring the register column back.
					if (authWrapper) {
						authWrapper.classList.remove('wcfa-account-auth--verify');
					}

					clearCode();
					if (verifyBtn) {
						verifyBtn.disabled = true;
					}
					if (emailInput) {
						emailInput.focus();
					}
					setMessage('');
				}

				if (!hasSwal()) {
					if (window.confirm(i18n.confirmBack || 'Go back?')) {
						reset();
					}
					return;
				}

				swalFire({
					icon: 'question',
					title: i18n.confirmBack || 'Go back?',
					showCancelButton: true,
					confirmButtonText: i18n.back || 'Yes',
					cancelButtonText: i18n.cancel || 'Cancel',
				}).then(function (result) {
					if (result.isConfirmed) {
						reset();
					}
				});
			});
		}

		if (reportBtn) {
			reportBtn.addEventListener('click', function () {
				openReport();
			});
		}

		if (verifyForm) {
			verifyForm.addEventListener('submit', function (e) {
				e.preventDefault();

				var code = readCodeValue();

				if (code.length !== codeLength) {
					return;
				}

				setButtonLoading(verifyBtn, true);

				post('/verify-code', { email: currentEmail, code: code })
					.then(function (result) {
						if (result.data && result.data.success) {
							// The post-login destination comes from the single,
							// shared redirect configuration (Settings screen).
							successThenRedirect(result.data.message || '', result.data.redirect);
						} else {
							notify('error', messageFrom(result));
							setButtonLoading(verifyBtn, false);
							if (verifyBtn) {
								verifyBtn.disabled = false;
							}
						}
					})
					.catch(function () {
						notify('error', i18n.connection || 'Connection error.');
						setButtonLoading(verifyBtn, false);
						if (verifyBtn) {
							verifyBtn.disabled = false;
						}
					});
			});
		}

		// Surface any notice WooCommerce printed on page load (e.g. a native
		// registration error) through the same notification system.
		bridgeServerNotices();
	});
})();
