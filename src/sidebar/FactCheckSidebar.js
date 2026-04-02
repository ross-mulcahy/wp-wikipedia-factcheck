/**
 * WordPress dependencies
 */
import { PluginSidebar } from '@wordpress/editor';
import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { requestWikipediaFactcheck } from '../api';
import SearchPanel from './SearchPanel';
import ResultPanel from './ResultPanel';

const { hasCredentials, settingsUrl } = window.wpWikipediaFactcheck || {};

function getSelectedEditorText( select ) {
	const { getSelectionStart, getSelectionEnd, getBlock } = select( 'core/block-editor' );
	const start = getSelectionStart();
	const end = getSelectionEnd();

	if ( ! start?.clientId || start.clientId !== end?.clientId ) {
		return '';
	}

	const block = getBlock( start.clientId );
	if ( ! block || ! start.attributeKey ) {
		return '';
	}

	const content = block.attributes?.[ start.attributeKey ] || '';
	const plainText = String( content ).replace( /<[^>]+>/g, '' );

	if ( typeof start.offset === 'number' && typeof end.offset === 'number' && start.offset !== end.offset ) {
		return plainText.substring( start.offset, end.offset ).trim();
	}

	return '';
}

export default function FactCheckSidebar() {
	const [ result, setResult ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ searchedTerm, setSearchedTerm ] = useState( '' );
	const [ analysis, setAnalysis ] = useState( null );
	const [ analysisError, setAnalysisError ] = useState( null );
	const [ analyzing, setAnalyzing ] = useState( false );
	const selectedText = useSelect( getSelectedEditorText, [] );

	const handleSearch = async ( term ) => {
		setSearchedTerm( term );
		setError( null );
		setResult( null );
		setAnalysis( null );
		setAnalysisError( null );
		setLoading( true );

		try {
			const data = await requestWikipediaFactcheck( 'lookup', { term } );
			setResult( data );
		} catch ( requestError ) {
			setError( requestError.message || __( 'Could not reach Wikipedia. Please try again.', 'wp-wikipedia-factcheck' ) );
		} finally {
			setLoading( false );
		}
	};

	const handleAnalyze = async () => {
		if ( ! selectedText || ! searchedTerm ) {
			return;
		}

		setAnalyzing( true );
		setAnalysisError( null );

		try {
			const data = await requestWikipediaFactcheck( 'analyze', {
				term: searchedTerm,
				selected_text: selectedText,
			} );
			setAnalysis( data );
		} catch ( requestError ) {
			setAnalysisError( requestError.message || __( 'Could not analyze the selected text.', 'wp-wikipedia-factcheck' ) );
		} finally {
			setAnalyzing( false );
		}
	};

	const handleClear = () => {
		setSearchedTerm( '' );
		setResult( null );
		setError( null );
		setAnalysis( null );
		setAnalysisError( null );
	};

	return (
		<PluginSidebar
			name="wp-wikipedia-factcheck"
			title={ __( 'Wikipedia Fact-Check', 'wp-wikipedia-factcheck' ) }
			icon="book-alt"
		>
			<div className="wp-wikipedia-factcheck-sidebar">
				<div className="wp-wikipedia-factcheck-intro">
					<p>{ __( 'Check a claim, place, person, or organisation against Wikipedia and review a few trust signals before you cite it.', 'wp-wikipedia-factcheck' ) }</p>
				</div>

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
					selectedText={ selectedText }
				/>

				{ error && (
					<Notice status="error" isDismissible onDismiss={ () => setError( null ) }>
						{ error }
					</Notice>
				) }

				{ result && (
					<ResultPanel
						result={ result }
						searchedTerm={ searchedTerm }
						selectedText={ selectedText }
						analysis={ analysis }
						analysisError={ analysisError }
						analyzing={ analyzing }
						onAnalyze={ handleAnalyze }
					/>
				) }
			</div>
		</PluginSidebar>
	);
}
