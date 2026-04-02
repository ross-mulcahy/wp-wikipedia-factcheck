/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { Button, TextControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function SearchPanel( { onSearch, onClear, loading, disabled, selectedText } ) {
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
		</form>
	);
}
