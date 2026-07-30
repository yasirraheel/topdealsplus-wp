import $ from 'jquery'
import ctEvents from 'ct-events'

import { computeSwatch } from './common'

const initForm = (form) => {
	if (form.hasEventListener) {
		return
	}

	form.hasEventListener = true

	computeSwatch(form)

	// Re-scan DOM so click triggers are registered on any new swatch elements.
	ctEvents.trigger('blocksy:frontend:init')

	// Notify integrations so they can bind plugin-specific event listeners.
	ctEvents.trigger('blocksy:woocommerce:swatches:single:init-form', { form })

	$(form).on('found_variation', () => computeSwatch(form))
	$(form).on('reset_data', () => computeSwatch(form))
}

export const maybeHandleSingleSwatches = (el) => {
	if (!el.closest('.single-product')) {
		return
	}

	const forms = el
		.closest('.single-product')
		.querySelectorAll('.variations_form')

	if (!forms || !forms.length) {
		return
	}

	forms.forEach((form) => initForm(form))
}
