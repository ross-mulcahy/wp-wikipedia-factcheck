/**
 * Settings page test-connection handler.
 */
( function () {
	const btn = document.getElementById( 'wp-wikipedia-factcheck-test' );
	if ( ! btn ) {
		return;
	}

	const result = document.getElementById(
		'wp-wikipedia-factcheck-test-result'
	);
	const config = window.wpWikipediaFactcheckAdmin || {};

	btn.addEventListener( 'click', function () {
		btn.disabled = true;
		result.textContent = config.i18n?.testing || 'Testing...';
		result.style.color = '';

		fetch( config.testUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce,
			},
		} )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( data ) {
				result.textContent = data.message;
				result.style.color = data.success ? 'green' : 'red';
			} )
			.catch( function () {
				result.textContent =
					config.i18n?.requestFailed || 'Request failed.';
				result.style.color = 'red';
			} )
			.finally( function () {
				btn.disabled = false;
			} );
	} );
} )();
