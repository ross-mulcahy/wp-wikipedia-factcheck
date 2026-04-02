export async function requestWikipediaFactcheck( path, body = {} ) {
	const response = await fetch( `${ window.wpWikipediaFactcheck.restUrl }${ path }`, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': window.wpWikipediaFactcheck.nonce,
		},
		body: JSON.stringify( body ),
	} );

	const contentType = response.headers.get( 'content-type' ) || '';
	let data;

	if ( contentType.includes( 'application/json' ) ) {
		data = await response.json();
	} else {
		const text = await response.text();
		data = { message: text };
	}

	if ( ! response.ok ) {
		const fallbackMessage = typeof data?.message === 'string'
			? data.message.replace( /<[^>]+>/g, ' ' ).replace( /\s+/g, ' ' ).trim()
			: '';
		throw new Error( fallbackMessage || `Request failed with status ${ response.status }.` );
	}

	return data;
}
