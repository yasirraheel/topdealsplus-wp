import { registerDynamicChunk } from 'blocksy-frontend'
import { scrollToTarget } from '../../../../../../static/js/helpers/scroll-to-target'

function whenTransitionEnds(el, cb) {
	const end = () => {
		el.removeEventListener('transitionend', onEnd)
		cb()
	}

	const onEnd = (e) => {
		if (e.target === el) {
			end()
		}
	}

	el.addEventListener('transitionend', onEnd)
}

const syncInputs = (el) => {
	const floatingInput = document.querySelector(
		'.ct-floating-bar .quantity .qty'
	)

	const productInput = document.querySelector(
		'.ct-cart-actions .quantity .qty'
	)

	if (!floatingInput || !productInput) {
		return
	}

	const nextQuantity = el.value?.toString().trim() || '1'

	;[floatingInput, productInput].forEach((input) => {
		input.value = nextQuantity

		input.dispatchEvent(new Event('change', { bubbles: true }))
	})

	document
		.querySelectorAll(
			'.ct-floating-bar-actions .button:not(.single_add_to_cart_button):not(.product_type_external)'
		)
		.forEach((button) => {
			button.dataset.quantity = nextQuantity
			button.setAttribute('data-quantity', nextQuantity)
		})
}

const updatePrice = (floatingBar) => {
	const currentPrice = floatingBar.querySelector('.product-price .price')
	const variablePrice = document.querySelector('.product .summary .price')

	if (currentPrice && variablePrice) {
		currentPrice.innerHTML = variablePrice.innerHTML
	}
}

const updateImage = (floatingBar) => {
	const currentImage = floatingBar.querySelector('img')
	const variableImage = document.querySelector(
		'.product .product-entry-wrapper .main-product-image'
	)

	if (currentImage && variableImage) {
		currentImage.src = variableImage.src
		currentImage.srcset = variableImage.srcset
		currentImage.sizes = variableImage.sizes
		currentImage.alt = variableImage.alt
		currentImage.title = variableImage.title
	}
}

const getDynamicData = (actions) =>
	JSON.parse(actions.dataset.dynamicAddToCartData)

const isVariationFullySelected = (form) => {
	const allVariations = JSON.parse(form.dataset.product_variations || '[]')
	const variationAttributes = Object.keys(allVariations[0]?.attributes || {})

	if (!variationAttributes.length) {
		return true
	}

	return variationAttributes.every((attributeName) => {
		const field = form.querySelector(`[name="${attributeName}"]`)
		return field && field.value.trim() !== ''
	})
}

const updateButtonText = (floatingBar, actions, form) => {
	const dynamicData = getDynamicData(actions)

	const maybeButton = floatingBar.querySelector(
		'.button:not(.single_add_to_cart_button):not(.product_type_external)'
	)

	if (isVariationFullySelected(form)) {
		maybeButton.innerHTML = dynamicData.simple.text
		return
	}

	maybeButton.innerHTML = dynamicData.variable.text
}

const syncQuantityVisibility = (floatingBar, form) => {
	const floatingQuantity = floatingBar.querySelector(
		'.ct-floating-bar-actions .quantity'
	)

	if (!floatingQuantity) {
		return
	}

	const productQuantity = document.querySelector('.ct-cart-actions .quantity')
	const shouldHideForVariation = !isVariationFullySelected(form)

	floatingQuantity.classList.toggle(
		'hidden',
		!productQuantity ||
			productQuantity.classList.contains('hidden') ||
			shouldHideForVariation
	)
}

const syncVariableData = () => {
	const floatingBar = document.querySelector('.ct-floating-bar')

	if (!floatingBar) {
		return
	}

	const actions = floatingBar.querySelector('.ct-floating-bar-actions')

	if (!actions?.dataset.dynamicAddToCartData) {
		return
	}

	const form = document.querySelector('.single-product form.variations_form')

	if (!form) {
		return
	}

	updatePrice(floatingBar)
	updateImage(floatingBar)
	updateButtonText(floatingBar, actions, form)
	syncQuantityVisibility(floatingBar, form)
}

registerDynamicChunk('blocksy_ext_woo_extra_floating_cart', {
	mount: (el, { state }) => {
		if (el.tagName === 'INPUT' && el.classList.contains('qty')) {
			syncInputs(el)

			return
		}

		if (state === 'target-after-bottom') {
			el.closest('.ct-drawer-canvas').dataset.floatingBar = 'yes:start'

			requestAnimationFrame(() => {
				el.closest('.ct-drawer-canvas').dataset.floatingBar = 'yes'

				syncVariableData()
			})
		}

		if (state !== 'target-after-bottom') {
			el.closest('.ct-drawer-canvas').dataset.floatingBar = 'no:updating'

			whenTransitionEnds(el, () => {
				if (
					el.closest('.ct-drawer-canvas').dataset.floatingBar ===
					'no:updating'
				) {
					el.closest('.ct-drawer-canvas').dataset.floatingBar = 'no'
				}
			})
		}

		const maybeButton = el.querySelector(
			'.button:not(.single_add_to_cart_button):not(.product_type_external)'
		)

		if (!maybeButton) {
			return
		}

		if (maybeButton.hasClickListener) {
			return
		}

		maybeButton.hasClickListener = true

		maybeButton.addEventListener('click', (event) => {
			event.preventDefault()

			if (!maybeButton.classList.contains('product_type_simple')) {
				scrollToTarget(
					document
						.querySelector(
							'.single-product .single_add_to_cart_button'
						)
						.closest('form')
				)
			}
		})
	}
})
