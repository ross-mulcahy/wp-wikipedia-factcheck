/**
 * WordPress dependencies
 */
import { registerPlugin } from '@wordpress/plugins';
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import FactCheckSidebar from './sidebar/FactCheckSidebar';
import factBoxMetadata from './blocks/fact-box/block.json';
import tooltipMetadata from './blocks/tooltip/block.json';
import FactBoxEdit from './blocks/FactBoxEdit';
import TooltipEdit from './blocks/TooltipEdit';
import './style.scss';

registerPlugin( 'wp-wikipedia-factcheck', {
	render: FactCheckSidebar,
	icon: 'book-alt',
} );

registerBlockType( factBoxMetadata.name, {
	...factBoxMetadata,
	edit: FactBoxEdit,
	save: () => null,
} );

registerBlockType( tooltipMetadata.name, {
	...tooltipMetadata,
	edit: TooltipEdit,
	save: () => null,
} );
