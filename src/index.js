/**
 * WordPress dependencies
 */
import { registerPlugin } from '@wordpress/plugins';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import FactCheckSidebar from './sidebar/FactCheckSidebar';
import './style.scss';

registerPlugin( 'wp-wikipedia-factcheck', {
	render: FactCheckSidebar,
	icon: 'book-alt',
} );
