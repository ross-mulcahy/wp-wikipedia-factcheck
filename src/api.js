export async function requestWikipediaFactcheck( path, body = {} ) {
	const response = await fetch( `${ window.wpWikipediaFactcheck.restUrl }${ path }`, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': window.wpWikipediaFactcheck.nonce,
		},
		body: JSON.stringify( body ),
	} );

	const data = await response.json();

	if ( ! response.ok ) {
		throw new Error( data?.message || 'Request failed.' );
	}

	return data;
}
