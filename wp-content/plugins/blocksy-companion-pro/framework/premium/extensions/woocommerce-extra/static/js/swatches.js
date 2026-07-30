import { registerDynamicChunk } from 'blocksy-frontend'
import $ from 'jquery'
import ctEvents from 'ct-events'

import { computeSwatch } from './swatches/common'
import { maybeHandleSingleSwatches } from './swatches/single'
import { maybeHandleArchiveSwatches } from './swatches/archive'
import { maybeHandleSingleProductBlockSwatches } from './swatches/product-block'
import { expandSwatches } from './swatches/visibility-limit'

// Third-party plugin integrations
import './swatches/integrations/themecomplete-epo'

ctEvents.on('blocksy:woocommerce:swatches:compute', ({ form }) => {
	if (!form) {
		console.error('blocksy:woocommerce:swatches:compute requires a form element')
		return
	}

	computeSwatch(form)
})

registerDynamicChunk('blocksy_ext_woo_extra_swatches', {
	mount: (el, { event }) => {
		if (
			event &&
			event.type === 'click' &&
			el.classList.contains('ct-swatches-more')
		) {
			expandSwatches(el)
			return
		}

		maybeHandleSingleSwatches(el)
		maybeHandleArchiveSwatches(el)
		maybeHandleSingleProductBlockSwatches(el)

		if (el.classList.contains('disabled')) {
			return
		}

		const variationSwatches = el.closest('.ct-variation-swatches')

		if (!variationSwatches) {
			return
		}

		const select = variationSwatches.querySelector('select')

		if (el === select) {
			return
		}

		if (el.classList.contains('active')) {
			$(select).val('').trigger('change')
			return
		}

		$(select).val(el.dataset.value).trigger('change')
	},
})
