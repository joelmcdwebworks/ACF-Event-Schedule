(function () {
	'use strict';

	if (typeof wp === 'undefined' || !wp.data || !wp.data.dispatch) {
		return;
	}

	var taxonomy = (typeof aesEditor !== 'undefined' && aesEditor.taxonomy) || 'aes_linked_event';
	var panel = 'taxonomy-panel-' + taxonomy;

	function hidePanel() {
		var select = wp.data.select('core/edit-post');
		if (select && typeof select.isEditorPanelRemoved === 'function' && select.isEditorPanelRemoved(panel)) {
			return;
		}

		var dispatch = wp.data.dispatch('core/edit-post');
		if (dispatch && typeof dispatch.removeEditorPanel === 'function') {
			dispatch.removeEditorPanel(panel);
		}
	}

	function start() {
		hidePanel();
		if (wp.data.subscribe) {
			wp.data.subscribe(hidePanel);
		}
	}

	if (wp.domReady) {
		wp.domReady(start);
	} else {
		start();
	}
})();
