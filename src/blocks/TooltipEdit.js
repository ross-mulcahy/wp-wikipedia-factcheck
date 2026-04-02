/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, TextareaControl, Button, Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { requestWikipediaFactcheck } from '../api';

function summarizeTooltipContent( text ) {
	if ( ! text ) {
		return '';
	}

	const firstSentence = text.match( /(.+?[.!?])(\s|$)/ )?.[ 1 ] || text;
	return firstSentence.length > 220 ? `${ firstSentence.substring( 0, 217 ) }...` : firstSentence;
}

export default function TooltipEdit( { attributes, setAttributes } ) {
	const { label, term, content, articleUrl } = attributes;
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );

	const loadWikipediaSummary = async () => {
		const lookupTerm = term || label;
		if ( ! lookupTerm.trim() ) {
			return;
		}

		setLoading( true );
		setError( null );

		try {
			const data = await requestWikipediaFactcheck( 'lookup', { term: lookupTerm } );
			setAttributes( {
				term: data.name || lookupTerm,
				content: summarizeTooltipContent( data.abstract ) || content,
				articleUrl: data.url || articleUrl,
			} );
		} catch ( requestError ) {
			setError( requestError.message );
		} finally {
			setLoading( false );
		}
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Wikipedia Tooltip', 'wp-wikipedia-factcheck' ) } initialOpen>
					<TextControl
						label={ __( 'Trigger label', 'wp-wikipedia-factcheck' ) }
						value={ label }
						onChange={ ( value ) => setAttributes( { label: value } ) }
					/>
					<TextControl
						label={ __( 'Wikipedia term', 'wp-wikipedia-factcheck' ) }
						value={ term }
						onChange={ ( value ) => setAttributes( { term: value } ) }
					/>
					<Button
						variant="primary"
						onClick={ loadWikipediaSummary }
						disabled={ loading || ! ( term || label ).trim() }
					>
						{ loading ? __( 'Loading summary…', 'wp-wikipedia-factcheck' ) : __( 'Load from Wikipedia', 'wp-wikipedia-factcheck' ) }
					</Button>
				</PanelBody>
			</InspectorControls>

			<div { ...useBlockProps( { className: 'wp-wikipedia-factcheck-block-editor-card wp-wikipedia-factcheck-block-editor-card--tooltip' } ) }>
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
				<div className="wp-wikipedia-factcheck-block-editor-card__eyebrow">
					{ __( 'Wikipedia Tooltip', 'wp-wikipedia-factcheck' ) }
				</div>
				<TextControl
					label={ __( 'Trigger label', 'wp-wikipedia-factcheck' ) }
					value={ label }
					onChange={ ( value ) => setAttributes( { label: value } ) }
					placeholder={ __( 'Highlighted phrase', 'wp-wikipedia-factcheck' ) }
				/>
				<TextControl
					label={ __( 'Wikipedia term', 'wp-wikipedia-factcheck' ) }
					value={ term }
					onChange={ ( value ) => setAttributes( { term: value } ) }
				/>
				<TextareaControl
					label={ __( 'Tooltip content', 'wp-wikipedia-factcheck' ) }
					value={ content }
					onChange={ ( value ) => setAttributes( { content: value } ) }
					rows={ 5 }
				/>
				<div className="wp-wikipedia-factcheck-block-editor-card__actions">
					<Button
						variant="secondary"
						onClick={ loadWikipediaSummary }
						disabled={ loading || ! ( term || label ).trim() }
					>
						{ __( 'Fetch summary', 'wp-wikipedia-factcheck' ) }
					</Button>
					{ loading && <Spinner /> }
				</div>
				<p className="wp-wikipedia-factcheck-tooltip-preview">
					<span className="wp-wikipedia-factcheck-tooltip-preview__label">
						{ label || __( 'Tooltip label', 'wp-wikipedia-factcheck' ) }
					</span>
					<span className="wp-wikipedia-factcheck-tooltip-preview__bubble">
						{ content || __( 'Tooltip content will appear here.', 'wp-wikipedia-factcheck' ) }
					</span>
				</p>
				{ articleUrl && (
					<p className="wp-wikipedia-factcheck-block-editor-card__help">
						{ __( 'The tooltip will link back to the Wikipedia source.', 'wp-wikipedia-factcheck' ) }
					</p>
				) }
			</div>
		</>
	);
}
