/**
 * "Use the 'My account' page" buttons on the settings screens.
 *
 * Some fields are URLs the store already has (its account page, for instance),
 * and copying them by hand is a good way to get a typo'd address that silently
 * sends customers nowhere. A field flagged with `fill_with` gets a button that
 * writes that URL into the input, and the settings are saved as usual.
 *
 * @package WcForceAuth
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var button = event.target && event.target.closest ? event.target.closest( '.admin-layout-fill-link' ) : null;

		if ( ! button ) {
			return;
		}

		event.preventDefault();

		var url = button.getAttribute( 'data-wcfa-fill' ) || '';
		var targetId = button.getAttribute( 'data-wcfa-fill-target' ) || '';
		var input = targetId ? document.getElementById( targetId ) : null;

		if ( ! url || ! input ) {
			return;
		}

		input.value = url;

		// Anything listening (conditional fields, the settings serializer) sees
		// this as a typed value, not as a DOM mutation out of nowhere.
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );

		input.focus();
	} );
} )();
