(function () {
	'use strict';

	if (typeof acf === 'undefined') {
		return;
	}

	function generateId() {
		if (window.crypto && typeof crypto.randomUUID === 'function') {
			return crypto.randomUUID();
		}

		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
			var r = (Math.random() * 16) | 0;
			var v = c === 'x' ? r : (r & 0x3) | 0x8;
			return v.toString(16);
		});
	}

	function idInputs($scope) {
		var $root = $scope && $scope.find ? $scope : acf.$el || jQuery(document);
		return $root.find(
			'[data-key="field_aes_time_block_id"] input[type="text"], [data-key="field_aes_space_id"] input[type="text"]'
		);
	}

	function fillEmptyIds($scope) {
		idInputs($scope).each(function () {
			if (!this.value) {
				this.value = generateId();
			}
		});
	}

	function replaceIds($scope) {
		idInputs($scope).each(function () {
			this.value = generateId();
		});
	}

	function populateSelect(field, choices, preserveValue) {
		if (!field || !field.$el) {
			return;
		}

		var current = field.val();
		var $select = field.$el.find('select').first();
		var value;

		$select.find('option').each(function () {
			if (this.value) {
				jQuery(this).remove();
			}
		});

		Object.keys(choices || {}).forEach(function (key) {
			$select.append(new Option(choices[key], key, false, false));
		});

		if (preserveValue && current && Object.prototype.hasOwnProperty.call(choices, current)) {
			value = current;
		} else {
			value = '';
		}

		field.val(value);
		$select.val(value).trigger('change');
	}

	function refreshSessionScheduleFields(eventId, preserveValue) {
		if (typeof aesAdmin === 'undefined') {
			return;
		}

		var body = new URLSearchParams();
		body.set('action', 'aes_get_event_schedule_options');
		body.set('nonce', aesAdmin.nonce);
		body.set('event_id', eventId || '0');

		window
			.fetch(aesAdmin.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: body.toString(),
			})
			.then(function (response) {
				return response.json();
			})
			.then(function (result) {
				if (!result || !result.success || !result.data) {
					return;
				}

				populateSelect(acf.getField('field_aes_session_time_block'), result.data.time_blocks, preserveValue);
				populateSelect(acf.getField('field_aes_session_space'), result.data.spaces, preserveValue);
			})
			.catch(function () {
				/* Fail silently; ACF validation still runs server-side. */
			});
	}

	function getEventId(fieldKey) {
		var field = acf.getField(fieldKey);
		var value;

		if (!field || typeof field.val !== 'function') {
			return 0;
		}

		value = field.val();

		if (Array.isArray(value)) {
			return value[0] || 0;
		}

		if (value && typeof value === 'object') {
			return value.ID || value.id || 0;
		}

		return value || 0;
	}

	function clearRelationship(field) {
		if (!field || typeof field.val !== 'function') {
			return;
		}

		field.val([]);
	}

	function refreshRelationshipField(field) {
		if (!field) {
			return;
		}

		field.set('paged', 1);

		if (typeof field.fetch === 'function') {
			field.fetch();
		}
	}

	acf.addAction('ready', function () {
		fillEmptyIds();
	});

	acf.addAction('append', function ($el) {
		fillEmptyIds($el);
	});

	acf.addAction('duplicate', function ($el) {
		replaceIds($el);
	});

	acf.addAction('new_field/key=field_aes_session_event', function (field) {
		field.on('change', function () {
			refreshSessionScheduleFields(field.val(), false);
			var speakersField = acf.getField('field_aes_session_speakers');
			clearRelationship(speakersField);
			refreshRelationshipField(speakersField);
		});
	});

	acf.addAction('new_field/key=field_aes_speaker_event', function (field) {
		field.on('change', function () {
			var sessionsField = acf.getField('field_aes_speaker_sessions');
			clearRelationship(sessionsField);
			refreshRelationshipField(sessionsField);
		});
	});

	acf.addFilter('relationship_ajax_data', function (data, field) {
		var key = field && typeof field.get === 'function' ? field.get('key') : '';

		if (key === 'field_aes_speaker_sessions') {
			data.aes_event_id = getEventId('field_aes_speaker_event');
		}

		if (key === 'field_aes_session_speakers') {
			data.aes_event_id = getEventId('field_aes_session_event');
		}

		return data;
	});
})();
