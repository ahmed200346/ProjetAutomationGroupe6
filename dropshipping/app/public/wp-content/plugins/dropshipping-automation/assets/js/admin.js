(function () {
	'use strict';

	var storageKey = 'dsa-admin-theme';
	var root = document.documentElement;
	var toggle = document.querySelector('.dsa-theme-toggle');
	var toastRegion = document.querySelector('.dsa-toast-region');
	var colorScheme = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

	function setTheme(theme) {
		var isDark = theme === 'dark';
		root.setAttribute('data-dsa-theme', isDark ? 'dark' : 'light');

		if (toggle) {
			toggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
			toggle.setAttribute('aria-label', isDark ? toggle.getAttribute('data-label-light') : toggle.getAttribute('data-label-dark'));
		}
	}

	var savedTheme = null;
	var manualTheme = false;
	try {
		savedTheme = window.localStorage.getItem(storageKey);
	} catch (error) {
		savedTheme = null;
	}

	if (savedTheme === 'dark' || savedTheme === 'light') {
		manualTheme = true;
		setTheme(savedTheme);
	} else {
		setTheme(colorScheme && colorScheme.matches ? 'dark' : 'light');
	}

	if (colorScheme) {
		var updateSystemTheme = function (event) {
			if (!manualTheme) {
				setTheme(event.matches ? 'dark' : 'light');
			}
		};

		if (colorScheme.addEventListener) {
			colorScheme.addEventListener('change', updateSystemTheme);
		} else if (colorScheme.addListener) {
			colorScheme.addListener(updateSystemTheme);
		}
	}

	if (toggle) {
		toggle.addEventListener('click', function () {
			var theme = root.getAttribute('data-dsa-theme') === 'dark' ? 'light' : 'dark';
			manualTheme = true;
			setTheme(theme);
			try {
				window.localStorage.setItem(storageKey, theme);
			} catch (error) {
				// The selected theme still applies for this page when storage is unavailable.
			}
		});
	}

	window.dsaToast = function (message, type) {
		if (!toastRegion || typeof message !== 'string' || !message.trim()) {
			return;
		}

		var toast = document.createElement('div');
		toast.className = 'dsa-toast' + (type === 'error' ? ' is-error' : '');
		toast.textContent = message;
		toastRegion.appendChild(toast);
		window.setTimeout(function () {
			toast.classList.add('is-leaving');
			window.setTimeout(function () {
				toast.remove();
			}, 220);
		}, 3600);
	};

	(function () {
		var data = window.dsaProvidersData;
		if (!data || !data.restUrl) {
			return;
		}
		var state = {};
		var request = function (provider, path, options) {
			options = options || {};
			options.headers = Object.assign({ 'X-WP-Nonce': data.nonce, 'Content-Type': 'application/json' }, options.headers || {});
			return fetch(data.restUrl + provider + path, options).then(function (response) {
				return response.json().then(function (body) {
					if (!response.ok) { throw new Error(body.message || 'Une erreur est survenue.'); }
					return body;
				});
			});
		};
		var formatSize = function (size) {
			if (!size) { return ''; }
			var units = ['o', 'Ko', 'Mo', 'Go', 'To'];
			var index = 0;
			var value = size;
			while (value >= 1024 && index < units.length - 1) { value /= 1024; index += 1; }
			return (index ? value.toFixed(1) : value) + ' ' + units[index];
		};
		var setStatus = function (card, label, type) {
			var badge = card.querySelector('[data-status]');
			badge.textContent = label;
			badge.className = 'dsa-status-badge' + (type ? ' is-' + type : '');
		};
		var render = function (card) {
			var provider = card.getAttribute('data-provider-card');
			var current = state[provider] || { models: [], enabled: [], default_model: '' };
			var panel = card.querySelector('[data-model-panel]');
			var list = card.querySelector('[data-model-list]');
			var query = (card.querySelector('[data-model-search]').value || '').toLowerCase();
			var models = current.models.filter(function (model) { return (model.name + ' ' + model.id).toLowerCase().indexOf(query) !== -1; });
			card.querySelector('[data-model-count]').textContent = models.length + ' modèle' + (models.length > 1 ? 's' : '');
			list.innerHTML = '';
			models.forEach(function (model) {
				var row = document.createElement('label');
				row.className = 'dsa-model-row';
				var checkbox = document.createElement('input');
				checkbox.type = 'checkbox';
				checkbox.checked = current.enabled.indexOf(model.id) !== -1;
				checkbox.addEventListener('change', function () {
					if (checkbox.checked && current.enabled.indexOf(model.id) === -1) { current.enabled.push(model.id); }
					if (!checkbox.checked) { current.enabled = current.enabled.filter(function (id) { return id !== model.id; }); if (current.default_model === model.id) { current.default_model = ''; } }
					render(card);
				});
				var copy = document.createElement('span');
				copy.innerHTML = '<strong></strong><small></small>';
				copy.querySelector('strong').textContent = model.name || model.id;
				copy.querySelector('small').textContent = model.id + (model.family ? ' · ' + model.family : '') + (model.quantization ? ' · ' + model.quantization : '') + (model.size ? ' · ' + formatSize(model.size) : '');
				row.appendChild(checkbox);
				row.appendChild(copy);
				if (checkbox.checked) {
					var radio = document.createElement('input');
					radio.type = 'radio';
					radio.name = 'dsa-default-' + provider;
					radio.checked = current.default_model === model.id;
					radio.title = 'Modèle par défaut';
					radio.addEventListener('change', function () { current.default_model = model.id; });
					var defaultLabel = document.createElement('span');
					defaultLabel.className = 'dsa-default-model';
					defaultLabel.appendChild(radio);
					defaultLabel.appendChild(document.createTextNode(' Défaut'));
					row.appendChild(defaultLabel);
				}
				list.appendChild(row);
			});
			panel.hidden = false;
		};
		var discover = function (card, refresh) {
			var provider = card.getAttribute('data-provider-card');
			var body = { refresh: !!refresh };
			var key = card.querySelector('[data-api-key]');
			var host = card.querySelector('[data-host]');
			if (key && key.value) { body.api_key = key.value; }
			if (host) { body.host = host.value; }
			setStatus(card, 'Connexion…', 'loading');
			request(provider, '/discover', { method: 'POST', body: JSON.stringify(body) }).then(function (result) {
				state[provider] = state[provider] || {};
				state[provider].models = result.models || [];
				state[provider].enabled = state[provider].enabled || [];
				state[provider].default_model = state[provider].default_model || '';
				setStatus(card, 'Connecté', 'success');
				render(card);
			}).catch(function (error) { setStatus(card, 'Erreur', 'error'); window.dsaToast(error.message, 'error'); });
		};
		document.querySelectorAll('[data-provider-card]').forEach(function (card) {
			var provider = card.getAttribute('data-provider-card');
			request(provider, '').then(function (config) {
				state[provider] = config;
				if (provider === 'ollama') { card.querySelector('[data-host]').value = config.host || 'http://localhost:11434'; }
				if (config.key_configured && card.querySelector('[data-key-state]')) { card.querySelector('[data-key-state]').textContent = config.key_mask + ' · Remplacez ou laissez vide pour conserver.'; card.querySelector('[data-remove-key]').hidden = false; }
				if (config.configured) { setStatus(card, 'Connecté', 'success'); }
				if (config.models && config.models.length) { render(card); }
			}).catch(function () { setStatus(card, 'Erreur', 'error'); });
			card.querySelector('[data-discover]').addEventListener('click', function () { discover(card, false); });
			card.querySelector('[data-refresh]').addEventListener('click', function () { discover(card, true); });
			card.querySelector('[data-model-search]').addEventListener('input', function () { render(card); });
			card.querySelector('[data-save]').addEventListener('click', function () {
				var current = state[provider] || {};
				var body = { models: current.models || [], enabled: current.enabled || [], default_model: current.default_model || '' };
				var host = card.querySelector('[data-host]');
				var key = card.querySelector('[data-api-key]');
				if (host) { body.host = host.value; }
				if (key && key.value) { body.api_key = key.value; }
				request(provider, '', { method: 'POST', body: JSON.stringify(body) }).then(function (config) { state[provider] = config; if (key) { key.value = ''; } if (config.key_configured && card.querySelector('[data-key-state]')) { card.querySelector('[data-key-state]').textContent = config.key_mask + ' · Remplacez ou laissez vide pour conserver.'; card.querySelector('[data-remove-key]').hidden = false; } setStatus(card, 'Connecté', 'success'); window.dsaToast('Sélection enregistrée.'); }).catch(function (error) { window.dsaToast(error.message, 'error'); });
			});
			var replaceKey = card.querySelector('[data-replace-key]');
			if (replaceKey) { replaceKey.addEventListener('click', function () { card.querySelector('[data-api-key]').focus(); }); }
			var removeKey = card.querySelector('[data-remove-key]');
			if (removeKey) { removeKey.addEventListener('click', function () { var current = state[provider] || {}; request(provider, '', { method: 'POST', body: JSON.stringify({ remove_key: true, models: current.models || [], enabled: current.enabled || [], default_model: current.default_model || '' }) }).then(function (config) { state[provider] = config; removeKey.hidden = true; card.querySelector('[data-key-state]').textContent = ''; setStatus(card, 'Non configuré', ''); window.dsaToast('Clé supprimée.'); }).catch(function (error) { window.dsaToast(error.message, 'error'); }); }); }
			var toggleSecret = card.querySelector('[data-toggle-secret]');
			if (toggleSecret) { toggleSecret.addEventListener('click', function () { var key = card.querySelector('[data-api-key]'); key.type = key.type === 'password' ? 'text' : 'password'; toggleSecret.textContent = key.type === 'password' ? 'Afficher' : 'Masquer'; }); }
		});
	}());

	(function () {
		var planner = document.querySelector('[data-workflow-planner]');
		var data = window.dsaWorkflowData;
		if (!planner || !data || !data.restUrl) { return; }

		var state = { active: 'products', schedules: {}, status: {}, history: [], health: {}, demo: {} };
		var previewTimer = null;
		var previewRequestId = 0;
		var workflowLabels = { products: 'Scraping produits', trends: 'Scraping tendances' };
		var weekdayNames = { 0: 'dimanche', 1: 'lundi', 2: 'mardi', 3: 'mercredi', 4: 'jeudi', 5: 'vendredi', 6: 'samedi' };

		function api(path, method, body) {
			return fetch(data.restUrl + path, {
				method: method || 'GET',
				headers: { 'X-WP-Nonce': data.nonce, 'Content-Type': 'application/json' },
				body: body ? JSON.stringify(body) : undefined
			}).then(function (response) {
				return response.json().then(function (payload) {
					if (!response.ok) { throw new Error(payload.message || 'Une erreur est survenue.'); }
					return payload;
				});
			});
		}

		function loadProductInputs() {
			var query = planner.querySelector('[data-n8n-query]');
			if (!query) { return Promise.resolve(); }
			return api('/product-settings', 'GET').then(function (settings) {
				query.value = settings.query || '';
				planner.querySelector('[data-n8n-marketplace]').value = settings.marketplace || 'aliexpress';
			});
		}

		function activePanel() {
			return planner.querySelector('[data-mode-panel="' + selectedMode() + '"]');
		}

		function selectedMode() {
			var tab = planner.querySelector('[data-mode][aria-selected="true"]');
			return tab ? tab.getAttribute('data-mode') : 'manual';
		}

		function selectMode(mode) {
			planner.querySelectorAll('[data-mode]').forEach(function (tab) {
				var selected = tab.getAttribute('data-mode') === mode;
				tab.setAttribute('aria-selected', selected ? 'true' : 'false');
			});
			planner.querySelectorAll('[data-mode-panel]').forEach(function (panel) {
				panel.hidden = panel.getAttribute('data-mode-panel') !== mode;
			});
			updateMonthlyFields();
			planner.querySelector('[data-schedule-badge]').textContent = mode === 'manual' ? 'Manuel' : (planner.querySelector('[data-schedule-enabled]').checked ? 'Activé' : 'Désactivé');
			requestPreview();
		}

		function getField(container, name) {
			return container ? container.querySelector('[data-field="' + name + '"]') : null;
		}

		function collectConfig() {
			var mode = selectedMode();
			var panel = activePanel();
			var config = {
				mode: mode,
				enabled: planner.querySelector('[data-schedule-enabled]').checked,
				timezone: getField(planner, 'timezone').value,
				start_at: getField(planner, 'start_at').value,
				end_at: getField(planner, 'end_at').value
			};
			if (mode === 'interval') {
				config.amount = getField(panel, 'amount').value;
				config.unit = getField(panel, 'unit').value;
			} else if (mode === 'daily') {
				config.time = getField(panel, 'time').value;
			} else if (mode === 'weekly') {
				config.time = getField(panel, 'time').value;
				config.weekdays = Array.prototype.map.call(panel.querySelectorAll('[data-weekday]:checked'), function (input) { return input.value; });
			} else if (mode === 'monthly') {
				config.time = getField(panel, 'time').value;
				config.monthly_type = panel.querySelector('[data-monthly-type]:checked').value;
				if (config.monthly_type === 'day') {
					config.day = getField(panel, 'day').value;
					config.last_day = getField(panel, 'last_day').checked;
				} else {
					config.ordinal = getField(panel, 'ordinal').value;
					config.weekday = getField(panel, 'weekday').value;
				}
			} else if (mode === 'once') {
				config.once_at = getField(panel, 'once_at').value;
			} else if (mode === 'advanced') {
				config.cron = getField(panel, 'cron').value;
			}
			return config;
		}

		function fillConfig(config) {
			config = config || {};
			planner.querySelector('[data-schedule-enabled]').checked = !!config.enabled;
			planner.querySelector('[data-enabled-label]').textContent = config.enabled ? 'Activé' : 'Désactivé';
			getField(planner, 'timezone').value = config.timezone || data.timezone || 'UTC';
			getField(planner, 'start_at').value = config.start_at || '';
			getField(planner, 'end_at').value = config.end_at || '';
			var mode = config.mode || 'daily';
			selectModeWithoutPreview(mode);
			var panel = planner.querySelector('[data-mode-panel="' + mode + '"]');
			if (mode === 'interval') {
				getField(panel, 'amount').value = config.amount || 1;
				getField(panel, 'unit').value = config.unit || 'hours';
			} else if (mode === 'daily' || mode === 'weekly' || mode === 'monthly') {
				getField(panel, 'time').value = config.time || '02:00';
				if (mode === 'weekly') {
					panel.querySelectorAll('[data-weekday]').forEach(function (input) { input.checked = (config.weekdays || []).map(String).indexOf(input.value) !== -1; });
				}
				if (mode === 'monthly') {
					var monthlyType = config.monthly_type || 'day';
					panel.querySelector('[data-monthly-type][value="' + monthlyType + '"]').checked = true;
					getField(panel, 'day').value = config.day === 'last' ? 1 : (config.day || 1);
					getField(panel, 'last_day').checked = config.day === 'last';
					getField(panel, 'ordinal').value = config.ordinal || 1;
					getField(panel, 'weekday').value = config.weekday !== undefined ? config.weekday : 1;
					updateMonthlyFields();
				}
			} else if (mode === 'once') {
				getField(panel, 'once_at').value = config.once_at || '';
			} else if (mode === 'advanced') {
				getField(panel, 'cron').value = config.cron || '';
			}
			planner.querySelector('[data-enabled-label]').textContent = config.enabled ? 'Activé' : 'Désactivé';
			planner.querySelector('[data-schedule-badge]').textContent = mode === 'manual' ? 'Manuel' : (config.enabled ? 'Activé' : 'Désactivé');
		}

		function selectModeWithoutPreview(mode) {
			planner.querySelectorAll('[data-mode]').forEach(function (tab) { tab.setAttribute('aria-selected', tab.getAttribute('data-mode') === mode ? 'true' : 'false'); });
			planner.querySelectorAll('[data-mode-panel]').forEach(function (panel) { panel.hidden = panel.getAttribute('data-mode-panel') !== mode; });
		}

		function updateMonthlyFields() {
			var panel = planner.querySelector('[data-mode-panel="monthly"]');
			if (!panel) { return; }
			var weekday = panel.querySelector('[data-monthly-type][value="weekday"]').checked;
			panel.querySelector('[data-monthly-day-fields]').hidden = weekday;
			panel.querySelector('[data-monthly-weekday-fields]').hidden = !weekday;
			getField(panel, 'day').disabled = getField(panel, 'last_day').checked;
		}

		function renderOccurrences(items) {
			var list = planner.querySelector('[data-occurrences]');
			list.textContent = '';
			if (!items || !items.length) {
				var empty = document.createElement('li');
				empty.className = 'is-empty';
				empty.textContent = 'Aucune date planifiée.';
				list.appendChild(empty);
				return;
			}
			items.slice(0, 5).forEach(function (item) {
				var row = document.createElement('li');
				row.textContent = item.label;
				list.appendChild(row);
			});
		}

		function requestPreview() {
			window.clearTimeout(previewTimer);
			previewTimer = window.setTimeout(function () {
				var config = collectConfig();
				var requestId = ++previewRequestId;
				api('/preview', 'POST', config).then(function (preview) {
					if (requestId !== previewRequestId) { return; }
					planner.querySelector('[data-schedule-error]').hidden = true;
					planner.querySelector('[data-schedule-summary]').textContent = preview.explanation || '';
					planner.querySelector('[data-cron-expression]').textContent = preview.expression || '';
					planner.querySelector('[data-cron-explanation]').textContent = preview.explanation || '';
					renderOccurrences(preview.occurrences);
				}).catch(function (error) {
					if (requestId !== previewRequestId) { return; }
					planner.querySelector('[data-schedule-error]').textContent = error.message;
					planner.querySelector('[data-schedule-error]').hidden = false;
					planner.querySelector('[data-schedule-summary]').textContent = 'Configuration à corriger.';
					renderOccurrences([]);
				});
			}, 220);
		}

		function renderStatus(payload) {
			state.schedules = payload.schedules || {};
			state.status = payload.status || {};
			state.history = payload.history || [];
			state.health = payload.health || {};
			var active = state.status[state.active] || {};
			planner.querySelector('[data-schedule-summary]').textContent = active.summary || '';
			planner.querySelector('[data-cron-expression]').textContent = active.expression || '';
			renderOccurrences(active.upcoming || []);
			var last = active.last;
			planner.querySelector('[data-last-run]').textContent = last ? formatDate(last.created_at) + ' · ' + (last.source === 'manual' ? 'Manuelle' : 'Planifiée') : 'Aucune exécution enregistrée.';
			planner.querySelector('[data-last-result]').textContent = last ? (last.result || last.status || '') : '';
			renderHistory();
			renderWorkflowCards();
			renderHealth();
		}

		function renderWorkflowCards() {
			var categoryNames = { 1: 'Gadgets maison', 2: 'Accessoires téléphone', 3: 'Fitness & bien-être', 4: 'Beauté & soins', 5: 'Animaux', 6: 'Cuisine pratique', 7: 'Rangement & bureau', 8: 'Loisirs plein air' };
			var labels = { platforms: { x: 'X', facebook: 'Facebook', instagram: 'Instagram' }, suppliers: { amazon: 'Amazon', alibaba: 'Alibaba', aliexpress: 'AliExpress' } };
			planner.querySelectorAll('[data-workflow-card]').forEach(function (card) {
				var workflow = card.getAttribute('data-workflow-card');
				var schedule = state.schedules[workflow] || {};
				var status = state.status[workflow] || {};
				var configuration = state.demo.workflows && state.demo.workflows[workflow] ? state.demo.workflows[workflow] : {};
				var displayValues = function (values, mapping) { return (values || []).map(function (value) { return mapping[value] || value; }).join(', ') || 'Aucune sélection'; };
				card.querySelector('[data-card-schedule-state]').textContent = schedule.mode === 'manual' ? 'Manuel' : (schedule.enabled ? 'Activé' : 'Désactivé');
				card.querySelector('[data-card-schedule-summary]').textContent = status.summary || 'Exécution uniquement à la demande.';
				card.querySelector('[data-card-platforms]').textContent = displayValues(configuration.platforms, labels.platforms);
				card.querySelector('[data-card-categories]').textContent = displayValues(configuration.categories, categoryNames);
				card.querySelector('[data-card-suppliers]').textContent = displayValues(configuration.suppliers, labels.suppliers);
				card.querySelector('[data-card-scoring]').textContent = (configuration.products_per_run || 10) + ' produits · ' + (configuration.minimum_score === undefined ? 60 : configuration.minimum_score);
				card.querySelector('[data-card-model]').textContent = configuration.model || 'Aucun modèle';
				card.querySelector('[data-card-next]').textContent = status.upcoming && status.upcoming.length ? status.upcoming[0].label : 'Non planifiée';
				var history = state.history.filter(function (run) { return run.workflow === workflow; }).slice(0, 10);
				var historyList = card.querySelector('[data-card-history]');
				historyList.textContent = '';
				card.querySelector('[data-card-history-count]').textContent = String(history.length);
				if (!history.length) {
					var empty = document.createElement('li');
					empty.textContent = 'Aucune exécution enregistrée.';
					historyList.appendChild(empty);
					return;
				}
				history.forEach(function (run) {
					var item = document.createElement('li');
					var date = document.createElement('span');
					var result = document.createElement('span');
					date.textContent = formatDate(run.created_at);
					result.textContent = (run.source === 'manual' ? 'Manuelle' : 'Planifiée') + ' · ' + (run.result || run.status || '');
					item.appendChild(date);
					item.appendChild(result);
					historyList.appendChild(item);
				});
			});
		}

		function refreshDemo() {
			var demoData = window.dsaBusinessData;
			if (!demoData || !demoData.restUrl) { return Promise.resolve(); }
			return fetch(demoData.restUrl, { headers: { 'X-WP-Nonce': demoData.nonce } }).then(function (response) {
				if (!response.ok) { throw new Error('Impossible de charger les paramètres démo.'); }
				return response.json();
			}).then(function (result) { state.demo = result; renderWorkflowCards(); });
		}

		function formatDate(value) {
			if (!value) { return ''; }
			var date = new Date(value.indexOf('T') === -1 ? value.replace(' ', 'T') + 'Z' : value);
			return isNaN(date.getTime()) ? value : date.toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' });
		}

		function renderHistory() {
			var body = planner.querySelector('[data-history]');
			body.textContent = '';
			var runs = state.history.filter(function (run) { return run.workflow === state.active; }).slice(0, 10);
			planner.querySelector('[data-history-count]').textContent = String(runs.length);
			if (!runs.length) {
				var emptyRow = document.createElement('tr');
				var emptyCell = document.createElement('td');
				emptyCell.colSpan = 4;
				emptyCell.textContent = 'Aucune exécution simulée pour le moment.';
				emptyRow.appendChild(emptyCell);
				body.appendChild(emptyRow);
				return;
			}
			runs.forEach(function (run) {
				var row = document.createElement('tr');
				[run.label || workflowLabels[run.workflow] || '', run.source === 'manual' ? 'Manuelle' : 'Planifiée', formatDate(run.created_at), run.result || run.status || ''].forEach(function (value) {
					var cell = document.createElement('td');
					cell.textContent = value;
					row.appendChild(cell);
				});
				body.appendChild(row);
			});
		}

		function renderHealth() {
			var panel = planner.querySelector('[data-cron-health]');
			var message = planner.querySelector('[data-cron-health-message]');
			var badge = planner.querySelector('[data-cron-health-badge]');
			var runButton = planner.querySelector('[data-run-now]');
			planner.querySelector('[data-cron-command]').textContent = '* * * * * ' + (state.health.command || 'wp action-scheduler run --quiet');
			runButton.disabled = false;
			runButton.title = state.health.action_scheduler && state.health.n8n_webhook ? 'Cette action sera transmise au workflow n8n.' : 'Le lancement Produits exige Action Scheduler et un webhook n8n explicitement activé.';
			panel.classList.remove('is-healthy', 'is-error');
			if (!state.health.action_scheduler) {
				panel.classList.add('is-error');
				message.textContent = 'Action Scheduler est indisponible. Les lancements Produits ne peuvent pas être mis en file.';
				badge.textContent = 'Indisponible';
			} else if (!state.health.n8n_webhook) {
				panel.classList.add('is-error');
				message.textContent = 'Webhook n8n non configuré ou opt-in inactif dans wp-config.php. Les runs Produits ne seront pas transmis.';
				badge.textContent = 'Webhook à configurer';
			} else if (!state.health.wp_cron_disabled) {
				panel.classList.add('is-error');
				message.textContent = 'WP-Cron est déclenché par les visites. Configurez un cron système, puis désactivez le déclenchement trafic dans wp-config.php.';
				badge.textContent = 'À configurer';
			} else {
				message.textContent = 'WP-Cron est désactivé. Configurez et surveillez un cron système chaque minute pour traiter Action Scheduler.';
				badge.textContent = 'Cron système requis';
			}
		}

		function refresh() {
			return api('', 'GET').then(renderStatus);
		}

		function runWorkflow(workflow, button) {
			button.disabled = true;
			api('/' + workflow + '/run', 'POST', {}).then(function (result) {
				var message = result.simulated
					? 'Workflow tendances simulé; aucune collecte réelle lancée.'
					: 'Run ' + (result.run_id || '') + ' transmis à Action Scheduler.';
				if (window.dsaToast) { window.dsaToast(message); }
				window.setTimeout(refresh, 1200);
			}).catch(function (error) {
				planner.querySelector('[data-schedule-error]').textContent = error.message;
				planner.querySelector('[data-schedule-error]').hidden = false;
			}).then(function () { button.disabled = false; });
		}

		planner.querySelectorAll('[data-workflow]').forEach(function (tab) {
			tab.addEventListener('click', function () {
				state.active = tab.getAttribute('data-workflow');
				planner.querySelectorAll('[data-workflow]').forEach(function (item) {
					var selected = item === tab;
					item.classList.toggle('is-active', selected);
					item.setAttribute('aria-pressed', selected ? 'true' : 'false');
				});
				planner.querySelector('[data-workflow-title]').textContent = workflowLabels[state.active];
				fillConfig(state.schedules[state.active]);
				var current = state.status[state.active] || {};
				planner.querySelector('[data-last-run]').textContent = current.last ? formatDate(current.last.created_at) + ' · ' + (current.last.source === 'manual' ? 'Manuelle' : 'Planifiée') : 'Aucune exécution enregistrée.';
				planner.querySelector('[data-last-result]').textContent = current.last ? (current.last.result || current.last.status || '') : '';
				requestPreview();
			});
		});

		planner.querySelectorAll('[data-mode]').forEach(function (tab) { tab.addEventListener('click', function () { selectMode(tab.getAttribute('data-mode')); }); });
		planner.addEventListener('input', function (event) { if (event.target.matches('[data-field], [data-weekday], [data-monthly-type], [data-schedule-enabled]')) { requestPreview(); } });
		planner.addEventListener('change', function (event) {
			if (event.target.matches('[data-monthly-type]')) { updateMonthlyFields(); }
			if (event.target.matches('[data-schedule-enabled]')) { planner.querySelector('[data-enabled-label]').textContent = event.target.checked ? 'Activé' : 'Désactivé'; }
			if (event.target.matches('[data-field], [data-weekday], [data-monthly-type], [data-schedule-enabled]')) { requestPreview(); }
		});

		planner.querySelectorAll('[data-preset]').forEach(function (button) {
			button.addEventListener('click', function () {
				var preset = button.getAttribute('data-preset');
				var config = { enabled: planner.querySelector('[data-schedule-enabled]').checked, timezone: getField(planner, 'timezone').value };
								config.start_at = getField(planner, 'start_at').value;
								config.end_at = getField(planner, 'end_at').value;
				if (preset === 'hourly') { Object.assign(config, { mode: 'interval', amount: 1, unit: 'hours' }); }
				if (preset === 'daily') { Object.assign(config, { mode: 'daily', time: '02:00' }); }
				if (preset === 'monday') { Object.assign(config, { mode: 'weekly', weekdays: [1], time: '09:00' }); }
				if (preset === 'month-start') { Object.assign(config, { mode: 'monthly', monthly_type: 'day', day: 1, time: '03:00' }); }
				fillConfig(config);
				requestPreview();
			});
		});

		planner.querySelector('[data-save-n8n-product-input]').addEventListener('click', function (event) {
			var button = event.currentTarget;
			var status = planner.querySelector('[data-n8n-product-status]');
			button.disabled = true;
			status.textContent = '';
			api('/product-settings', 'POST', {
				query: planner.querySelector('[data-n8n-query]').value,
				marketplace: planner.querySelector('[data-n8n-marketplace]').value,
				limit: Number(planner.querySelector('[data-workflow-limit]').value)
			}).then(function (settings) {
				status.textContent = 'Recherche enregistrée (' + settings.marketplace + ', limite ' + settings.limit + ').';
			}).catch(function (error) {
				status.textContent = error.message;
			}).then(function () { button.disabled = false; });
		});

		planner.querySelector('[data-save-schedule]').addEventListener('click', function (event) {
			var button = event.currentTarget;
			button.disabled = true;
			planner.querySelector('[data-schedule-error]').hidden = true;
			api('/' + state.active, 'POST', collectConfig()).then(function (result) {
				state.schedules[state.active] = result.schedule;
				if (window.dsaToast) { window.dsaToast('Planification enregistrée dans WordPress.'); }
				return refresh().then(function () { fillConfig(state.schedules[state.active]); requestPreview(); });
			}).catch(function (error) {
				planner.querySelector('[data-schedule-error]').textContent = error.message;
				planner.querySelector('[data-schedule-error]').hidden = false;
			}).then(function () { button.disabled = false; });
		});

		planner.querySelector('[data-run-now]').addEventListener('click', function () {
			runWorkflow(state.active, this);
		});
		planner.querySelectorAll('[data-workflow-run]').forEach(function (button) {
			button.addEventListener('click', function () { runWorkflow(button.getAttribute('data-workflow-run'), button); });
		});
		document.addEventListener('dsa:workflow-config-saved', refreshDemo);

		refresh().then(function () {
			fillConfig(state.schedules[state.active]);
			requestPreview();
			return loadProductInputs();
		}).catch(function (error) {
			planner.querySelector('[data-schedule-error]').textContent = error.message;
			planner.querySelector('[data-schedule-error]').hidden = false;
		});
		refreshDemo().catch(function () {});
	}());

	(function () {
		var dashboard = document.querySelector('[data-dsa-dashboard]');
		var data = window.dsaDashboardData;
		if (!dashboard || !data || !data.restUrl) { return; }

		var periodControl = dashboard.querySelector('[data-dashboard-period]');
		var runButton = dashboard.querySelector('[data-dashboard-run]');
		var liveRegion = dashboard.querySelector('[data-dashboard-live]');
		var strings = data.messages || {};

		function request(path, method) {
			return fetch(data.restUrl + path, {
				method: method || 'GET',
				headers: { 'X-WP-Nonce': data.nonce, 'Content-Type': 'application/json' }
			}).then(function (response) {
				return response.json().then(function (payload) {
					if (!response.ok) { throw new Error(payload.message || strings.request_error || 'Une erreur est survenue.'); }
					return payload;
				});
			});
		}

		function node(tag, className, text) {
			var element = document.createElement(tag);
			if (className) { element.className = className; }
			if (text !== undefined) { element.textContent = text; }
			return element;
		}

		function formatNumber(value, decimals) {
			return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: decimals || 0 }).format(value || 0);
		}

		function formatDate(value) {
			if (!value) { return '—'; }
			var date = new Date(value.indexOf('T') === -1 ? value.replace(' ', 'T') + 'Z' : value);
			return isNaN(date.getTime()) ? value : date.toLocaleString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
		}

		function renderMetrics(metrics) {
			var labels = strings.metrics || {};
			var container = dashboard.querySelector('[data-dashboard-kpis]');
			container.textContent = '';
			Object.keys(labels).forEach(function (key) {
				var metric = metrics[key] || { value: 0, suffix: '', delta: 0 };
				var card = node('article', 'dsa-kpi');
				var label = node('span', 'dsa-kpi-label', labels[key]);
				var value = node('strong', 'dsa-kpi-value', formatNumber(metric.value, key === 'margin' || key === 'errors' ? 1 : 0) + (metric.suffix || ''));
				var deltaText = metric.delta === null ? strings.delta_new : (metric.delta > 0 ? '+' : '') + formatNumber(metric.delta, 1) + (strings.previous_period || '% vs période précédente');
				var delta = node('small', 'dsa-kpi-delta', deltaText);
				if (metric.delta !== null) {
					var favorable = key === 'errors' ? metric.delta <= 0 : metric.delta >= 0;
					delta.classList.add(favorable ? 'is-positive' : 'is-negative');
				}
				card.appendChild(label);
				card.appendChild(value);
				card.appendChild(delta);
				container.appendChild(card);
			});
		}

		function renderPipeline(items) {
			var list = dashboard.querySelector('[data-dashboard-pipeline]');
			var statusLabels = strings.pipeline_status || {};
			list.textContent = '';
			(items || []).forEach(function (step) {
				var item = node('li', 'dsa-pipeline-step is-' + step.status);
				var link = node('a', 'dsa-pipeline-link');
				link.href = data.links && data.links[step.page] ? data.links[step.page] : '#';
				link.appendChild(node('strong', 'dsa-pipeline-label', step.label));
				link.appendChild(node('span', 'dsa-pipeline-status', statusLabels[step.status] || statusLabels.inactive));
				item.appendChild(link);
				list.appendChild(item);
			});
		}

		function renderChart(series) {
			var chart = dashboard.querySelector('[data-dashboard-chart]');
			var tableBody = dashboard.querySelector('[data-dashboard-chart-table] tbody');
			chart.textContent = '';
			tableBody.textContent = '';
			if (!series || !series.length || !series.some(function (point) { return point.value > 0; })) {
				chart.appendChild(node('p', 'dsa-dashboard-empty', strings.empty_chart));
				return;
			}
			var maximum = Math.max.apply(Math, series.map(function (point) { return point.value; })) || 1;
			series.forEach(function (point) {
				var column = node('div', 'dsa-chart-column');
				var count = node('span', 'dsa-chart-count', String(point.value));
				var bar = node('span', 'dsa-chart-bar');
				bar.style.setProperty('--dsa-bar-height', Math.max(5, (point.value / maximum) * 100) + '%');
				bar.setAttribute('aria-hidden', 'true');
				column.appendChild(count);
				column.appendChild(bar);
				column.appendChild(node('span', 'dsa-chart-label', point.label));
				chart.appendChild(column);
				var row = node('tr');
				row.appendChild(node('th', '', point.label));
				row.appendChild(node('td', '', String(point.value)));
				tableBody.appendChild(row);
			});
		}

		function renderScheduled(items) {
			var list = dashboard.querySelector('[data-dashboard-scheduled]');
			list.textContent = '';
			if (!items || !items.length) {
				list.appendChild(node('li', 'dsa-dashboard-empty', strings.empty_schedule));
				return;
			}
			items.forEach(function (item) {
				var row = node('li', 'dsa-scheduled-item');
				row.appendChild(node('strong', '', item.workflow));
				row.appendChild(node('time', '', item.date));
				list.appendChild(row);
			});
		}

		function renderRuns(items) {
			var body = dashboard.querySelector('[data-dashboard-runs]');
			body.textContent = '';
			if (!items || !items.length) {
				var empty = node('tr');
				var emptyCell = node('td', 'dsa-dashboard-empty', strings.empty_runs);
				emptyCell.colSpan = 5;
				empty.appendChild(emptyCell);
				body.appendChild(empty);
				return;
			}
			items.forEach(function (run) {
				var row = node('tr');
				var start = run.debut ? new Date(run.debut.replace(' ', 'T') + 'Z') : null;
				var end = run.fin ? new Date(run.fin.replace(' ', 'T') + 'Z') : null;
				var duration = start && end && !isNaN(start.getTime()) && !isNaN(end.getTime()) ? Math.max(0, Math.round((end - start) / 60000)) + ' min' : '—';
				var statuses = strings.run_status || {};
				var status = node('span', 'dsa-run-status is-' + (run.statut === 'failed' ? 'error' : run.statut === 'completed' ? 'ok' : 'muted'), statuses[run.statut] || run.statut || '—');
				[row, run.workflow || '—', formatDate(run.debut), duration, status, (run.produits_trouves || 0) + ' / ' + (run.produits_ajoutes || 0)].forEach(function (value, index) {
					if (index === 0) { return; }
					var cell = node('td');
					if (value instanceof Node) { cell.appendChild(value); } else { cell.textContent = value; }
					row.appendChild(cell);
				});
				body.appendChild(row);
			});
		}

		function renderAlerts(items) {
			var list = dashboard.querySelector('[data-dashboard-alerts]');
			list.textContent = '';
			if (!items || !items.length) {
				list.appendChild(node('li', 'dsa-dashboard-empty', strings.empty_alerts));
				return;
			}
			items.forEach(function (alert) {
				var item = node('li', 'dsa-alert-item is-' + alert.type);
				var link = node('a', '', alert.text);
				link.href = data.links && data.links[alert.page] ? data.links[alert.page] : '#';
				item.appendChild(link);
				list.appendChild(item);
			});
		}

		function refresh() {
			dashboard.setAttribute('aria-busy', 'true');
			return request('?period=' + encodeURIComponent(periodControl.value)).then(function (payload) {
				renderMetrics(payload.metrics || {});
				renderPipeline(payload.pipeline || []);
				renderChart(payload.chart || []);
				renderScheduled(payload.scheduled || []);
				renderRuns(payload.runs || []);
				renderAlerts(payload.alerts || []);
				liveRegion.textContent = (strings.updated || 'Indicateurs actualisés pour %s.').replace('%s', periodControl.options[periodControl.selectedIndex].text);
			}).catch(function (error) {
				liveRegion.textContent = error.message;
				if (window.dsaToast) { window.dsaToast(error.message, 'error'); }
			}).then(function () { dashboard.setAttribute('aria-busy', 'false'); });
		}

		periodControl.addEventListener('change', refresh);
		runButton.addEventListener('click', function () {
			runButton.disabled = true;
			request('/run', 'POST').then(function () {
				if (window.dsaToast) { window.dsaToast(strings.run_queued || 'Exécution ajoutée à Action Scheduler.'); }
				window.setTimeout(refresh, 900);
			}).catch(function (error) {
				if (window.dsaToast) { window.dsaToast(error.message, 'error'); }
			}).then(function () { runButton.disabled = false; });
		});

		refresh();
	}());
})();