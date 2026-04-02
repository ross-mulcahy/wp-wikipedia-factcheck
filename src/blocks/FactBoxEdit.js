/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, TextareaControl, Button, Notice, Spinner, RadioControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { requestWikipediaFactcheck } from '../api';

export default function FactBoxEdit( { attributes, setAttributes } ) {
	const { term, headline, fact, articleUrl } = attributes;
	const [ loading, setLoading ] = useState( false );
	const [ options, setOptions ] = useState( [] );
	const [ error, setError ] = useState( null );

	const fetchFacts = async () => {
		if ( ! term.trim() ) {
			return;
		}

		setLoading( true );
		setError( null );

		try {
			const data = await requestWikipediaFactcheck( 'interesting-facts', { term } );
			setOptions( data.facts || [] );

			if ( data.article?.url ) {
				setAttributes( { articleUrl: data.article.url } );
			}

			if ( ! headline && data.facts?.[ 0 ]?.headline ) {
				setAttributes( { headline: data.facts[ 0 ].headline } );
			}

			if ( ! fact && data.facts?.[ 0 ]?.fact ) {
				setAttributes( { fact: data.facts[ 0 ].fact } );
			}
		} catch ( requestError ) {
			setError( requestError.message );
		} finally {
			setLoading( false );
		}
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Wikipedia Fact Box', 'wp-wikipedia-factcheck' ) } initialOpen>
					<TextControl
						label={ __( 'Wikipedia term', 'wp-wikipedia-factcheck' ) }
						value={ term }
						onChange={ ( value ) => setAttributes( { term: value } ) }
					/>
					<Button
						variant="primary"
						onClick={ fetchFacts }
						disabled={ loading || ! term.trim() }
					>
						{ loading ? __( 'Loading facts…', 'wp-wikipedia-factcheck' ) : __( 'Suggest facts with AI', 'wp-wikipedia-factcheck' ) }
					</Button>
					{ options.length > 0 && (
						<RadioControl
							label={ __( 'Choose a fact', 'wp-wikipedia-factcheck' ) }
							selected={ fact }
							options={ options.map( ( option ) => ( {
								label: `${ option.headline }: ${ option.fact }`,
								value: option.fact,
							} ) ) }
							onChange={ ( value ) => {
								const selected = options.find( ( option ) => option.fact === value );
								setAttributes( {
									fact: value,
									headline: selected?.headline || headline,
								} );
							} }
						/>
					) }
				</PanelBody>
			</InspectorControls>

			<div { ...useBlockProps( { className: 'wp-wikipedia-factcheck-block-editor-card' } ) }>
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
				<div className="wp-wikipedia-factcheck-block-editor-card__eyebrow">
					{ __( 'Wikipedia Fact Box', 'wp-wikipedia-factcheck' ) }
				</div>
				<TextControl
					label={ __( 'Wikipedia term', 'wp-wikipedia-factcheck' ) }
					value={ term }
					onChange={ ( value ) => setAttributes( { term: value } ) }
				/>
				<TextControl
					label={ __( 'Headline', 'wp-wikipedia-factcheck' ) }
					value={ headline }
					onChange={ ( value ) => setAttributes( { headline: value } ) }
					placeholder={ __( 'Interesting fact', 'wp-wikipedia-factcheck' ) }
				/>
				<TextareaControl
					label={ __( 'Selected fact', 'wp-wikipedia-factcheck' ) }
					value={ fact }
					onChange={ ( value ) => setAttributes( { fact: value } ) }
					rows={ 4 }
				/>
				<div className="wp-wikipedia-factcheck-block-editor-card__actions">
					<Button
						variant="secondary"
						onClick={ fetchFacts }
						disabled={ loading || ! term.trim() }
					>
						{ __( 'Refresh suggestions', 'wp-wikipedia-factcheck' ) }
					</Button>
					{ loading && <Spinner /> }
				</div>
				{ articleUrl && (
					<p className="wp-wikipedia-factcheck-block-editor-card__help">
						{ __( 'Source article linked and ready for the front end.', 'wp-wikipedia-factcheck' ) }
					</p>
				) }
			</div>
		</>
	);
}
