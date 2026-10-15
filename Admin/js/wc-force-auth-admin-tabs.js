/**
 * Handles the settings page tab navigation (block switching + nav carousel).
 *
 * @package WcForceAuth
 */
(function ($) {
	'use strict';

	$(document).ready(function () {
		var $outer = $('.admin-layout-top-menu-outer');
		var $clip = $outer.find('.admin-layout-top-menu-clip');
		var $nav = $clip.find('.admin-layout-top-menu');
		var $prev = $outer.find('.admin-layout-nav-arrow--prev');
		var $next = $outer.find('.admin-layout-nav-arrow--next');
		var offset = 0;

		if (!$outer.length) {
			return;
		}

		function getMaxOffset() {
			return Math.max(0, $nav[0].scrollWidth - $clip[0].clientWidth);
		}

		function applyOffset(newOffset) {
			var max = getMaxOffset();
			offset = Math.max(0, Math.min(newOffset, max));
			$nav[0].style.transform = 'translateX(-' + offset + 'px)';
			updateArrows();
		}

		function updateArrows() {
			var overflows = getMaxOffset() > 0;
			$outer.toggleClass('has-overflow', overflows);

			if (overflows) {
				$prev.prop('disabled', offset <= 0);
				$next.prop('disabled', offset >= getMaxOffset() - 1);
			}
		}

		function scrollTabIntoView(tabId) {
			var $tab = $('#nav-' + tabId);
			if (!$tab.length) {
				return;
			}

			var tabLeft = $tab[0].offsetLeft;
			var tabRight = tabLeft + $tab[0].offsetWidth;
			var clipW = $clip[0].clientWidth;

			if (tabLeft < offset) {
				applyOffset(tabLeft);
			} else if (tabRight > offset + clipW) {
				applyOffset(tabRight - clipW);
			}
		}

		$prev.on('click', function () {
			applyOffset(offset - Math.round($clip[0].clientWidth * 0.6));
		});

		$next.on('click', function () {
			applyOffset(offset + Math.round($clip[0].clientWidth * 0.6));
		});

		$(window).on('resize', function () {
			applyOffset(offset);
		});

		updateArrows();

		function switchBlock(tabId) {
			var $link = $('#nav-' + tabId);
			if (!$link.length) {
				return;
			}

			$('.admin-layout-title-link').removeClass('active');
			$link.addClass('active');
			$('.admin-layout-block').removeClass('active');
			$('#block-' + tabId).addClass('active');
		}

		function activateTab(tabId) {
			scrollTabIntoView(tabId);
			switchBlock(tabId);
		}

		$('.admin-layout-title-link').on('click', function (e) {
			e.preventDefault();
			activateTab($(this).attr('id').replace('nav-', ''));
		});

		$(document).on('click', '.wcfa-goto-tab', function (e) {
			e.preventDefault();
			activateTab($(this).data('tab'));
		});

		var params = new URLSearchParams(window.location.search);
		var requested = params.get('wcfa_tab');
		var $active = $('.admin-layout-title-link.active');

		if (requested && $('#nav-' + requested).length) {
			activateTab(requested);
		} else if ($active.length) {
			activateTab($active.attr('id').replace('nav-', ''));
		}
	});
})(jQuery);
