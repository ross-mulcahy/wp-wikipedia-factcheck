/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { Notice, ExternalLink } from '@wordpress/components';

/**
 * Internal dependencies
 */
import CredibilityBadge from './CredibilityBadge';

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
			: sprintf( __( '%d minutes ago', 'wp-wikipedia-factcheck' ), diffMinutes );
	}

	const diffHours = Math.floor( diffMinutes / 60 );
	if ( diffHours < 24 ) {
		return diffHours === 1
			? __( '1 hour ago', 'wp-wikipedia-factcheck' )
			: sprintf( __( '%d hours ago', 'wp-wikipedia-factcheck' ), diffHours );
	}

	const diffDays = Math.floor( diffHours / 24 );
	if ( diffDays < 30 ) {
		return diffDays === 1
			? __( '1 day ago', 'wp-wikipedia-factcheck' )
			: sprintf( __( '%d days ago', 'wp-wikipedia-factcheck' ), diffDays );
	}

	const diffMonths = Math.floor( diffDays / 30 );
	return diffMonths === 1
		? __( '1 month ago', 'wp-wikipedia-factcheck' )
		: sprintf( __( '%d months ago', 'wp-wikipedia-factcheck' ), diffMonths );
}

function getFirstSentence( text ) {
	if ( ! text ) {
		return '';
	}

	const match = text.match( /(.+?[.!?])(\s|$)/ );
	return match?.[ 1 ] || text;
}

function getCategoryWarnings( categories = [] ) {
	const warningPatterns = [
		/articles containing potentially dated statements/i,
		/lacking reliable references/i,
		/articles with short description/i,
		/pages using gadget/i,
		/articles containing /i,
	];

	return categories.filter( ( category ) =>
		warningPatterns.some( ( pattern ) => pattern.test( category ) )
	).slice( 0, 3 );
}

function getFreshnessLabel( dateModified ) {
	if ( ! dateModified ) {
		return __( 'Unknown freshness', 'wp-wikipedia-factcheck' );
	}

	const ageInDays = Math.floor( ( Date.now() - new Date( dateModified ).getTime() ) / ( 1000 * 60 * 60 * 24 ) );

	if ( ageInDays <= 7 ) {
		return __( 'Recently updated', 'wp-wikipedia-factcheck' );
	}

	if ( ageInDays <= 90 ) {
		return __( 'Fairly current', 'wp-wikipedia-factcheck' );
	}

	return __( 'Check for newer sources', 'wp-wikipedia-factcheck' );
}

function getSignalSummary( result, searchedTerm ) {
	const warnings = getCategoryWarnings( result.categories );
	const exactishMatch = searchedTerm && result.name
		? result.name.toLowerCase().includes( searchedTerm.toLowerCase() ) ||
			searchedTerm.toLowerCase().includes( result.name.toLowerCase() )
		: true;

	if ( result.revert_risk !== null && result.revert_risk > 0.4 ) {
		return {
			tone: 'caution',
			title: __( 'Use with caution', 'wp-wikipedia-factcheck' ),
			body: __( 'This article version has a higher revert-risk score, so double-check core claims against stronger primary or newsroom sources.', 'wp-wikipedia-factcheck' ),
		};
	}

	if ( warnings.length > 0 ) {
		return {
			tone: 'watch',
			title: __( 'Useful, but check dated details', 'wp-wikipedia-factcheck' ),
			body: __( 'Wikipedia found a relevant article, but some maintenance categories suggest parts of it may be dated or need stronger sourcing.', 'wp-wikipedia-factcheck' ),
		};
	}

	if ( ! exactishMatch ) {
		return {
			tone: 'watch',
			title: __( 'Likely match, verify the subject', 'wp-wikipedia-factcheck' ),
			body: __( 'Wikipedia resolved your search to a nearby article title. Confirm it is the exact person, place, or organisation you meant to check.', 'wp-wikipedia-factcheck' ),
		};
	}

	return {
		tone: 'good',
		title: __( 'Good starting point', 'wp-wikipedia-factcheck' ),
		body: __( 'This looks like a strong candidate for a quick fact check. Use the summary for orientation, then verify any dates, figures, and claims against the linked article.', 'wp-wikipedia-factcheck' ),
	};
}

function getSuggestedChecks( result ) {
	const checks = [
		__( 'Compare names, numbers, and dates in your draft against the article summary and full page.', 'wp-wikipedia-factcheck' ),
	];

	if ( result.date_modified ) {
		checks.push( __( 'Open the article before publishing if the fact depends on something time-sensitive.', 'wp-wikipedia-factcheck' ) );
	}

	if ( result.wikidata_qid ) {
		checks.push( __( 'Use the Wikidata entry for identifiers, alternate names, and structured facts.', 'wp-wikipedia-factcheck' ) );
	}

	if ( getCategoryWarnings( result.categories ).length > 0 ) {
		checks.push( __( 'Treat maintenance categories as a warning sign and confirm contentious details from another source.', 'wp-wikipedia-factcheck' ) );
	}

	return checks.slice( 0, 3 );
}

export default function ResultPanel( {
	result,
	searchedTerm,
	selectedText,
	aiAvailable,
	analysis,
	analysisError,
	analyzing,
	onAnalyze,
} ) {
	if ( ! result.found ) {
		return (
			<Notice status="info" isDismissible={ false } className="wp-wikipedia-factcheck-not-found">
				{ sprintf(
					__( 'No Wikipedia article found for "%s". Try a broader term, an exact title, or a more specific name.', 'wp-wikipedia-factcheck' ),
					result.term
				) }
			</Notice>
		);
	}

	const abstract = result.abstract && result.abstract.length > 320
		? result.abstract.substring( 0, 320 ) + '…'
		: result.abstract;
	const quickSummary = getFirstSentence( result.abstract );
	const warnings = getCategoryWarnings( result.categories );
	const signalSummary = getSignalSummary( result, searchedTerm );
	const suggestedChecks = getSuggestedChecks( result );

	return (
		<div className="wp-wikipedia-factcheck-result">
			<div className="wp-wikipedia-factcheck-signal-card">
				<span className={ `wp-wikipedia-factcheck-signal-card__eyebrow wp-wikipedia-factcheck-signal-card__eyebrow--${ signalSummary.tone }` }>
					{ signalSummary.title }
				</span>
				<p>{ signalSummary.body }</p>
			</div>

			<div className="wp-wikipedia-factcheck-result-header">
				<div className="wp-wikipedia-factcheck-result-header__main">
					<h3>{ result.name }</h3>
					<div className="wp-wikipedia-factcheck-result-links">
						<ExternalLink href={ result.url }>
							{ __( 'Open article', 'wp-wikipedia-factcheck' ) }
						</ExternalLink>
						{ result.wikidata_qid && result.wikidata_url && (
							<ExternalLink href={ result.wikidata_url }>
								{ sprintf( __( 'Wikidata %s', 'wp-wikipedia-factcheck' ), result.wikidata_qid ) }
							</ExternalLink>
						) }
					</div>
				</div>
				<CredibilityBadge revertRisk={ result.revert_risk } />
			</div>

			<div className="wp-wikipedia-factcheck-metrics">
				<div className="wp-wikipedia-factcheck-metric">
					<span className="wp-wikipedia-factcheck-metric__label">{ __( 'Freshness', 'wp-wikipedia-factcheck' ) }</span>
					<strong>{ getFreshnessLabel( result.date_modified ) }</strong>
					{ result.date_modified && <span>{ relativeDate( result.date_modified ) }</span> }
				</div>
				<div className="wp-wikipedia-factcheck-metric">
					<span className="wp-wikipedia-factcheck-metric__label">{ __( 'Article type', 'wp-wikipedia-factcheck' ) }</span>
					<strong>{ warnings.length > 0 ? __( 'Needs closer review', 'wp-wikipedia-factcheck' ) : __( 'General reference', 'wp-wikipedia-factcheck' ) }</strong>
					<span>{ __( 'Use as a starting point, not a final source.', 'wp-wikipedia-factcheck' ) }</span>
				</div>
			</div>

			{ result.image?.content_url && (
				<div className="wp-wikipedia-factcheck-image">
					<a href={ result.url } target="_blank" rel="noopener noreferrer">
						<img
							src={ result.image.content_url }
							alt={ result.name }
						/>
					</a>
				</div>
			) }

			{ quickSummary && (
				<div className="wp-wikipedia-factcheck-section">
					<h4>{ __( 'Quick Summary', 'wp-wikipedia-factcheck' ) }</h4>
					<p className="wp-wikipedia-factcheck-lead">{ quickSummary }</p>
					{ abstract && abstract !== quickSummary && <p>{ abstract }</p> }
				</div>
			) }

			<div className="wp-wikipedia-factcheck-section">
				<h4>{ __( 'Suggested Checks', 'wp-wikipedia-factcheck' ) }</h4>
				<ul className="wp-wikipedia-factcheck-list">
					{ suggestedChecks.map( ( check ) => (
						<li key={ check }>{ check }</li>
					) ) }
				</ul>
			</div>

			<div className="wp-wikipedia-factcheck-section">
				<h4>{ __( 'AI Claim Check', 'wp-wikipedia-factcheck' ) }</h4>
				{ ! aiAvailable && (
					<p>{ __( 'Enable a provider through the WordPress AI Client to compare selected draft text against this Wikipedia summary.', 'wp-wikipedia-factcheck' ) }</p>
				) }
				{ aiAvailable && ! selectedText && (
					<p>{ __( 'Select a sentence or paragraph in the editor, then run an AI claim check to spot likely mismatches in names, dates, numbers, or core assertions.', 'wp-wikipedia-factcheck' ) }</p>
				) }
				{ aiAvailable && selectedText && (
					<>
						<p className="wp-wikipedia-factcheck-selection-preview">
							<strong>{ __( 'Selected draft text:', 'wp-wikipedia-factcheck' ) }</strong>{ ' ' }
							{ selectedText }
						</p>
						<button
							type="button"
							className="components-button is-secondary"
							onClick={ onAnalyze }
							disabled={ analyzing }
						>
							{ analyzing ? __( 'Analyzing…', 'wp-wikipedia-factcheck' ) : __( 'Compare with Wikipedia', 'wp-wikipedia-factcheck' ) }
						</button>
					</>
				) }
				{ analysisError && (
					<Notice status="error" isDismissible={ false }>
						{ analysisError }
					</Notice>
				) }
				{ analysis && (
					<div className="wp-wikipedia-factcheck-analysis">
						<p className="wp-wikipedia-factcheck-analysis__summary">
							<strong>{ analysis.verdict }</strong>
							{ ' ' }
							{ analysis.summary }
						</p>
						{ analysis.mismatches?.length > 0 ? (
							<ul className="wp-wikipedia-factcheck-list wp-wikipedia-factcheck-list--warning">
								{ analysis.mismatches.map( ( mismatch, index ) => (
									<li key={ `${ mismatch.type }-${ index }` }>
										<strong>{ mismatch.type }</strong>: { mismatch.explanation }
									</li>
								) ) }
							</ul>
						) : (
							<p>{ __( 'No obvious mismatches were found from the summary provided.', 'wp-wikipedia-factcheck' ) }</p>
						) }
					</div>
				) }
			</div>

			{ warnings.length > 0 && (
				<div className="wp-wikipedia-factcheck-section">
					<h4>{ __( 'Watch-outs', 'wp-wikipedia-factcheck' ) }</h4>
					<ul className="wp-wikipedia-factcheck-list wp-wikipedia-factcheck-list--warning">
						{ warnings.map( ( warning ) => (
							<li key={ warning }>{ warning }</li>
						) ) }
					</ul>
				</div>
			) }

			{ result.categories?.length > 0 && (
				<div className="wp-wikipedia-factcheck-section">
					<h4>{ __( 'Topic Tags', 'wp-wikipedia-factcheck' ) }</h4>
					<div className="wp-wikipedia-factcheck-categories">
						{ result.categories
							.filter( ( category ) => ! warnings.includes( category ) )
							.slice( 0, 6 )
							.map( ( category ) => (
								<span key={ category } className="wp-wikipedia-factcheck-category-pill">
									{ category }
								</span>
							) ) }
					</div>
				</div>
			) }

			<div className="wp-wikipedia-factcheck-license">
				<span>{ __( 'Source', 'wp-wikipedia-factcheck' ) }</span>
				<ExternalLink href={ result.url }>
					{ __( 'Wikipedia article', 'wp-wikipedia-factcheck' ) }
				</ExternalLink>
				<span>·</span>
				{ result.license?.url ? (
					<ExternalLink href={ result.license.url }>
						{ result.license.identifier || result.license.name }
					</ExternalLink>
				) : (
					<span>{ __( 'CC BY-SA 4.0', 'wp-wikipedia-factcheck' ) }</span>
				) }
			</div>
		</div>
	);
}
