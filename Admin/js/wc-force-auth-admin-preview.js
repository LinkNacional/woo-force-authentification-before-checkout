/**
 * Preview modal for the OTP settings page.
 *
 * The "Visualizar" links shown beside the Appearance fields open the visual
 * text editor (the "Texts" tab content) inside a modal, so the store owner can
 * check how the appearance is turning out without leaving the current tab.
 *
 * The real editor element is MOVED into the modal (not cloned), so inline
 * editing, the live color/brand bindings and the hidden inputs (saved by the
 * settings form) all keep working. It is moved back to its original spot when
 * the modal closes.
 *
 * @package WcForceAuth
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var modal = document.querySelector('.wcfa-preview-modal');
		var editor = document.querySelector('.wcfa-editor');

		if (!modal || !editor) {
			return;
		}

		var modalBody = modal.querySelector('.wcfa-preview-modal__body');
		var home = null; // Comment node marking the editor's original position.

		function scrollbarWidth() {
			return Math.max(0, window.innerWidth - document.documentElement.clientWidth);
		}

		function openModal() {
			if (modal.classList.contains('is-open')) {
				return;
			}

			// Remember where the editor lives so it can be restored on close.
			if (!home) {
				home = document.createComment('wcfa-editor-home');
				editor.parentNode.insertBefore(home, editor);
				modalBody.appendChild(editor);
			}

			// Compensate for the vertical scrollbar we are about to hide so the
			// page behind does not shift sideways (a few px "misalignment").
			modal.style.setProperty('--wcfa-scrollbar-comp', scrollbarWidth() + 'px');

			modal.hidden = false;
			modal.setAttribute('aria-hidden', 'false');
			modal.classList.add('is-open');
			document.body.classList.add('wcfa-preview-modal-open');

			var closeBtn = modal.querySelector('.wcfa-preview-modal__close');
			if (closeBtn) {
				closeBtn.focus();
			}
		}

		function closeModal() {
			if (!modal.classList.contains('is-open')) {
				return;
			}

			if (home && home.parentNode) {
				home.parentNode.insertBefore(editor, home);
				home.parentNode.removeChild(home);
				home = null;
			}

			modal.hidden = true;
			modal.setAttribute('aria-hidden', 'true');
			modal.classList.remove('is-open');
			document.body.classList.remove('wcfa-preview-modal-open');
		}

		document.addEventListener('click', function (event) {
			var target = event.target;

			if (target.closest('.wcfa-preview-open')) {
				event.preventDefault();
				openModal();
				return;
			}

			if (target.closest('[data-wcfa-preview-close]')) {
				event.preventDefault();
				closeModal();
			}
		});

		document.addEventListener('keydown', function (event) {
			if ('Escape' === event.key && modal.classList.contains('is-open')) {
				closeModal();
			}
		});
	});
})();
