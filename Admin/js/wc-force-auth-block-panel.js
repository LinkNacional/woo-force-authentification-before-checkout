/**
 * This plugin's part of the editor of a page holding the
 * "LKN Forms - Login and Register" block.
 *
 * The block is a plain login/register block and knows nothing about codes, so the
 * code controls come from here. They are two different things, and each one is
 * filled into a slot the block declares:
 *
 *  - the page's own settings (code rules, e-mail branding), in
 *    `lknform-otp-panel`, right below the block's Layout panel. They are stored in
 *    the block (`otp` attribute), with the page, so two login pages keep their
 *    own — the store-wide settings are the default for this plugin's own screens;
 *  - which page is the WooCommerce login page, in `lknform-store-login-page`,
 *    right under the block's "Show" field. It is one choice for the whole store
 *    (a single option), so turning it on in a page turns it off everywhere else;
 *  - a button that fills the block's "Redirect after login" with the store's
 *    "My account" address, in `lknform-redirect-after`. Typing that URL by hand
 *    is a good way to get a typo'd address that sends customers nowhere, and the
 *    block cannot suggest it itself: "My account" is a WooCommerce page.
 *
 * The screens themselves are drawn by this plugin on the front end (see
 * `WcForceAuthBlockOtp`), which reads the block's attribute: no OTP markup ever
 * comes from the block, so turning the code off gives the password form back with
 * nothing to clean up.
 *
 * @package WcForceAuth
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.element || ! wp.components || ! wp.data ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useSelect = wp.data.useSelect;
	var components = wp.components;
	var config = window.wcForceAuthBlockPanel || {};
	var i18n = config.i18n || {};

	/**
	 * Slot the block declares for the page's code settings (below its Layout panel).
	 *
	 * The block owns the place (and the switch that turns the feature on); this
	 * plugin owns the controls. Keeping the name here, next to the other slot's,
	 * keeps the contract in one place.
	 */
	var SLOT_NAME = config.slot || 'lknform-otp-panel';

	/**
	 * Length from which a help text is worth a bubble instead of a paragraph.
	 *
	 * The sidebar is narrow: a paragraph of help under every field pushes the
	 * fields themselves out of view, so a long explanation moves behind a "?"
	 * next to the label of the field it belongs to. Two lines of hint still fit
	 * and are read at a glance, so they stay where they are (and a translation
	 * that runs a few characters longer than the English text does not move an
	 * explanation behind the "?" by itself).
	 */
	var HELP_LONG = 70;

	/**
	 * Width of the bubble in pixels. Keep in sync with
	 * `.wcfa-block-panel__bubble` in Admin/css/wc-force-auth-block-panel.css: it
	 * is what keeps the bubble inside the window.
	 */
	var BUBBLE_WIDTH = 260;

	/** Gap between the "?" and its bubble. */
	var GAP = 6;

	/** Space kept between the bubble and the edges of the window. */
	var MARGIN = 8;

	/** Tells the other "?" that one of them opened (one bubble at a time). */
	var OPEN_EVENT = 'wcfa-block-panel-tip-open';

	/** Last number handed out, for the bubbles' DOM ids. */
	var lastTip = 0;

	/**
	 * Rough height of a bubble, to place it before it has been measured.
	 *
	 * @param {string} text The explanation.
	 * @return {number} Estimated height in pixels.
	 */
	function estimateHeight( text ) {
		// About 40 characters a line in 260px, 18px a line, plus the padding.
		return Math.ceil( ( text ? text.length : 0 ) / 40 ) * 18 + 28;
	}

	/**
	 * Where the bubble goes, from the "?" button's place on the screen.
	 *
	 * Below the "?" normally, and above it when there is no room left — a "?"
	 * near the bottom of a long panel still shows its text. Horizontally it is
	 * kept inside the window, since it is as wide as the sidebar.
	 *
	 * @param {HTMLElement|null} node   The "?" button.
	 * @param {number}           height Height the bubble will take.
	 * @return {Object} Inline style (fixed position) for the bubble.
	 */
	function anchorSpot( node, height ) {
		if ( ! node ) {
			return { top: MARGIN, left: MARGIN };
		}

		var rect = node.getBoundingClientRect();
		var below = rect.bottom + GAP;
		var above = rect.top - height - GAP;
		var room = window.innerHeight - MARGIN - height;

		// Below, unless that would run off the bottom and there is room above.
		var top = below + height > window.innerHeight - MARGIN ? above : below;

		top = Math.max( MARGIN, Math.min( top, room > MARGIN ? room : MARGIN ) );

		return {
			top: Math.round( top ),
			left: Math.round(
				Math.max( MARGIN, Math.min( rect.left, window.innerWidth - BUBBLE_WIDTH - MARGIN ) )
			),
		};
	}

	/**
	 * The "?" button and the bubble it opens.
	 *
	 * Drawn here instead of with `Popover`: that component's API has changed
	 * between WordPress versions (the newest one wants a store), while a
	 * fixed-position bubble anchored to the button behaves the same everywhere —
	 * and, being fixed, it is never clipped by the sidebar's scrolling box.
	 *
	 * @param {Object} props       Component props.
	 * @param {string} props.text  The full explanation.
	 * @param {string} props.label The setting it belongs to (accessible name).
	 * @return {Element|null} The button and its bubble, or null without text.
	 */
	function HelpTip( props ) {
		var text = props.text || '';
		var label = props.label || '';
		var open = wp.element.useState( false );
		var isOpen = open[ 0 ];
		var setOpen = open[ 1 ];
		var spot = wp.element.useState( null );
		var setSpot = spot[ 1 ];
		var button = wp.element.useRef( null );
		var bubble = wp.element.useRef( null );
		var domId = wp.element.useRef( '' );

		// Ids are only needed for `aria-controls`: a counter is enough, and it
		// does not tie this to the editor's own id generator.
		if ( ! domId.current ) {
			lastTip += 1;
			domId.current = 'wcfa-block-panel-bubble-' + lastTip;
		}

		wp.element.useEffect(
			function () {
				if ( ! isOpen ) {
					return undefined;
				}

				var place = function () {
					setSpot(
						anchorSpot(
							button.current,
							bubble.current ? bubble.current.offsetHeight : estimateHeight( text )
						)
					);
				};

				var away = function ( event ) {
					// The bubble is portaled, so it is not inside the button: its
					// own clicks (selecting the text, mostly) have to be ignored.
					if ( button.current && button.current.contains( event.target ) ) {
						return;
					}

					if ( bubble.current && bubble.current.contains( event.target ) ) {
						return;
					}

					setOpen( false );
				};

				// Another "?" was opened: this one closes.
				var elsewhere = function () {
					setOpen( false );
				};

				var escape = function ( event ) {
					if ( 'Escape' === event.key ) {
						setOpen( false );
					}
				};

				// Measured now that it is on the page, and focused so the text is read.
				place();

				if ( bubble.current && bubble.current.focus ) {
					bubble.current.focus( { preventScroll: true } );
				}

				document.addEventListener( 'mousedown', away );
				document.addEventListener( 'keydown', escape );
				document.addEventListener( OPEN_EVENT, elsewhere );
				window.addEventListener( 'resize', place );
				// `true`: any scrolling box (the sidebar has one), not just the window.
				window.addEventListener( 'scroll', place, true );

				return function () {
					document.removeEventListener( 'mousedown', away );
					document.removeEventListener( 'keydown', escape );
					document.removeEventListener( OPEN_EVENT, elsewhere );
					window.removeEventListener( 'resize', place );
					window.removeEventListener( 'scroll', place, true );

					// Focus went into the bubble, which is going away: hand it back
					// to the "?" so keyboard users are not dropped at the top.
					if ( button.current && button.current.focus ) {
						button.current.focus( { preventScroll: true } );
					}
				};
			},
			[ isOpen ]
		);

		if ( ! text ) {
			return null;
		}

		return el(
			Fragment,
			null,
			el(
				'button',
				{
					type: 'button',
					ref: button,
					className: 'wcfa-block-panel__tip',
					'aria-expanded': isOpen,
					'aria-haspopup': 'dialog',
					'aria-controls': domId.current,
					'aria-label': wp.i18n.sprintf( i18n.moreAbout || 'More about %s', label ),
					onClick: function ( event ) {
						// The text sits inside the label of a checkbox, and clicking a
						// label flips its control: the "?" must not.
						event.preventDefault();
						event.stopPropagation();

						if ( isOpen ) {
							setOpen( false );

							return;
						}

						document.dispatchEvent( new window.CustomEvent( OPEN_EVENT ) );

						// Positioned before it exists, so it never shows up misplaced
						// (the effect measures it and adjusts right after).
						setSpot( anchorSpot( button.current, estimateHeight( text ) ) );
						setOpen( true );
					},
				},
				'?'
			),
			isOpen &&
				wp.element.createPortal(
					el(
						'div',
						{
							id: domId.current,
							ref: bubble,
							className: 'wcfa-block-panel__bubble',
							role: 'dialog',
							'aria-label': label,
							tabIndex: -1,
							style: spot[ 0 ] || undefined,
						},
						text
					),
					document.body
				)
		);
	}

	/**
	 * A label with the "?" when the explanation is long.
	 *
	 * @param {string} label The field label.
	 * @param {string} help  The explanation.
	 * @return {string|Element} The label, with the "?" when there is a long text.
	 */
	function labelWithTip( label, help ) {
		if ( ! help || help.length <= HELP_LONG ) {
			return label;
		}

		return el(
			'span',
			{ className: 'wcfa-block-panel__label' },
			label,
			el( HelpTip, { text: help, label: label } )
		);
	}

	/**
	 * The help line a field should show under itself: nothing when the text went
	 * behind the "?".
	 *
	 * @param {string} help The explanation.
	 * @return {string|undefined} Text to print under the field, if short.
	 */
	function inlineHelp( help ) {
		return ! help || help.length <= HELP_LONG ? help : undefined;
	}

	/**
	 * Slot under the block's "Show" field, for the store login page switch.
	 */
	var LOGIN_PAGE_SLOT = config.loginPageSlot || 'lknform-store-login-page';

	/**
	 * Slot under the block's "Redirect after login" field.
	 */
	var REDIRECT_SLOT = config.redirectSlot || 'lknform-redirect-after';

	/**
	 * The block these controls belong to.
	 */
	var BLOCK = config.blockName || 'lknform/login-register';

	/**
	 * Whether a field is a plain yes/no switch.
	 *
	 * @param {Object} field Field schema.
	 * @return {boolean} Whether it is a yes/no switch.
	 */
	function isToggle( field ) {
		var keys = Object.keys( field.options || {} );

		return 'radio' === field.type && 2 === keys.length && -1 !== keys.indexOf( 'yes' ) && -1 !== keys.indexOf( 'no' );
	}

	/**
	 * The logo field: the URL, a thumbnail of what it points at, and the media
	 * library button.
	 *
	 * Picking an image is the normal way to set it, so the field opens the
	 * WordPress media frame (the same one the Customizer and the site icon use)
	 * instead of asking for a URL to be typed. The URL stays visible (and
	 * editable) so an external image can still be used.
	 *
	 * @param {Object}   props          Component props.
	 * @param {string}   props.value    Current URL.
	 * @param {Function} props.onChange Change handler.
	 * @param {boolean}  props.disabled Whether the control is read-only.
	 * @param {string}   props.help     Field description.
	 * @return {Element} The field.
	 */
	function LogoField( { value, onChange, disabled, help } ) {
		/**
		 * Opens the media library and hands the chosen image's URL over.
		 */
		const open = () => {
			if ( ! wp.media || disabled ) {
				return;
			}

			const frame = wp.media( {
				title: i18n.logoTitle || 'Select the logo',
				button: { text: i18n.useLogo || 'Use this image' },
				library: { type: 'image' },
				multiple: false,
			} );

			frame.on( 'select', function () {
				const attachment = frame.state().get( 'selection' ).first();

				if ( ! attachment ) {
					return;
				}

				const url = attachment.get( 'url' );

				if ( url ) {
					onChange( url );
				}
			} );

			frame.open();
		};

		return el(
			components.BaseControl,
			{ label: labelWithTip( i18n.logoLabel, help ), id: 'wcfa-block-panel-logo', help: inlineHelp( help ) },
			el(
				'div',
				{ className: 'wcfa-block-panel__logo' },
				value
					? el( 'img', { className: 'wcfa-block-panel__logo-preview', src: value, alt: '' } )
					: null,
				el( components.TextControl, {
					value: value,
					onChange: onChange,
					type: 'url',
					disabled: disabled,
					placeholder: 'https://',
					__nextHasNoMarginBottom: true,
				} ),
				el(
					'div',
					{ className: 'wcfa-block-panel__logo-actions' },
					el(
						components.Button,
						{ variant: 'secondary', onClick: open, disabled: disabled },
						i18n.chooseLogo || 'Choose from the media library'
					),
					value
						? el(
								components.Button,
								{
									variant: 'link',
									isDestructive: true,
									onClick: function () {
										onChange( '' );
									},
									disabled: disabled,
								},
								i18n.removeLogo || 'Remove'
						  )
						: null
				)
			)
		);
	}

	/**
	 * One setting control, built from the schema the settings screen provides.
	 *
	 * @param {Object}   props          Component props.
	 * @param {string}   props.name     Setting key.
	 * @param {Object}   props.field    Field schema.
	 * @param {string}   props.value    Current value.
	 * @param {Function} props.onChange Change handler.
	 * @param {boolean}  props.disabled Whether the control is read-only.
	 * @return {Element} The control.
	 */
	function Field( { name, field, value, onChange, disabled } ) {
		// A long explanation moves behind the "?" beside the label (see HelpTip),
		// so the fields of the panel stay visible in the narrow sidebar.
		var label = labelWithTip( field.label, field.description );
		var help = inlineHelp( field.description );

		if ( isToggle( field ) ) {
			return el( components.ToggleControl, {
				label: label,
				checked: 'yes' === value,
				onChange: function ( checked ) {
					onChange( checked ? 'yes' : 'no' );
				},
				help: help,
				disabled: disabled,
				__nextHasNoMarginBottom: true,
			} );
		}

		if ( 'select' === field.type || 'radio' === field.type ) {
			var options = Object.keys( field.options || {} ).map( function ( key ) {
				return { label: field.options[ key ], value: key };
			} );

			return el( components.SelectControl, {
				label: label,
				value: value,
				options: options,
				onChange: onChange,
				help: help,
				disabled: disabled,
				__nextHasNoMarginBottom: true,
			} );
		}

		return el( components.TextControl, {
			label: label,
			value: value,
			type: 'number' === field.type ? 'number' : 'text',
			onChange: onChange,
			help: help,
			disabled: disabled,
			__nextHasNoMarginBottom: true,
			key: name,
		} );
	}

	/**
	 * Saves the store login page choice.
	 *
	 * @param {number}   pageId 0 to give the login screen back to WooCommerce.
	 * @param {Function} onDone Called with the response (or null on failure).
	 */
	function saveLoginPage( pageId, onDone ) {
		var data = config.loginPage || {};
		var body = new FormData();

		body.append( 'action', data.action );
		body.append( '_ajax_nonce', data.nonce );
		body.append( 'page_id', pageId );

		window
			.fetch( data.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( response ) {
				onDone( response || null );
			} )
			.catch( function () {
				onDone( null );
			} );
	}

	/**
	 * "Use this page as the WooCommerce login page": the store-wide choice of
	 * which page serves the login screen on "My account".
	 *
	 * It is rendered right under the block's "Show" field, in the slot the block
	 * declares, because it is about the login screen this page shows — but it is
	 * *not* a page setting: there is a single option, so turning it on here turns
	 * it off on any other page (one login screen per store).
	 *
	 * @return {Element} The control.
	 */
	function LoginPageControl() {
		var data = config.loginPage || {};
		var current = useState( data.pageId || 0 );
		var pageId = current[ 0 ];
		var setPageId = current[ 1 ];
		var title = useState( data.pageTitle || '' );
		var pageTitle = title[ 0 ];
		var setPageTitle = title[ 1 ];
		var slug = useState( data.pageSlug || '' );
		var pageSlug = slug[ 0 ];
		var setPageSlug = slug[ 1 ];
		var status = useState( '' );
		var setStatus = status[ 1 ];

		var post = useSelect( function ( select ) {
			var editor = select( 'core/editor' );

			return {
				id: editor && editor.getCurrentPostId ? editor.getCurrentPostId() || 0 : 0,
				status: editor && editor.getCurrentPostAttribute ? editor.getCurrentPostAttribute( 'status' ) || '' : '',
			};
		}, [] );

		// The title uses the same wrapper as the groups of the code panel, so both
		// places read as one block of settings in the same sidebar.
		function group() {
			var children = Array.prototype.slice.call( arguments );

			return el(
				'div',
				{ className: 'wcfa-block-panel__group' },
				el( components.BaseControl, {
					label: i18n.loginPageGroup,
					id: 'wcfa-block-panel-login-page',
				} ),
				children
			);
		}

		if ( ! data.woocommerce ) {
			return group(
				el( components.Notice, { status: 'info', isDismissible: false }, noticeLine( i18n.loginPageNeedsWooShort, i18n.loginPageNeedsWoo ) )
			);
		}

		if ( ! config.canSave ) {
			return group(
				el(
					components.Notice,
					{ status: 'info', isDismissible: false },
					noticeLine(
						i18n.loginPageNoPermissionShort,
						pageId === post.id ? i18n.loginPageNoPermissionOn : i18n.loginPageNoPermission
					)
				)
			);
		}

		var isThisPage = !! post.id && pageId === post.id;

		// The page that holds the role is named by its title and its slug: the slug
		// is what tells two similarly named pages apart.
		var holder = pageTitle && pageSlug ? pageTitle + ' (' + pageSlug + ')' : pageTitle;

		return group(
			el( components.CheckboxControl, {
				label: labelWithTip( i18n.loginPageLabel, i18n.loginPageHelp ),
				checked: isThisPage,
				// An unpublished page cannot take the role, but a page that already
				// has it must always be able to give it back.
				disabled: ! post.id || ( ! isThisPage && 'publish' !== post.status ),
				onChange: function ( next ) {
					setStatus( i18n.saving || '' );

					saveLoginPage( next ? post.id : 0, function ( response ) {
						if ( ! response || ! response.success ) {
							setStatus( ( response && response.data && response.data.message ) || i18n.error || '' );

							return;
						}

						setPageId( response.data.pageId || 0 );
						setPageTitle( response.data.pageTitle || '' );
						setPageSlug( response.data.pageSlug || '' );
						setStatus( response.data.message || '' );
					} );
				},
				help: inlineHelp( i18n.loginPageHelp ),
				__nextHasNoMarginBottom: true,
			} ),

			'publish' !== post.status &&
				el(
					components.Notice,
					{ status: 'warning', isDismissible: false },
					noticeLine( i18n.loginPageUnpublishedShort, i18n.loginPageUnpublished )
				),

			! isThisPage &&
				pageId > 0 &&
				el(
					components.Notice,
					{ status: 'info', isDismissible: false },
					holder
						? noticeLine(
								wp.i18n.sprintf( i18n.loginPageOtherShort || '%s', holder ),
								wp.i18n.sprintf( i18n.loginPageOther || '%s', holder )
						  )
						: noticeLine( i18n.loginPageOtherGenericShort || '', i18n.loginPageOtherGeneric || '' )
				),

			'' !== status[ 0 ] &&
				el( 'p', { className: 'components-base-control__help', style: { marginTop: 0 } }, status[ 0 ] )
		);
	}

	/**
	 * The page's code rules and e-mail branding, bound to the block's attribute.
	 *
	 * These are the page's own settings, not the store's: they are read from and
	 * written to the block (`otp.settings`), which the post stores — so two login
	 * pages can ask for different codes. The store-wide settings stay in this
	 * plugin's own screen, which is what its own login page uses.
	 *
	 * Rendered by the block plugin inside its sidebar, through the
	 * `lknform-otp-panel` slot, which hands the block's attributes over.
	 *
	 * @param {Object}   props                Slot fill props.
	 * @param {Object}   props.attributes     The block's attributes.
	 * @param {Function} props.setAttributes  Updates the block.
	 * @return {Element|null} The panel, or null outside the block.
	 */
	function PagePanel( props ) {
		var attributes = ( props && props.attributes ) || null;
		var setAttributes = props && props.setAttributes;
		var otp = ( attributes && attributes.otp ) || null;

		if ( ! attributes || typeof setAttributes !== 'function' || ! otp ) {
			return null;
		}

		var stored = otp.settings || {};

		/**
		 * Writes one setting into the block (the post stores it when saved).
		 *
		 * @param {string} name  Setting key.
		 * @param {string} value New value.
		 */
		function changeSetting( name, value ) {
			var patch = {};

			patch[ name ] = value;

			setAttributes( {
				otp: Object.assign( {}, otp, {
					settings: Object.assign( {}, stored, patch ),
				} ),
			} );
		}

		var fields = config.fields || {};
		var defaults = config.pageDefaults || {};
		var names = Object.keys( fields );
		var groups = [
			{ key: 'code', label: i18n.codeGroup },
			{ key: 'email', label: i18n.emailGroup },
		];

		// An empty field falls back to the snapshot the block was created with (or,
		// failing that, to the store value), so no control is ever shown blank.
		function valueOf( name ) {
			if ( ! Object.prototype.hasOwnProperty.call( stored, name ) ) {
				return defaults[ name ] || '';
			}

			return stored[ name ];
		}

		var disabled = ! config.canSave;

		return el(
			components.PanelBody,
			{ title: i18n.panelTitle, initialOpen: true },

			groups.map( function ( group ) {
				var groupNames = names.filter( function ( name ) {
					return ( fields[ name ].group || 'code' ) === group.key;
				} );

				if ( ! groupNames.length ) {
					return null;
				}

				return el(
					'div',
					{ className: 'wcfa-block-panel__group', key: group.key },
					el( components.BaseControl, { label: group.label, id: 'wcfa-block-panel-' + group.key } ),
					groupNames.map( function ( name ) {
						var change = function ( value ) {
							changeSetting( name, value );
						};

						if ( 'logo_url' === name ) {
							return el( LogoField, {
								key: name,
								value: valueOf( name ),
								onChange: change,
								disabled: disabled,
								help: fields[ name ].description,
							} );
						}

						return el( Field, {
							key: name,
							name: name,
							field: fields[ name ],
							value: valueOf( name ),
							onChange: change,
							disabled: disabled,
						} );
					} )
				);
			} ),

			disabled && el( 'p', { className: 'components-base-control__help' }, i18n.readOnly )
		);
	}

	/**
	 * A one-line notice: the short text, with the full wording behind its "?".
	 *
	 * The warnings of this panel used to be paragraphs inside `Notice`s, which is
	 * a lot of room for a sidebar that has to show fields. What the reader needs
	 * at a glance is the state ("publish the page first"); the reason stays one
	 * click away.
	 *
	 * @param {string} text   Short line shown in the notice.
	 * @param {string} detail Full explanation, in the bubble.
	 * @return {Element} The notice content.
	 */
	function noticeLine( text, detail ) {
		return el(
			'span',
			{ className: 'wcfa-block-panel__label' },
			text,
			el( HelpTip, { text: detail, label: text } )
		);
	}

	/**
	 * The block being edited, from whatever is selected inside it.
	 *
	 * A slot of the block's own sidebar is on screen while the block (or one of
	 * its pieces) is selected, so walking up from the selection finds it.
	 *
	 * @param {Function} select Data store selector.
	 * @return {Object|null} The block, or null when it is not on screen.
	 */
	function editedBlock( select ) {
		var editor = select( 'core/block-editor' );
		var id = editor.getSelectedBlockClientId();
		var guard = 0;

		while ( id && guard++ < 20 ) {
			var block = editor.getBlock( id );

			if ( block && BLOCK === block.name ) {
				return block;
			}

			id = editor.getBlockRootClientId( id );
		}

		return null;
	}

	/**
	 * "Use the 'My account' page" under the block's "Redirect after login".
	 *
	 * The address is the store's, so it is offered from here and written straight
	 * into the block's attribute — the customer's next sign-in lands on the
	 * account page without anyone typing a URL.
	 *
	 * @return {Element|null} The button, or nothing when there is no address.
	 */
	function RedirectFill() {
		var url = config.accountUrl || '';
		var block = useSelect( editedBlock, [] );
		var attributes = ( block && block.attributes ) || {};
		var disabled = !! attributes.redirectToPrevious;
		var filled = url && attributes.redirectAfter === url;

		// No published "My account" page to offer: without it, the field is just a
		// text input, as the block ships it.
		if ( ! url || ! block ) {
			return null;
		}

		return el(
			'div',
			{ className: 'wcfa-block-panel__redirect' },
			el(
				components.Button,
				{
					variant: 'secondary',
					disabled: disabled || filled,
					onClick: function () {
						wp.data
							.dispatch( 'core/block-editor' )
							.updateBlockAttributes( block.clientId, {
								redirectAfter: url,
							} );
					},
				},
				i18n.redirectFill || 'Use the "My account" page'
			),
			disabled &&
				el(
					'p',
					{ className: 'components-base-control__help' },
					i18n.redirectFillIgnored
				),
			! disabled &&
				filled &&
				el(
					'p',
					{ className: 'components-base-control__help' },
					i18n.redirectFillFilled
				)
		);
	}

	/**
	 * Registers this plugin's controls in the editor.
	 *
	 * Three fills, each into the slot the block declares: the store login page
	 * switch (under the block's "Show" field), the "My account" shortcut (under
	 * the redirect field) and the page's code settings (below the Layout panel).
	 * There is no sidebar of its own — everything it shows belongs to those
	 * places, and a second sidebar would be an empty copy of them.
	 *
	 * @return {Element} The plugin content.
	 */
	function render() {
		return el(
			Fragment,
			null,
			el( components.Fill, { name: LOGIN_PAGE_SLOT }, function () {
				return el( LoginPageControl, null );
			} ),
			el( components.Fill, { name: SLOT_NAME }, function ( fillProps ) {
				return el( PagePanel, fillProps || {} );
			} ),
			el( components.Fill, { name: REDIRECT_SLOT }, function () {
				return el( RedirectFill, null );
			} )
		);
	}

	wp.plugins.registerPlugin( 'wc-force-auth-block-panel', { render: render } );
} )( window.wp );
