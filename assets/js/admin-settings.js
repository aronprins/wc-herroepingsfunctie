( function () {
	function onReady( callback ) {
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', callback );
			return;
		}

		callback();
	}

	function emitChange( element ) {
		element.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		element.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	function storeAppliedTranslation( locale, fieldKeys ) {
		var localeInput = document.getElementById( 'wch_default_translation_applied_locale' );

		if ( ! localeInput ) {
			return;
		}

		document.querySelectorAll( '[data-wch-default-translation-field]' ).forEach( function ( element ) {
			element.remove();
		} );

		localeInput.value = locale;

		if ( ! locale ) {
			return;
		}

		fieldKeys.forEach( function ( key ) {
			var input = document.createElement( 'input' );
			input.type = 'hidden';
			input.name = localeInput.name.replace( '[_default_translation_locale]', '[_default_translation_fields][]' );
			input.value = key;
			input.setAttribute( 'data-wch-default-translation-field', '' );
			localeInput.insertAdjacentElement( 'afterend', input );
		} );
	}

	onReady( function () {
		var select = document.getElementById( 'wch_default_translation_locale' );
		var config = window.wchDefaultTranslationPreview || {};
		var fields = config.fields || {};
		var sets = config.sets || {};

		if ( ! select ) {
			return;
		}

		select.addEventListener( 'change', function () {
			var set = sets[ select.value ];

			if ( ! select.value ) {
				storeAppliedTranslation( '', [] );
				return;
			}

			if ( ! set || ! set.values ) {
				return;
			}

			storeAppliedTranslation( select.value, Object.keys( fields ) );

			Object.keys( fields ).forEach( function ( key ) {
				var element = document.querySelector( fields[ key ] );

				if ( ! element || typeof set.values[ key ] === 'undefined' ) {
					return;
				}

				element.value = set.values[ key ];
				emitChange( element );
			} );
		} );
	} );
}() );
