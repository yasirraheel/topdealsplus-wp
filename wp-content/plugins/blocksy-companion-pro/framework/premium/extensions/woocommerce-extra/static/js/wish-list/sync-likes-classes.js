import { getProductIdFromElement } from '../utils'

const SELECTOR = [
	'[class*="ct-wishlist-button"]',
	'.ct-wishlist-remove',
	'.wishlist-product-remove > .remove',
	'.product-mobile-actions > [href*="wishlist-remove"]',
].join(', ')

const getAdditionalAttributes = (productCard) => {
	const additional_attrs = {}

	productCard
		.querySelectorAll('select[data-attribute_name]')
		.forEach((select) => {
			const key = select.dataset.attribute_name.replace('attribute_', '')
			additional_attrs[key] = select.value
		})

	return additional_attrs
}

const hasMatchingItem = (likes, id, attributes = {}) => {
	return likes.items.some((item) => {
		if (item.id !== id) return false

		const itemAttrs = item?.attributes || {}
		return Object.keys(attributes).every(
			(key) =>
				itemAttrs[key] &&
				attributes[key] &&
				itemAttrs[key] === attributes[key]
		)
	})
}

const handleSimpleProduct = (el, likes, productId) => {
	if (hasMatchingItem(likes, productId)) {
		el.dataset.buttonState = 'active'
	}
}

const handleVariableProduct = (el, likes, productCard, productId) => {
	const variationButton = productCard.querySelector('.add_to_cart_button')
	const maybeVariation = productCard.querySelector('[data-current-variation]')

	// Case 1: variable without add_to_cart_button and no current variation
	if (variationButton === null && !maybeVariation) {
		handleSimpleProduct(el, likes, productId)
		return
	}

	// Case 2: current variation exists in dataset
	if (maybeVariation) {
		const variationId = parseFloat(maybeVariation.dataset.currentVariation)
		const additionalAttrs = getAdditionalAttributes(productCard)

		if (hasMatchingItem(likes, variationId, additionalAttrs)) {
			el.dataset.buttonState = 'active'
		}
	}

	// Case 3: add_to_cart_button link contains variation_id
	if (
		variationButton &&
		variationButton.getAttribute('href').includes('variation_id')
	) {
		const params = new URLSearchParams(variationButton.getAttribute('href'))
		const variationId = parseFloat(params.get('variation_id'))
		const additionalAttrs = getAdditionalAttributes(productCard)

		if (hasMatchingItem(likes, variationId, additionalAttrs)) {
			el.dataset.buttonState = 'active'
		}
	}
}

const processWishlistButton = (el, likes) => {
	el.dataset.buttonState = ''

	const productCard = el.closest('.product')
	const isVariable = typeof el.dataset.variable !== 'undefined'
	const productId = getProductIdFromElement(el)

	if (!isVariable) {
		handleSimpleProduct(el, likes, productId)
	} else {
		handleVariableProduct(el, likes, productCard, productId)
	}
}

const syncLikesClasses = (likes) => {
	document.querySelectorAll(SELECTOR).forEach((el) => {
		processWishlistButton(el, likes)
	})
}

ctEvents.on('blocksy:wishlist:sync', () =>
	syncLikesClasses(window.ct_localizations.blc_ext_wish_list.list)
)

export default syncLikesClasses
