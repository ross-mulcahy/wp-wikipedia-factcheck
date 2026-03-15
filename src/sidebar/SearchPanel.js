/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { Button, TextControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function SearchPanel( { onSearch, onClear, loading, disabled } ) {
	const selectedText = useSelect( ( select ) => {
		const { getSelectionStart, getSelectionEnd } = select( 'core/block-editor' );
		const start = getSelectionStart();
		const end = getSelectionEnd();

		if ( ! start?.clientId || start.clientId !== end?.clientId ) {
			return '';
		}

		const block = select( 'core/block-editor' ).getBlock( start.clientId );
		if ( ! block ) {
			return '';
		}

		const attributeKey = start.attributeKey;
		if ( ! attributeKey ) {
			return '';
		}

		const content = block.attributes[ attributeKey ] || '';
		// Strip HTML tags for plain text extraction.
		const plainText = content.replace( /<[^>]+>/g, '' );

		if ( typeof start.offset === 'number' && typeof end.offset === 'number' && start.offset !== end.offset ) {
			return plainText.substring( start.offset, end.offset );
		}

		return '';
	}, [] );

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
