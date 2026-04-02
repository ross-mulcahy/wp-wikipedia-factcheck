/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { Button, TextControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function SearchPanel( {
	onSearch,
	onSuggestTopics,
	onSelectSuggestion,
	onClear,
	loading,
	suggesting,
	disabled,
	selectedText,
	draftContent,
	suggestions,
	suggestionsError,
} ) {
	const [ term, setTerm ] = useState( '' );

	useEffect( () => {
		if ( selectedText ) {
			setTerm( selectedText );
		}
	}, [ selectedText ] );

	const handleSubmit = ( e ) => {
		e?.preventDefault();
		if ( term.trim() ) {
			onSearch( term.trim() );
		}
	};

	const handleClear = () => {
		setTerm( '' );
		onClear();
	};

	return (
		<form className="wp-wikipedia-factcheck-search" onSubmit={ handleSubmit }>
			<TextControl
				label={ __( 'Search Wikipedia', 'wp-wikipedia-factcheck' ) }
				value={ term }
				onChange={ setTerm }
				placeholder={ __( 'Enter a term…', 'wp-wikipedia-factcheck' ) }
				disabled={ disabled || loading }
			/>
			{ selectedText && term !== selectedText && (
				<Button
					variant="link"
					className="wp-wikipedia-factcheck-use-selection"
					onClick={ () => setTerm( selectedText ) }
					disabled={ disabled || loading }
				>
					{ __( 'Use selected text', 'wp-wikipedia-factcheck' ) }
				</Button>
			) }
			<div className="wp-wikipedia-factcheck-search-actions">
				<Button
					variant="primary"
					onClick={ handleSubmit }
					disabled={ disabled || loading || ! term.trim() }
				>
					{ loading ? <Spinner /> : __( 'Search', 'wp-wikipedia-factcheck' ) }
				</Button>
				{ ( term || loading ) && (
					<Button
						variant="link"
						onClick={ handleClear }
						disabled={ loading }
					>
						{ __( 'Clear', 'wp-wikipedia-factcheck' ) }
					</Button>
				) }
			</div>
			<div className="wp-wikipedia-factcheck-search-actions wp-wikipedia-factcheck-search-actions--secondary">
				<Button
					variant="secondary"
					onClick={ onSuggestTopics }
					disabled={ disabled || loading || suggesting || ! draftContent }
				>
					{ suggesting ? __( 'Scanning draft…', 'wp-wikipedia-factcheck' ) : __( 'Suggest from draft', 'wp-wikipedia-factcheck' ) }
				</Button>
			</div>
			{ suggestionsError && (
				<p className="wp-wikipedia-factcheck-search-help wp-wikipedia-factcheck-search-help--error">
					{ suggestionsError }
				</p>
			) }
			{ suggestions?.length > 0 && (
				<div className="wp-wikipedia-factcheck-suggestions">
					<p className="wp-wikipedia-factcheck-search-help">
						{ __( 'AI spotted a few Wikipedia topics worth checking in this draft.', 'wp-wikipedia-factcheck' ) }
					</p>
					<div className="wp-wikipedia-factcheck-suggestion-list">
						{ suggestions.map( ( suggestion ) => (
							<button
								key={ suggestion.term }
								type="button"
								className="wp-wikipedia-factcheck-suggestion"
								onClick={ () => {
									setTerm( suggestion.term );
									onSelectSuggestion( suggestion.term );
								} }
								disabled={ disabled || loading || suggesting }
							>
								<strong>{ suggestion.term }</strong>
								{ suggestion.why && <span>{ suggestion.why }</span> }
							</button>
						) ) }
					</div>
				</div>
			) }
		</form>
	);
}
