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
			: sprintf(
					__( '%d minutes ago', 'wp-wikipedia-factcheck' ),
					diffMinutes
			  );
	}

	const diffHours = Math.floor( diffMinutes / 60 );
	if ( diffHours < 24 ) {
		return diffHours === 1
			? __( '1 hour ago', 'wp-wikipedia-factcheck' )
			: sprintf(
					__( '%d hours ago', 'wp-wikipedia-factcheck' ),
					diffHours
			  );
	}

	const diffDays = Math.floor( diffHours / 24 );
	if ( diffDays < 30 ) {
		return diffDays === 1
			? __( '1 day ago', 'wp-wikipedia-factcheck' )
			: sprintf(
					__( '%d days ago', 'wp-wikipedia-factcheck' ),
					diffDays
			  );
	}

	const diffMonths = Math.floor( diffDays / 30 );
	return diffMonths === 1
		? __( '1 month ago', 'wp-wikipedia-factcheck' )
		: sprintf(
				__( '%d months ago', 'wp-wikipedia-factcheck' ),
				diffMonths
		  );
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

	return categories
		.filter( ( category ) =>
			warningPatterns.some( ( pattern ) => pattern.test( category ) )
		)
		.slice( 0, 3 );
}

function getFreshnessLabel( dateModified ) {
	if ( ! dateModified ) {
		return __( 'Unknown freshness', 'wp-wikipedia-factcheck' );
	}

	const ageInDays = Math.floor(
		( Date.now() - new Date( dateModified ).getTime() ) /
			( 1000 * 60 * 60 * 24 )
	);

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
	const exactishMatch =
		searchedTerm && result.name
			? result.name
					.toLowerCase()
					.includes( searchedTerm.toLowerCase() ) ||
			  searchedTerm.toLowerCase().includes( result.name.toLowerCase() )
			: true;

	if ( result.revert_risk !== null && result.revert_risk > 0.4 ) {
		return {
			tone: 'caution',
			title: __( 'Use with caution', 'wp-wikipedia-factcheck' ),
			body: __(
				'This article version has a higher revert-risk score, so double-check core claims against stronger primary or newsroom sources.',
				'wp-wikipedia-factcheck'
			),
		};
	}

	if ( warnings.length > 0 ) {
		return {
			tone: 'watch',
			title: __(
				'Useful, but check dated details',
				'wp-wikipedia-factcheck'
			),
			body: __(
				'Wikipedia found a relevant article, but some maintenance categories suggest parts of it may be dated or need stronger sourcing.',
				'wp-wikipedia-factcheck'
			),
		};
	}

	if ( ! exactishMatch ) {
		return {
			tone: 'watch',
			title: __(
				'Likely match, verify the subject',
				'wp-wikipedia-factcheck'
			),
			body: __(
				'Wikipedia resolved your search to a nearby article title. Confirm it is the exact person, place, or organisation you meant to check.',
				'wp-wikipedia-factcheck'
			),
		};
	}

	return {
		tone: 'good',
		title: __( 'Good starting point', 'wp-wikipedia-factcheck' ),
		body: __(
			'This looks like a strong candidate for a quick fact check. Use the summary for orientation, then verify any dates, figures, and claims against the linked article.',
			'wp-wikipedia-factcheck'
		),
	};
}

export default function ResultPanel( {
	result,
	searchedTerm,
	briefing,
	briefingError,
	briefingLoading,
	onGenerateBriefing,
} ) {
	if ( ! result.found ) {
		return (
			<Notice
				status="info"
				isDismissible={ false }
				className="wp-wikipedia-factcheck-not-found"
			>
				{ sprintf(
					__(
						'No Wikipedia article found for "%s". Try a broader term, an exact title, or a more specific name.',
						'wp-wikipedia-factcheck'
					),
					result.term
				) }
			</Notice>
		);
	}

	const abstract =
		result.abstract && result.abstract.length > 320
			? result.abstract.substring( 0, 320 ) + '…'
			: result.abstract;
	const quickSummary = getFirstSentence( result.abstract );
	const warnings = getCategoryWarnings( result.categories );
	const signalSummary = getSignalSummary( result, searchedTerm );
	return (
		<div className="wp-wikipedia-factcheck-result">
			<div className="wp-wikipedia-factcheck-hero">
				<div className="wp-wikipedia-factcheck-hero__media">
					{ result.image?.content_url ? (
						<img
							src={ result.image.content_url }
							alt={ result.name }
							className="wp-wikipedia-factcheck-hero__image"
						/>
					) : (
						<div className="wp-wikipedia-factcheck-hero__image wp-wikipedia-factcheck-hero__image--fallback" />
					) }
					<div className="wp-wikipedia-factcheck-hero__overlay" />
				</div>

				<div className="wp-wikipedia-factcheck-hero__body">
					<div className="wp-wikipedia-factcheck-hero__status">
						<div
							className={ `wp-wikipedia-factcheck-status-card wp-wikipedia-factcheck-status-card--${ signalSummary.tone }` }
						>
							<span className="wp-wikipedia-factcheck-status-card__label">
								{ __( 'Signal', 'wp-wikipedia-factcheck' ) }
							</span>
							<strong>{ signalSummary.title }</strong>
						</div>
						<div className="wp-wikipedia-factcheck-status-card wp-wikipedia-factcheck-status-card--credibility">
							<span className="wp-wikipedia-factcheck-status-card__label">
								{ __(
									'Credibility',
									'wp-wikipedia-factcheck'
								) }
							</span>
							<CredibilityBadge
								revertRisk={ result.revert_risk }
							/>
						</div>
					</div>

					<div className="wp-wikipedia-factcheck-result-header__main">
						<h3>{ result.name }</h3>
						<p className="wp-wikipedia-factcheck-hero__summary">
							{ signalSummary.body }
						</p>
						<div className="wp-wikipedia-factcheck-result-links">
							<ExternalLink href={ result.url }>
								{ __(
									'Open article',
									'wp-wikipedia-factcheck'
								) }
							</ExternalLink>
							{ result.wikidata_qid && result.wikidata_url && (
								<ExternalLink href={ result.wikidata_url }>
									{ sprintf(
										__(
											'Wikidata %s',
											'wp-wikipedia-factcheck'
										),
										result.wikidata_qid
									) }
								</ExternalLink>
							) }
						</div>
					</div>

					<div className="wp-wikipedia-factcheck-metrics">
						<div className="wp-wikipedia-factcheck-metric">
							<span className="wp-wikipedia-factcheck-metric__label">
								{ __( 'Freshness', 'wp-wikipedia-factcheck' ) }
							</span>
							<strong>
								{ getFreshnessLabel( result.date_modified ) }
							</strong>
							{ result.date_modified && (
								<span>
									{ relativeDate( result.date_modified ) }
								</span>
							) }
						</div>
						<div className="wp-wikipedia-factcheck-metric">
							<span className="wp-wikipedia-factcheck-metric__label">
								{ __( 'Use case', 'wp-wikipedia-factcheck' ) }
							</span>
							<strong>
								{ warnings.length > 0
									? __(
											'Needs closer review',
											'wp-wikipedia-factcheck'
									  )
									: __(
											'Good first-source check',
											'wp-wikipedia-factcheck'
									  ) }
							</strong>
							<span>
								{ __(
									'Use as a starting point, not a final source.',
									'wp-wikipedia-factcheck'
								) }
							</span>
						</div>
					</div>
				</div>
			</div>

			{ quickSummary && (
				<div className="wp-wikipedia-factcheck-section">
					<h4>{ __( 'Quick Summary', 'wp-wikipedia-factcheck' ) }</h4>
					<p className="wp-wikipedia-factcheck-lead">
						{ quickSummary }
					</p>
					{ abstract && abstract !== quickSummary && (
						<p>{ abstract }</p>
					) }
				</div>
			) }

			<div className="wp-wikipedia-factcheck-section">
				<h4>{ __( 'AI Research Brief', 'wp-wikipedia-factcheck' ) }</h4>
				<p>
					{ __(
						'Turn this Wikipedia match into a compact editor briefing with key facts, context, and follow-up angles tied to your current draft.',
						'wp-wikipedia-factcheck'
					) }
				</p>
				<div className="wp-wikipedia-factcheck-inline-actions">
					<button
						type="button"
						className="components-button is-secondary"
						onClick={ onGenerateBriefing }
						disabled={ briefingLoading }
					>
						{ briefingLoading
							? __(
									'Building briefing…',
									'wp-wikipedia-factcheck'
							  )
							: __(
									'Create briefing',
									'wp-wikipedia-factcheck'
							  ) }
					</button>
				</div>
				{ briefingError && (
					<Notice status="error" isDismissible={ false }>
						{ briefingError }
					</Notice>
				) }
				{ briefing && (
					<div className="wp-wikipedia-factcheck-briefing">
						<p className="wp-wikipedia-factcheck-lead">
							{ briefing.headline }
						</p>
						<p>{ briefing.why_relevant }</p>
						{ briefing.key_facts?.length > 0 && (
							<>
								<h5>
									{ __(
										'Key Facts To Use',
										'wp-wikipedia-factcheck'
									) }
								</h5>
								<ul className="wp-wikipedia-factcheck-list">
									{ briefing.key_facts.map( ( fact ) => (
										<li key={ fact }>{ fact }</li>
									) ) }
								</ul>
							</>
						) }
						{ briefing.angles?.length > 0 && (
							<>
								<h5>
									{ __(
										'Reporting Angles',
										'wp-wikipedia-factcheck'
									) }
								</h5>
								<ul className="wp-wikipedia-factcheck-list">
									{ briefing.angles.map( ( angle ) => (
										<li key={ angle }>{ angle }</li>
									) ) }
								</ul>
							</>
						) }
						{ briefing.cautions?.length > 0 && (
							<>
								<h5>
									{ __(
										'Cautions',
										'wp-wikipedia-factcheck'
									) }
								</h5>
								<ul className="wp-wikipedia-factcheck-list wp-wikipedia-factcheck-list--warning">
									{ briefing.cautions.map( ( caution ) => (
										<li key={ caution }>{ caution }</li>
									) ) }
								</ul>
							</>
						) }
					</div>
				) }
			</div>

			{ result.categories?.length > 0 && (
				<div className="wp-wikipedia-factcheck-section">
					<h4>{ __( 'Topic Tags', 'wp-wikipedia-factcheck' ) }</h4>
					<div className="wp-wikipedia-factcheck-categories">
						{ result.categories
							.filter(
								( category ) => ! warnings.includes( category )
							)
							.slice( 0, 6 )
							.map( ( category ) => (
								<span
									key={ category }
									className="wp-wikipedia-factcheck-category-pill"
								>
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
					<span>
						{ __( 'CC BY-SA 4.0', 'wp-wikipedia-factcheck' ) }
					</span>
				) }
			</div>
		</div>
	);
}
