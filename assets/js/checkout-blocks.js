( function () {
	var config = window.wchCheckoutWaiver || {};
	var countryCodes = Array.isArray( config.countryCodes ) ? config.countryCodes : [];
	var observer = null;
	var scheduled = false;

	countryCodes = countryCodes.map( function ( code ) {
		return String( code || '' ).toUpperCase();
	} );

	function normalizeCountryCode( value ) {
		var match;

		value = String( value || '' ).trim().toUpperCase();
		if ( /^[A-Z]{2}$/.test( value ) ) {
			return value;
		}

		match = value.match( /\(([A-Z]{2})\)\s*$/ );
		return match ? match[ 1 ] : '';
	}

	function readAddressCountry( address ) {
		if ( ! address || typeof address !== 'object' ) {
			return '';
		}

		return normalizeCountryCode(
			address.country ||
				address.country_code ||
				address.countryCode ||
				address.billing_country ||
				address.shipping_country ||
				''
		);
	}

	function readCountryFromStore() {
		var stores = [ 'wc/store/checkout', 'wc/store/cart' ];
		var selectors;
		var i;
		var selector;
		var data;
		var country;

		if ( ! window.wp || ! window.wp.data || ! window.wp.data.select ) {
			return '';
		}

		for ( i = 0; i < stores.length; i++ ) {
			selector = window.wp.data.select( stores[ i ] );
			if ( ! selector ) {
				continue;
			}

			selectors = [
				'getBillingAddress',
				'getShippingAddress',
				'getCustomerData',
				'getCartData',
				'getCheckoutData',
			];

			for ( var j = 0; j < selectors.length; j++ ) {
				if ( typeof selector[ selectors[ j ] ] !== 'function' ) {
					continue;
				}

				data = selector[ selectors[ j ] ]();
				country = readAddressCountry( data );
				if ( country ) {
					return country;
				}

				country = readAddressCountry( data && data.billingAddress );
				if ( country ) {
					return country;
				}

				country = readAddressCountry( data && data.billing_address );
				if ( country ) {
					return country;
				}

				country = readAddressCountry( data && data.shippingAddress );
				if ( country ) {
					return country;
				}

				country = readAddressCountry( data && data.shipping_address );
				if ( country ) {
					return country;
				}
			}
		}

		return '';
	}

	function readCountryFromDom() {
		var selectors = [
			'[name="billing_country"]',
			'[id*="billing-country"]',
			'[id*="billing_country"]',
			'input[aria-label*="Country"]',
			'input[aria-label*="country"]',
			'input[aria-label*="Land"]',
			'input[aria-label*="land"]',
			'button[aria-label*="Country"]',
			'button[aria-label*="country"]',
			'button[aria-label*="Land"]',
			'button[aria-label*="land"]',
		];
		var nodes = document.querySelectorAll( selectors.join( ',' ) );
		var i;
		var country;

		for ( i = 0; i < nodes.length; i++ ) {
			country = normalizeCountryCode(
				nodes[ i ].value ||
					nodes[ i ].getAttribute( 'aria-label' ) ||
					nodes[ i ].textContent ||
					''
			);
			if ( country ) {
				return country;
			}
		}

		return '';
	}

	function getSelectedCountry() {
		return readCountryFromStore() || readCountryFromDom();
	}

	function cartNeedsDigitalWaiver() {
		var selector;
		var cartData;

		if ( typeof config.cartHasOnlyDigitalProducts === 'boolean' ) {
			return config.cartHasOnlyDigitalProducts;
		}

		if ( ! window.wp || ! window.wp.data || ! window.wp.data.select ) {
			return true;
		}

		selector = window.wp.data.select( 'wc/store/cart' );
		if ( ! selector || typeof selector.getCartData !== 'function' ) {
			return true;
		}

		cartData = selector.getCartData();
		if ( cartData && true === cartData.needs_shipping ) {
			return false;
		}
		if ( cartData && false === cartData.needs_shipping ) {
			return true;
		}
		if ( cartData && true === cartData.needsShipping ) {
			return false;
		}
		if ( cartData && false === cartData.needsShipping ) {
			return true;
		}

		return true;
	}

	function countryRequiresWaiver( country ) {
		country = normalizeCountryCode( country );
		if ( ! country ) {
			return !! config.unknownCountryRequires;
		}

		return countryCodes.indexOf( country ) !== -1;
	}

	function shouldRequireWaiver() {
		return cartNeedsDigitalWaiver() && countryRequiresWaiver( getSelectedCountry() );
	}

	function findWaiverInput() {
		return document.querySelector(
			'input[type="checkbox"][id*="wc-herroepingsfunctie"][id*="withdrawal-waiver"],' +
				'input[type="checkbox"][name*="wc-herroepingsfunctie"][name*="withdrawal-waiver"]'
		);
	}

	function findWaiverContainer( input ) {
		var selectors = [
			'.wc-block-components-checkbox',
			'.wc-block-components-checkout-step',
			'.components-base-control',
			'.woocommerce-additional-fields__field-wrapper',
			'p',
			'div',
		];
		var i;
		var container;

		for ( i = 0; i < selectors.length; i++ ) {
			container = input.closest( selectors[ i ] );
			if ( container ) {
				return container;
			}
		}

		return input.parentNode;
	}

	function dispatchChange( input ) {
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	function updatePlaceOrderButton( show ) {
		var buttons;
		var fallbackText = config.fallbackButtonText || 'Place Order';

		if ( ! config.buttonText ) {
			return;
		}

		buttons = Array.prototype.slice.call( document.querySelectorAll( 'button' ) ).filter( function ( button ) {
			var text = ( button.textContent || '' ).trim();
			var className = String( button.className || '' );
			return (
				className.indexOf( 'checkout-place-order' ) !== -1 ||
				'Place Order' === text ||
				config.buttonText === text
			);
		} );

		buttons.forEach( function ( button ) {
			var textNode = button.querySelector( '.wc-block-components-button__text' ) || button;

			if ( ! button.dataset.wchOriginalText ) {
				button.dataset.wchOriginalText = config.buttonText === ( textNode.textContent || '' ).trim() ? fallbackText : ( textNode.textContent || '' ).trim() || fallbackText;
			}

			textNode.textContent = show ? config.buttonText : button.dataset.wchOriginalText;
		} );
	}

	function setWaiverVisibility() {
		var input = findWaiverInput();
		var show = shouldRequireWaiver();
		var container;

		updatePlaceOrderButton( show );

		if ( ! input ) {
			return;
		}

		container = findWaiverContainer( input );
		if ( container ) {
			container.hidden = ! show;
			container.style.display = show ? '' : 'none';
		}

		input.disabled = ! show;
		input.required = !! show;

		if ( ! show && input.checked ) {
			input.checked = false;
			dispatchChange( input );
		}
	}

	function scheduleUpdate() {
		if ( scheduled ) {
			return;
		}

		scheduled = true;
		window.requestAnimationFrame( function () {
			scheduled = false;
			setWaiverVisibility();
		} );
	}

	if (
		window.wc &&
		window.wc.blocksCheckout &&
		window.wc.blocksCheckout.registerCheckoutFilters
	) {
		window.wc.blocksCheckout.registerCheckoutFilters( 'wc-herroepingsfunctie', {
			placeOrderButtonLabel: function ( defaultValue ) {
				return shouldRequireWaiver() && config.buttonText ? config.buttonText : defaultValue;
			},
		} );
	}

	if ( window.wp && window.wp.data && window.wp.data.subscribe ) {
		window.wp.data.subscribe( scheduleUpdate );
	}

	document.addEventListener( 'change', scheduleUpdate, true );
	document.addEventListener( 'input', scheduleUpdate, true );

	if ( window.MutationObserver ) {
		observer = new MutationObserver( scheduleUpdate );
		observer.observe( document.documentElement, { childList: true, subtree: true } );
	}

	scheduleUpdate();
	window.setTimeout( scheduleUpdate, 500 );
	window.setTimeout( scheduleUpdate, 1500 );
}() );
