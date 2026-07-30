// Server side links are generated from the current request URL, so HTML
// fetched with blocksy_ajax=yes contains links with the param baked in.
// blocksy_ajax is a transport-only marker and must not stay in the DOM.
export const stripAjaxParamFromLinks = (container) => {
	;[...container.querySelectorAll('a[href*="blocksy_ajax"]')].map((link) => {
		const url = new URL(link.getAttribute('href'), window.location.href)

		url.searchParams.delete('blocksy_ajax')

		link.setAttribute('href', url.toString())
	})
}
