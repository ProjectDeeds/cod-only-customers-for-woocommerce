/* global wc, wp */
wp.domReady(function () {
	'use strict';
	var registry = wc.wcBlocksRegistry;
	var methods = Object.assign({}, registry.getPaymentMethods(), registry.getExpressPaymentMethods());
	var callbacks = {};
	Object.keys(methods).forEach(function (name) {
		// Express methods may have names different from their server-side gateway.
		if (name !== 'cod') {
			callbacks[name] = function () { return false; };
		}
	});
	registry.registerPaymentMethodExtensionCallbacks('cod-only-customers-for-woocommerce', callbacks);
});
