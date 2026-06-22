( function () {
	var config = window.wchCheckoutWaiver || {};

	if (
		! config.buttonText ||
		! window.wc ||
		! window.wc.blocksCheckout ||
		! window.wc.blocksCheckout.registerCheckoutFilters
	) {
		return;
	}

	window.wc.blocksCheckout.registerCheckoutFilters( 'wc-herroepingsfunctie', {
		placeOrderButtonLabel: function () {
			return config.buttonText;
		},
	} );
}() );
