/* global wp, document, window */
(function () {
	'use strict';
	const h = wp.element.createElement;
	const { useEffect, useRef, useState } = wp.element;
	const api = wp.apiFetch;
	const t = wp.i18n.__;
	const cfg = window.uopM3Builder || {};
	api.use(api.createNonceMiddleware(cfg.nonce || ''));
	const types = ['text', 'textarea', 'email', 'phone', 'number', 'date', 'select', 'radio', 'checkbox', 'multiselect', 'consent'];
	const selectable = ['select', 'radio', 'multiselect'];
	const defaultField = () => ({ key: 'name', type: 'text', label: 'Name', required: true });
	const clone = value => JSON.parse(JSON.stringify(value));
	const fieldId = (key, property) => 'uop-' + key.replace(/[^a-zA-Z0-9_]/g, '-') + '-' + property;
	const safeKey = value => value.toLowerCase().replace(/[^a-z0-9_]/g, '_').replace(/^([^a-z])/, 'f_$1').slice(0, 100);

	function Builder() {
		const [forms, setForms] = useState([]);
		const [selected, setSelected] = useState('');
		const [record, setRecord] = useState(null);
		const [draft, setDraft] = useState(null);
		const [create, setCreate] = useState({ key: '', title: '', context: 'event' });
		const [dirty, setDirty] = useState(false);
		const [busy, setBusy] = useState(false);
		const [error, setError] = useState('');
		const [notice, setNotice] = useState('');
		const alertRef = useRef(null);
		const dragIndex = useRef(null);

		function failed(reason) {
			setNotice('');
			setError(reason && reason.message ? reason.message : t('An action failed. No changes were saved.', 'uop-core'));
		}
		useEffect(() => { if (error && alertRef.current) alertRef.current.focus(); }, [error]);
		async function refreshForms() {
			const response = await api({ path: '/uop/v1/forms' });
			setForms(response.items || []);
		}
		useEffect(() => { refreshForms().catch(failed); }, []);
		async function load(uuid) {
			setError(''); setNotice('');
			if (!uuid) { setSelected(''); setRecord(null); setDraft(null); setDirty(false); return; }
			try {
				const data = await api({ path: '/uop/v1/forms/' + uuid });
				setRecord(data); setDraft(clone(data.draft)); setSelected(uuid); setDirty(false);
			} catch (e) { failed(e); }
		}
		function edit(mutator) {
			setDraft(previous => {
				const next = clone(previous);
				mutator(next.fields);
				return next;
			});
			setDirty(true); setNotice(''); setError('');
		}
		function move(from, to) {
			if (!draft || from === to || from < 0 || to < 0 || to >= draft.fields.length) return;
			edit(items => { const [item] = items.splice(from, 1); items.splice(to, 0, item); });
			setNotice(t('Field order changed. Save the draft to keep your changes.', 'uop-core'));
		}
		async function make() {
			setBusy(true); setError(''); setNotice('');
			try {
				const result = await api({ path: '/uop/v1/forms', method: 'POST', data: { ...create, draft: { schema_version: 1, fields: [defaultField()] } } });
				await refreshForms(); await load(result.public_id);
				setNotice(t('Form draft created.', 'uop-core'));
			} catch (e) { failed(e); } finally { setBusy(false); }
		}
		async function save() {
			setBusy(true); setError(''); setNotice('');
			try {
				const result = await api({ path: '/uop/v1/forms/' + record.public_id + '/draft', method: 'PUT', data: { revision: record.revision, draft } });
				setRecord(result); setDraft(clone(result.draft)); setDirty(false);
				await refreshForms(); setNotice(t('Draft saved.', 'uop-core'));
			} catch (e) { failed(e); } finally { setBusy(false); }
		}
		async function publish() {
			setBusy(true); setError(''); setNotice('');
			try {
				const result = await api({ path: '/uop/v1/forms/' + record.public_id + '/publish', method: 'POST', data: { revision: record.revision } });
				setRecord(result); setDirty(false);
				await refreshForms(); setNotice(t('Immutable version published.', 'uop-core'));
			} catch (e) { failed(e); } finally { setBusy(false); }
		}
		function fieldControl(field, index, property, label, inputType, options) {
			const id = fieldId(field.key + '-' + index, property);
			const value = field[property] === undefined ? '' : field[property];
			const props = {
				id, value, disabled: busy,
				onChange: ev => edit(items => {
					if (property === 'type') {
						items[index].type = ev.target.value;
						delete items[index].options; delete items[index].consent_definition_public_id;
						if (selectable.includes(items[index].type)) items[index].options = ['Option 1'];
					} else {
						items[index][property] = property === 'key' ? safeKey(ev.target.value) : ev.target.value;
					}
				})
			};
			let control;
			if (inputType === 'select') control = h('select', props, options.map(option => h('option', { key: option, value: option }, option)));
			else control = h('input', { ...props, type: inputType || 'text', maxLength: property === 'label' ? 191 : 100 });
			return h('label', { className: 'uop-m3__control', htmlFor: id, key: property }, h('span', null, label), control);
		}
		function renderField(field, index) {
			const length = draft.fields.length;
			const prefix = fieldId(field.key + '-' + index, 'required');
			const options = selectable.includes(field.type) ? h('label', { className: 'uop-m3__control' },
				t('Options (one per line)', 'uop-core'),
				h('textarea', {
					value: (field.options || []).join('\n'), rows: 3, disabled: busy,
					onChange: ev => edit(items => { items[index].options = ev.target.value.split('\n').map(v => v.trim()).filter(Boolean); })
				})
			) : null;
			const others = draft.fields.filter((item, i) => i !== index && item.type !== 'consent');
			const condition = field.visible_when && field.visible_when.all && field.visible_when.all[0];
			const dependent = condition && condition.field || '';
			const shown = condition && typeof condition.value === 'string' ? condition.value : '';
			function setCondition(nextField, nextValue) {
				edit(items => {
					if (!nextField) delete items[index].visible_when;
					else items[index].visible_when = { schema_version: 1, all: [{ source: 'registration', field: nextField, operator: 'eq', value: nextValue }] };
				});
			}
			return h('li', {
				key: field.key + '-' + index, className: 'uop-m3__field',
				draggable: !busy, onDragStart: () => { dragIndex.current = index; },
				onDragOver: ev => ev.preventDefault(),
				onDrop: ev => { ev.preventDefault(); move(dragIndex.current, index); dragIndex.current = null; },
				'aria-label': field.label + ', ' + t('position', 'uop-core') + ' ' + (index + 1)
			},
			h('div', { className: 'uop-m3__field-header' },
				h('h3', { className: 'uop-m3__heading' }, (index + 1) + '. ' + field.label),
				h('div', { className: 'uop-m3__field-tools' },
					h('button', { type: 'button', disabled: busy || index === 0, onClick: () => move(index, index - 1), 'aria-label': t('Move field up', 'uop-core') + ': ' + field.label }, '↑'),
					h('button', { type: 'button', disabled: busy || index === length - 1, onClick: () => move(index, index + 1), 'aria-label': t('Move field down', 'uop-core') + ': ' + field.label }, '↓'),
					h('label', null, t('Position', 'uop-core') + ' ',
						h('select', { disabled: busy, value: index, 'aria-label': t('Move field to position', 'uop-core') + ': ' + field.label, onChange: ev => move(index, Number(ev.target.value)) },
							draft.fields.map((item, i) => h('option', { key: i, value: i }, i + 1))
						)
					),
					h('button', { type: 'button', disabled: busy || length === 1, onClick: () => edit(items => items.splice(index, 1)) }, t('Remove', 'uop-core'))
				)
			),
			h('div', { className: 'uop-m3__cols' },
				fieldControl(field, index, 'key', t('Field key', 'uop-core')),
				fieldControl(field, index, 'label', t('Label', 'uop-core')),
				fieldControl(field, index, 'type', t('Type', 'uop-core'), 'select', types)
			),
			h('label', { htmlFor: prefix }, h('input', {
				id: prefix, type: 'checkbox', disabled: busy, checked: Boolean(field.required),
				onChange: ev => edit(items => { items[index].required = ev.target.checked; })
			}), ' ', t('Required', 'uop-core')),
			options,
			field.type === 'consent' ? fieldControl(field, index, 'consent_definition_public_id', t('Consent definition UUID', 'uop-core')) : null,
			others.length ? h('div', { className: 'uop-m3__cols' },
				h('label', { className: 'uop-m3__control' }, t('Visible when another field equals', 'uop-core'),
					h('select', { disabled: busy, value: dependent, onChange: ev => setCondition(ev.target.value, shown) },
						h('option', { value: '' }, t('Always visible', 'uop-core')),
						others.map(other => h('option', { key: other.key, value: other.key }, other.label))
					)
				),
				dependent ? h('label', { className: 'uop-m3__control' }, t('Expected value', 'uop-core'),
					h('input', { disabled: busy, value: shown, onChange: ev => setCondition(dependent, ev.target.value) })
				) : null
			) : null,
			h('p', { className: 'uop-m3__hint' }, t('Drag to reorder, or use the arrow buttons or position selector.', 'uop-core'))
			);
		}
		return h('div', { className: 'uop-m3' },
			error ? h('div', { role: 'alert', tabIndex: -1, ref: alertRef, className: 'uop-m3__alert' }, error) : null,
			notice ? h('div', { role: 'status', 'aria-live': 'polite', className: 'uop-m3__status' }, notice) : null,
			h('section', { className: 'uop-m3__toolbar', 'aria-label': t('Open form', 'uop-core') },
				h('label', { className: 'uop-m3__control' }, t('Existing forms', 'uop-core'),
					h('select', { value: selected, disabled: busy || dirty, onChange: ev => load(ev.target.value) },
						h('option', { value: '' }, t('Choose a form', 'uop-core')),
						forms.map(form => h('option', { key: form.public_id, value: form.public_id }, form.title + ' (' + form.status + ')'))
					)
				),
				h('p', { className: 'uop-m3__hint' }, dirty ? t('Save before switching forms.', 'uop-core') : '')
			),
			!record ? h('section', { className: 'uop-m3__panel', 'aria-label': t('Create form', 'uop-core') },
				h('h2', null, t('New form', 'uop-core')),
				h('div', { className: 'uop-m3__cols' },
					['key', 'title'].map(prop => h('label', { key: prop, className: 'uop-m3__control' }, prop === 'key' ? t('Form key', 'uop-core') : t('Title', 'uop-core'),
						h('input', { value: create[prop], required: true, disabled: busy, maxLength: prop === 'key' ? 100 : 191, onChange: ev => setCreate(old => ({ ...old, [prop]: prop === 'key' ? safeKey(ev.target.value) : ev.target.value })) })
					)),
					h('label', { className: 'uop-m3__control' }, t('Context', 'uop-core'),
						h('select', { value: create.context, disabled: busy, onChange: ev => setCreate(old => ({ ...old, context: ev.target.value })) },
							h('option', { value: 'event' }, t('Event', 'uop-core')),
							h('option', { value: 'organization' }, t('Organization', 'uop-core'))
						)
					)
				),
				h('button', { type: 'button', className: 'button button-primary', disabled: busy || !create.key || !create.title, onClick: make }, t('Create draft', 'uop-core'))
			) : h('section', { className: 'uop-m3__panel', 'aria-label': t('Edit draft', 'uop-core') },
				h('h2', null, record.title),
				h('p', null, t('Draft revision:', 'uop-core') + ' ' + record.revision + ' · ' + t('Status:', 'uop-core') + ' ' + record.status),
				record.published ? h('p', { className: 'uop-m3__hint' }, t('Last published version:', 'uop-core') + ' ' + record.published.version) : null,
				h('ol', { className: 'uop-m3__form-fields' }, draft.fields.map(renderField)),
				h('div', { className: 'uop-m3__actions' },
					h('button', { type: 'button', className: 'button', disabled: busy || draft.fields.length >= 100,
						onClick: () => edit(items => {
							const used = new Set(items.map(item => item.key));
							let key = 'field_' + (items.length + 1);
							while (used.has(key)) key += '_new';
							items.push({ key, label: t('New field', 'uop-core'), type: 'text', required: false });
						}) }, t('Add field', 'uop-core')),
					h('button', { type: 'button', className: 'button button-primary', disabled: busy || !dirty, onClick: save }, t('Save draft', 'uop-core')),
					h('button', { type: 'button', className: 'button', disabled: busy || dirty, onClick: publish }, t('Publish immutable version', 'uop-core'))
				),
				dirty ? h('p', { className: 'uop-m3__hint', role: 'status' }, t('Unsaved changes. Publish is disabled until the draft is saved.', 'uop-core')) : null
			)
		);
	}
	const container = document.getElementById('uop-m3-form-builder');
	if (container) wp.element.createRoot(container).render(h(Builder));
}());
