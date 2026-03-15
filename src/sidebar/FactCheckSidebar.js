/**
 * WordPress dependencies
 */
import { PluginSidebar } from '@wordpress/editor';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import SearchPanel from './SearchPanel';
import ResultPanel from './ResultPanel';

const { hasCredentials, settingsUrl } = window.wpWikipediaFactcheck || {};

export default function FactCheckSidebar() {
	const [ result, setResult ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ loading, setLoading ] = useState( false );

	const handleSearch = async ( term ) => {
		setError( null );
		setResult( null );
		setLoading( true );

		try {
			const response = await fetch(
				`${ window.wpWikipediaFactcheck.restUrl }lookup`,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': window.wpWikipediaFactcheck.nonce,
					},
					body: JSON.stringify( { term } ),
				}
			);

			const data = await response.json();

			if ( ! response.ok ) {
				const messages = {
					401: __( 'Authentication failed. Check your credentials in Settings.', 'wp-wikipedia-factcheck' ),
					429: __( 'API limit reached. Try again shortly.', 'wp-wikipedia-factcheck' ),
				};
				setError( messages[ response.status ] || __( 'Could not reach Wikipedia. Please try again.', 'wp-wikipedia-factcheck' ) );
				return;
			}

			setResult( data );
		} catch {
			setError( __( 'Could not reach Wikipedia. Please try again.', 'wp-wikipedia-factcheck' ) );
		} finally {
			setLoading( false );
		}
	};

	const handleClear = () => {
		setResult( null );
		setError( null );
	};

	return (
		<PluginSidebar
			name="wp-wikipedia-factcheck"
			title={ __( 'Wikipedia Fact-Check', 'wp-wikipedia-factcheck' ) }
			icon="book-alt"
		>
			<div className="wp-wikipedia-factcheck-sidebar">
				{ ! hasCredentials && (
					<Notice status="warning" isDismissible={ false }>
						{ __( 'Configure your Wikimedia Enterprise credentials in ', 'wp-wikipedia-factcheck' ) }
						<a href={ settingsUrl } target="_blank" rel="noopener noreferrer">
							{ __( 'Settings → Wikipedia Fact-Check', 'wp-wikipedia-factcheck' ) }
						</a>.
					</Notice>
				) }

				<SearchPanel
					onSearch={ handleSearch }
					onClear={ handleClear }
					loading={ loading }
					disabled={ ! hasCredentials }
				/>

				{ error && (
					<Notice status="error" isDismissible onDismiss={ () => setError( null ) }>
						{ error }
					</Notice>
				) }

				{ result && <ResultPanel result={ result } /> }
			</div>
		</PluginSidebar>
	);
}
