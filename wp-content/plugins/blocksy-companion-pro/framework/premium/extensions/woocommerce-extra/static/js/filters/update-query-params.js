export const updateQueryParams = (uri) => {
	const url = new URL(uri, location.href)

	url.searchParams.delete('blocksy_ajax')

	uri = url.toString()

	// searchParams.forEach((value, key) => {
	// 	if (!value) {
	// 		searchParams.delete(key)
	// 	}
	// })

	// const newUlr = searchParams.toString().length
	// 	? decodeURIComponent(searchParams.toString())
	// 	: window.location.pathname

	window.history.pushState(null, document.title, uri)
}
