/**
 * WordPress dependencies
 */
import { Tooltip } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Get badge properties based on revert risk score.
 *
 * @param {number|null} revertRisk Revert risk probability (0-1) or null.
 * @return {Object} Badge label and color class.
 */
function getBadgeProps( revertRisk ) {
	if ( revertRisk === null || revertRisk === undefined ) {
		return {
			label: __( 'No score', 'wp-wikipedia-factcheck' ),
			className: 'wp-wikipedia-factcheck-badge--grey',
		};
	}

	if ( revertRisk <= 0.15 ) {
		return {
			label: __( 'High credibility', 'wp-wikipedia-factcheck' ),
			className: 'wp-wikipedia-factcheck-badge--green',
		};
	}

	if ( revertRisk <= 0.4 ) {
		return {
			label: __( 'Moderate', 'wp-wikipedia-factcheck' ),
			className: 'wp-wikipedia-factcheck-badge--amber',
		};
	}

	return {
		label: __( 'Flagged', 'wp-wikipedia-factcheck' ),
		className: 'wp-wikipedia-factcheck-badge--red',
	};
}

export default function CredibilityBadge( { revertRisk } ) {
	const { label, className } = getBadgeProps( revertRisk );

	return (
		<Tooltip
			text={ __(
				'Revert risk score from Wikimedia: probability this article version was vandalism.',
				'wp-wikipedia-factcheck'
			) }
		>
			<span className={ `wp-wikipedia-factcheck-badge ${ className }` }>
				{ label }
			</span>
		</Tooltip>
	);
}
