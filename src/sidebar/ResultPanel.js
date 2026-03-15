/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Notice, ExternalLink } from '@wordpress/components';

/**
 * Internal dependencies
 */
import CredibilityBadge from './CredibilityBadge';

/**
 * Format a date string as a relative time (e.g. "3 days ago").
 *
 * @param {string} dateString ISO date string.
 * @return {string} Relative time string.
 */
function relativeDate( dateString ) {
	if ( ! dateString ) {
		return '';
	}

	const now = Date.now();
	const then = new Date( dateString ).getTime();
	const diffSeconds = Math.floor( ( now - then ) / 1000 );

	if ( diffSeconds < 60 ) {
		return __( 'just now', 'wp-wikipedia-factcheck' );
	}

	const diffMinutes = Math.floor( diffSeconds / 60 );
	if ( diffMinutes < 60 ) {
		return diffMinutes === 1
			? __( '1 minute ago', 'wp-wikipedia-factcheck' )
			: `${ diffMinutes } ${ __( 'minutes ago', 'wp-wikipedia-factcheck' ) }`;
	}

	const diffHours = Math.floor( diffMinutes / 60 );
	if ( diffHours < 24 ) {
		return diffHours === 1
			? __( '1 hour ago', 'wp-wikipedia-factcheck' )
			: `${ diffHours } ${ __( 'hours ago', 'wp-wikipedia-factcheck' ) }`;
	}

	const diffDays = Math.floor( diffHours / 24 );
	if ( diffDays < 30 ) {
		return diffDays === 1
			? __( '1 day ago', 'wp-wikipedia-factcheck' )
			: `${ diffDays } ${ __( 'days ago', 'wp-wikipedia-factcheck' ) }`;
	}

	const diffMonths = Math.floor( diffDays / 30 );
	return diffMonths === 1
		? __( '1 month ago', 'wp-wikipedia-factcheck' )
		: `${ diffMonths } ${ __( 'months ago', 'wp-wikipedia-factcheck' ) }`;
}

export default function ResultPanel( { result } ) {
	if ( ! result.found ) {
		return (
			<Notice status="info" isDismissible={ false } className="wp-wikipedia-factcheck-not-found">
				{ __(
					`No Wikipedia article found for "${ result.term }". Try a different spelling or a broader term.`,
					'wp-wikipedia-factcheck'
				) }
			</Notice>
		);
	}

	const abstract = result.abstract && result.abstract.length > 400
		? result.abstract.substring( 0, 400 ) + '…'
		: result.abstract;

	return (
		<div className="wp-wikipedia-factcheck-result">
			{ /* Header */ }
			<div className="wp-wikipedia-factcheck-result-header">
				<h3>
					<ExternalLink href={ result.url }>
						{ result.name }
					</ExternalLink>
				</h3>
				{ result.date_modified && (
					<span className="wp-wikipedia-factcheck-date">
						{ __( 'Last edited:', 'wp-wikipedia-factcheck' ) }{ ' ' }
						{ relativeDate( result.date_modified ) }
					</span>
				) }
				<CredibilityBadge revertRisk={ result.revert_risk } />
			</div>

			{ /* Image */ }
			{ result.image?.content_url && (
				<div className="wp-wikipedia-factcheck-image">
					<a href={ result.url } target="_blank" rel="noopener noreferrer">
						<img
							src={ result.image.content_url }
							alt={ result.name }
							style={ { maxHeight: '120px' } }
						/>
					</a>
				</div>
			) }

			{ /* Abstract */ }
			{ abstract && (
				<div className="wp-wikipedia-factcheck-abstract">
					<p>{ abstract }</p>
					{ result.abstract && result.abstract.length > 400 && (
						<ExternalLink href={ result.url }>
							{ __( 'Read more on Wikipedia →', 'wp-wikipedia-factcheck' ) }
						</ExternalLink>
					) }
				</div>
			) }

			{ /* Categories */ }
			{ result.categories?.length > 0 && (
				<div className="wp-wikipedia-factcheck-categories">
					{ result.categories.map( ( cat ) => (
						<span key={ cat } className="wp-wikipedia-factcheck-category-pill">
							{ cat }
						</span>
					) ) }
				</div>
			) }

			{ /* Wikidata */ }
			{ result.wikidata_qid && (
				<div className="wp-wikipedia-factcheck-wikidata">
					<ExternalLink href={ result.wikidata_url }>
						{ `Wikidata: ${ result.wikidata_qid }` }
					</ExternalLink>
				</div>
			) }

			{ /* License */ }
			<div className="wp-wikipedia-factcheck-license">
				{ __( 'Content licensed under ', 'wp-wikipedia-factcheck' ) }
				{ result.license?.url ? (
					<ExternalLink href={ result.license.url }>
						{ result.license.identifier || result.license.name }
					</ExternalLink>
				) : (
					__( 'CC BY-SA 4.0', 'wp-wikipedia-factcheck' )
				) }
				{ ' · Wikipedia' }
			</div>
		</div>
	);
}
