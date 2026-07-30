import { loadStyle } from 'blocksy-frontend'

// Inject the styles/scripts/dynamic-CSS that the server collected for content
// rendered out-of-band — AJAX popups, AJAX mega menus, etc. Such content skips
// the normal enqueue lifecycle, so its block-plugin frontend scripts (e.g.
// GreenShift's accordion) and dynamic CSS ride in the AJAX response and are
// injected here instead.
//
// Everything is deduped by handle so re-opening/re-rendering doesn't re-inject.
// Styles resolve before the returned promise settles, so a caller can `await`
// this before revealing the content to avoid a flash of unstyled markup.
//
// The handle-less inline dynamic-CSS blob (`assets.inline_css`) is appended
// into the theme's ct-main-styles-inline-css element (created here when the
// page didn't print one). Dynamic CSS is append-only within a page lifetime —
// re-fetches are deduped by simply checking the CSS is not already there.
const appendDynamicCss = (css) => {
	let style = document.getElementById('ct-main-styles-inline-css')

	if (!style) {
		style = document.createElement('style')
		style.id = 'ct-main-styles-inline-css'
		document.head.appendChild(style)
	}

	if (style.textContent.includes(css)) {
		return
	}

	style.textContent += css
}

export const injectCollectedAssets = async (assets = {}) => {
	const stylePromises = (assets.styles || []).map(({ src, handle, inline }) => {
		if (handle && document.getElementById(`${handle}-css`)) {
			return Promise.resolve()
		}

		if (!src && inline) {
			const style = document.createElement('style')
			if (handle) {
				style.id = `${handle}-css`
				style.dataset.handle = handle
			}
			style.textContent = inline
			document.head.appendChild(style)
			return Promise.resolve()
		}

		if (!src) {
			return Promise.resolve()
		}

		return loadStyle(src).then(() => {
			if (inline) {
				const style = document.createElement('style')
				document.head.appendChild(style)
				style.textContent = inline
			}
		})
	})

	await Promise.all(stylePromises)

	if (assets.inline_css) {
		appendDynamicCss(assets.inline_css)
	}

	;(assets.scripts || []).forEach(({ src, handle, data }) => {
		if (
			document.getElementById(`${handle}-js`) ||
			document.querySelector(`script[src="${src}"]`)
		) {
			return
		}

		if (data) {
			const inline = document.createElement('script')
			inline.textContent = data
			document.head.appendChild(inline)
		}

		const script = document.createElement('script')
		script.src = src
		if (handle) {
			script.id = `${handle}-js`
		}
		script.dataset.handle = handle
		document.body.appendChild(script)
	})
}
