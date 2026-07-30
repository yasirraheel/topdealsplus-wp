/**
 * Integration with "Extra Product Options & Add-Ons for WooCommerce" by ThemeComplete
 * https://themecomplete.com/extra-product-options/
 *
 * EPO allows embedding variable products as add-ons inside the main product
 * form, creating nested .variations_form elements. It uses its own variation
 * handling system with tc_ prefixed jQuery events instead of native WooCommerce
 * events:
 *
 * - tc_variation_form: fired on the nested form after EPO initializes it via AJAX
 * - tc_found_variation: fired when a matching variation is found (like WooCommerce's found_variation)
 * - tc_reset_data: fired when variation selection is reset (like WooCommerce's reset_data)
 */

import $ from 'jquery'
import ctEvents from 'ct-events'

// When any form is initialized, bind EPO's variation event listeners on it.
// The chunk loads via tc_variation_form jquery-event trigger registered in PHP.
// initForm() triggers blocksy:woocommerce:swatches:single:init-form for each form.
ctEvents.on('blocksy:woocommerce:swatches:single:init-form', ({ form }) => {
	if (!form || form.__epoListenersBound) {
		return
	}

	form.__epoListenersBound = true

	$(form).on('tc_found_variation', () => {
		ctEvents.trigger('blocksy:woocommerce:swatches:compute', { form })
	})

	$(form).on('tc_reset_data', () => {
		ctEvents.trigger('blocksy:woocommerce:swatches:compute', { form })
	})
})
