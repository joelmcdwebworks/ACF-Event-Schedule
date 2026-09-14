(function (wp) {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var createElement = wp.element.createElement;
	var ServerSideRender = wp.serverSideRender;
	var __ = wp.i18n.__;

	registerBlockType('acf-event-schedule/schedule', {
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps({
				className: 'aes-schedule-block-editor',
			});

			return createElement(
				'div',
				blockProps,
				createElement(
					InspectorControls,
					null,
					createElement(
						PanelBody,
						{ title: __('Schedule settings', 'acf-event-schedule'), initialOpen: true },
						createElement(TextControl, {
							label: __('Event Post ID', 'acf-event-schedule'),
							help: __('Leave empty to use the current post.', 'acf-event-schedule'),
							value: attributes.postId ? String(attributes.postId) : '',
							onChange: function (value) {
								setAttributes({ postId: parseInt(value, 10) || 0 });
							},
							type: 'number',
						}),
						createElement(TextControl, {
							label: __('Date', 'acf-event-schedule'),
							help: __('Optional. Leave empty to show every day.', 'acf-event-schedule'),
							value: attributes.date || '',
							onChange: function (value) {
								setAttributes({ date: value });
							},
							type: 'date',
						})
					)
				),
				createElement(ServerSideRender, {
					block: 'acf-event-schedule/schedule',
					attributes: attributes,
				})
			);
		},
		save: function () {
			return null;
		},
	});
})(window.wp);
