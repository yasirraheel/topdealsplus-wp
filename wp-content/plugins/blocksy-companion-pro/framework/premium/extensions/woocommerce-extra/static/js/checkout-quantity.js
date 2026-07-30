import { registerDynamicChunk } from 'blocksy-frontend'
import $ from 'jquery'

let request = null
let debounceTimer = null

registerDynamicChunk('blocksy_ext_woo_extra_checkout_qty', {
	mount: (el) => {
		const cartKey = el.dataset.cartKey

		if (!cartKey) {
			return
		}

		if (debounceTimer) {
			clearTimeout(debounceTimer)
		}

		if (request) {
			request.abort()
			request = null
		}

		const currentVal = parseFloat(el.value)

		debounceTimer = setTimeout(() => {
			request = $.ajax({
				type: 'POST',
				url: ct_localizations.ajax_url,
				data: {
					action: 'blocksy_update_qty_cart',
					hash: cartKey,
					quantity: currentVal,
				},
				success: () => {
					jQuery('body').trigger('updated_wc_div')

					$(document.body).trigger('update_checkout')

					ctEvents.trigger('ct:header:update')
				},
				complete: () => {
					request = null
				},
			})
		}, 500)
	},
})
