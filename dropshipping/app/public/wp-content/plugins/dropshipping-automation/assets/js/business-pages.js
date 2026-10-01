(function () {
	'use strict';

	var data = window.dsaBusinessData;
	if (!data || !data.restUrl) { return; }
	var toast = function () {
		if (window.dsaToast) { window.dsaToast('Action simulée (mode démo)'); }
	};
	var request = function (payload) {
		return fetch(data.restUrl, {
			method: payload ? 'POST' : 'GET',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': data.nonce, 'Content-Type': 'application/json' },
			body: payload ? JSON.stringify(payload) : undefined
		}).then(function (response) {
			return response.json().then(function (body) {
				if (!response.ok) { throw new Error(body.message || 'Une erreur est survenue.'); }
				return body;
			});
		});
	};
	var labelForStatus = function (status) {
		return { shortlist: 'Shortlist', pending: 'À valider', published: 'Publié en démo', rejected: 'Rejeté' }[status] || status;
	};
	var addDetailAction = function (container, label, action, id) {
		var button = document.createElement('button');
		button.type = 'button';
		button.className = 'dsa-button';
		button.textContent = label;
		button.setAttribute('data-detail-action', action);
		button.setAttribute('data-id', id);
		container.appendChild(button);
	};

	(function () {
		var root = document.querySelector('[data-business-page="products"]');
		if (!root) { return; }
		var rows = Array.prototype.slice.call(root.querySelectorAll('[data-product-row]'));
		var activeStatus = 'shortlist';
		var panel = root.querySelector('[data-product-panel]');
		var panelContent = root.querySelector('[data-product-detail-content]');
		var selected = function () { return rows.filter(function (row) { return row.querySelector('[data-product-select]').checked; }); };
		var updateBulk = function () {
			var selection = selected();
			var bulk = root.querySelector('[data-product-bulk]');
			bulk.hidden = !selection.length;
			root.querySelector('[data-selected-count]').textContent = selection.length + ' sélectionné(s)';
		};
		var filterRows = function () {
			var search = root.querySelector('[data-product-search]').value.toLocaleLowerCase();
			var category = root.querySelector('[data-product-category]').value;
			var shown = 0;
			rows.forEach(function (row) {
				var isVisible = row.getAttribute('data-status') === activeStatus && (!search || row.getAttribute('data-name').toLocaleLowerCase().indexOf(search) !== -1) && (!category || row.getAttribute('data-category') === category);
				row.hidden = !isVisible;
				if (isVisible) { shown += 1; }
			});
			root.querySelector('[data-product-empty]').hidden = shown > 0;
			root.querySelector('[data-product-count]').textContent = shown + ' produit(s)';
			root.querySelector('[data-select-visible]').checked = false;
			updateBulk();
		};
		var setStatus = function (ids, action) {
			request({ area: 'product', action: action, ids: ids }).then(function () {
				var newStatus = { approve: 'published', reject: 'rejected', rescore: 'shortlist' }[action];
				ids.forEach(function (id) {
					var row = rows.find(function (candidate) { return candidate.getAttribute('data-id') === String(id); });
					if (row) {
						row.setAttribute('data-status', newStatus);
						row.children[8].querySelector('.dsa-demo-status').className = 'dsa-demo-status is-' + newStatus;
						row.children[8].querySelector('.dsa-demo-status').textContent = labelForStatus(newStatus);
						row.querySelector('[data-product-select]').checked = false;
					}
				});
				filterRows();
				toast();
			}).catch(function (error) { if (window.dsaToast) { window.dsaToast(error.message, 'error'); } });
		};
		root.querySelectorAll('[data-product-tab]').forEach(function (tab) {
			tab.addEventListener('click', function () {
				activeStatus = tab.getAttribute('data-product-tab');
				root.querySelectorAll('[data-product-tab]').forEach(function (item) { item.setAttribute('aria-selected', item === tab ? 'true' : 'false'); });
				filterRows();
			});
		});
		root.querySelector('[data-product-search]').addEventListener('input', filterRows);
		root.querySelector('[data-product-category]').addEventListener('change', filterRows);
		root.querySelector('[data-product-sort]').addEventListener('change', function (event) {
			var key = event.target.value;
			rows.sort(function (left, right) {
				if (key === 'titre') { return left.getAttribute('data-name').localeCompare(right.getAttribute('data-name'), 'fr'); }
				var attribute = key === 'marge_pct' ? 'data-margin' : 'data-score';
				return Number(right.getAttribute(attribute)) - Number(left.getAttribute(attribute));
			}).forEach(function (row) { row.parentNode.appendChild(row); });
			filterRows();
		});
		root.querySelectorAll('[data-product-sort-key]').forEach(function (button) {
			button.addEventListener('click', function () {
				var key = button.getAttribute('data-product-sort-key') === 'titre' ? 'titre' : 'score_global';
				root.querySelector('[data-product-sort]').value = key === 'titre' ? 'titre' : 'score_global';
				root.querySelector('[data-product-sort]').dispatchEvent(new Event('change'));
			});
		});
		root.querySelector('[data-select-visible]').addEventListener('change', function (event) {
			rows.forEach(function (row) { if (!row.hidden) { row.querySelector('[data-product-select]').checked = event.target.checked; } });
			updateBulk();
		});
		root.addEventListener('change', function (event) { if (event.target.matches('[data-product-select]')) { updateBulk(); } });
		root.querySelectorAll('[data-bulk-action]').forEach(function (button) {
			button.addEventListener('click', function () { setStatus(selected().map(function (row) { return Number(row.getAttribute('data-id')); }), button.getAttribute('data-bulk-action')); });
		});
		root.addEventListener('click', function (event) {
			var trigger = event.target.closest('[data-product-detail]');
			if (trigger) {
				var detail = JSON.parse(trigger.getAttribute('data-product-detail'));
				root.querySelector('[data-detail-name]').textContent = detail.name;
				panelContent.textContent = '';
				var facts = document.createElement('dl');
				facts.className = 'dsa-detail-metrics';
				[
					['Catégorie', detail.category], ['Fournisseur associé', detail.source], ['Prix fournisseur', detail.purchase.toFixed(2) + ' €'],
					['Prix de vente cible', detail.sale.toFixed(2) + ' €'], ['Marge estimée', detail.margin.toFixed(1) + '%'],
					['Livraison moyenne', detail.delivery + ' jours'], ['Note moyenne', detail.rating.toFixed(1) + ' / 5 (' + detail.reviews_count + ' avis)']
				].forEach(function (item) { var wrapper = document.createElement('div'); var term = document.createElement('dt'); var value = document.createElement('dd'); term.textContent = item[0]; value.textContent = item[1]; wrapper.appendChild(term); wrapper.appendChild(value); facts.appendChild(wrapper); });
				panelContent.appendChild(facts);
				var supplierList = document.createElement('section'); supplierList.className = 'dsa-detail-block';
				var supplierHeading = document.createElement('h3'); supplierHeading.textContent = 'Prix par fournisseur'; supplierList.appendChild(supplierHeading);
				var supplierText = document.createElement('p'); supplierText.textContent = detail.source + ' · ' + detail.purchase.toFixed(2) + ' €'; supplierList.appendChild(supplierText);
				var unavailable = document.createElement('p'); unavailable.className = 'dsa-muted-copy'; unavailable.textContent = 'Autres prix non appariés dans les données de démonstration.'; supplierList.appendChild(unavailable); panelContent.appendChild(supplierList);
				var reviewBlock = document.createElement('section'); reviewBlock.className = 'dsa-detail-block'; var reviewHeading = document.createElement('h3'); reviewHeading.textContent = 'Avis récents'; reviewBlock.appendChild(reviewHeading);
				(detail.reviews || []).forEach(function (review) { var paragraph = document.createElement('p'); paragraph.textContent = '★ ' + review.note + ' · ' + review.commentaire; reviewBlock.appendChild(paragraph); }); panelContent.appendChild(reviewBlock);
				var historyBlock = document.createElement('section'); historyBlock.className = 'dsa-detail-block'; var historyHeading = document.createElement('h3'); historyHeading.textContent = 'Historique de prix · 30 jours'; historyBlock.appendChild(historyHeading); var history = document.createElement('div'); history.className = 'dsa-price-history';
				(detail.prices || []).slice(-12).forEach(function (snapshot) { var bar = document.createElement('span'); bar.title = snapshot.date + ' · ' + Number(snapshot.prix).toFixed(2) + ' €'; bar.style.height = Math.max(12, Math.min(100, Number(snapshot.prix) / Number(detail.sale) * 60)) + '%'; history.appendChild(bar); });
				historyBlock.appendChild(history); panelContent.appendChild(historyBlock);
				var actions = document.createElement('div'); actions.className = 'dsa-detail-actions';
				addDetailAction(actions, 'Valider', 'approve', detail.id); addDetailAction(actions, 'Rejeter', 'reject', detail.id); addDetailAction(actions, 'Renvoyer au scoring', 'rescore', detail.id); panelContent.appendChild(actions);
				panel.setAttribute('aria-hidden', 'false'); panel.inert = false; panel.classList.add('is-open');
			}
			if (event.target.closest('[data-panel-close]')) { panel.classList.remove('is-open'); panel.setAttribute('aria-hidden', 'true'); panel.inert = true; }
			var actionButton = event.target.closest('[data-detail-action]');
			if (actionButton) { setStatus([Number(actionButton.getAttribute('data-id'))], actionButton.getAttribute('data-detail-action')); panel.classList.remove('is-open'); panel.setAttribute('aria-hidden', 'true'); panel.inert = true; }
		});
		filterRows();
	}());

	(function () {
		var root = document.querySelector('[data-workflow-planner]');
		if (!root) { return; }
		var parameterPanel = root.querySelector('[data-workflow-parameters]');
		var state = {};
		var checkboxes = function (selector, values) {
			parameterPanel.querySelectorAll(selector).forEach(function (input) { input.checked = (values || []).map(String).indexOf(input.value) !== -1; });
		};
		var loadConfiguration = function (workflow) {
			var config = state.workflows && state.workflows[workflow] ? state.workflows[workflow] : {};
			checkboxes('[data-workflow-platform]', config.platforms);
			checkboxes('[data-workflow-category]', config.categories);
			checkboxes('[data-workflow-supplier]', config.suppliers);
			parameterPanel.querySelector('[data-workflow-limit]').value = config.products_per_run || 10;
			parameterPanel.querySelector('[data-workflow-score]').value = config.minimum_score === undefined ? 60 : config.minimum_score;
			parameterPanel.querySelector('[data-workflow-model]').value = config.model || '';
		};
		var collectConfiguration = function () {
			return {
				area: 'workflow', action: 'save', workflow: root.querySelector('[data-workflow].is-active').getAttribute('data-workflow'),
				platforms: Array.prototype.map.call(parameterPanel.querySelectorAll('[data-workflow-platform]:checked'), function (input) { return input.value; }),
				categories: Array.prototype.map.call(parameterPanel.querySelectorAll('[data-workflow-category]:checked'), function (input) { return Number(input.value); }),
				suppliers: Array.prototype.map.call(parameterPanel.querySelectorAll('[data-workflow-supplier]:checked'), function (input) { return input.value; }),
				products_per_run: Number(parameterPanel.querySelector('[data-workflow-limit]').value),
				minimum_score: Number(parameterPanel.querySelector('[data-workflow-score]').value),
				model: parameterPanel.querySelector('[data-workflow-model]').value
			};
		};
		request().then(function (result) { state = result; loadConfiguration('products'); }).catch(function () {});
		root.querySelectorAll('[data-workflow]').forEach(function (tab) {
			tab.addEventListener('click', function () { window.setTimeout(function () { loadConfiguration(tab.getAttribute('data-workflow')); }, 0); });
		});
		root.querySelector('[data-save-workflow]').addEventListener('click', function (event) {
			var button = event.currentTarget;
			button.disabled = true;
			request(collectConfiguration()).then(function (result) { state = result.state; toast(); root.querySelector('[data-workflow-error]').hidden = true; window.dispatchEvent(new Event('dsa:workflow-config-saved')); }).catch(function (error) { var message = root.querySelector('[data-workflow-error]'); message.textContent = error.message; message.hidden = false; }).then(function () { button.disabled = false; });
		});
	}());

	(function () {
		var root = document.querySelector('[data-business-page="suppliers"]');
		if (!root) { return; }
		root.querySelectorAll('[data-supplier-action]').forEach(function (button) {
			button.addEventListener('click', function () {
				var connected = button.getAttribute('data-connected') === '1';
				request({ area: 'supplier', supplier: button.getAttribute('data-supplier'), action: connected ? 'disconnect' : 'connect' }).then(function () {
					button.setAttribute('data-connected', connected ? '0' : '1');
					button.textContent = connected ? 'Simuler la connexion' : 'Simuler la déconnexion';
					var status = button.parentNode.querySelector('[data-supplier-status]');
					status.className = 'dsa-demo-status is-' + (connected ? 'disconnected' : 'connected');
					status.textContent = connected ? 'Non connecté' : 'Connecté en démo'; toast();
				}).catch(function (error) { if (window.dsaToast) { window.dsaToast(error.message, 'error'); } });
			});
		});
	}());

	(function () {
		var root = document.querySelector('[data-business-page="orders"]');
		if (!root) { return; }
		var panel = root.querySelector('[data-order-panel]');
		var content = root.querySelector('[data-order-detail-content]');
		root.addEventListener('click', function (event) {
			var trigger = event.target.closest('[data-order-detail]');
			if (trigger) {
				var order = JSON.parse(trigger.getAttribute('data-order-detail'));
				root.querySelector('[data-order-title]').textContent = 'Commande #' + (Number(order.id) + 7000);
				content.textContent = '';
				var tracking = document.createElement('p'); tracking.className = 'dsa-tracking-number'; tracking.textContent = order.number; content.appendChild(tracking);
				var line = document.createElement('p'); line.textContent = order.product + ' · ' + order.supplier; content.appendChild(line);
				var timeline = document.createElement('ol'); timeline.className = 'dsa-order-timeline';
				[['Reçue', true], ['Transmise au fournisseur', ['processing', 'shipped', 'delivered'].indexOf(order.status) !== -1], ['Expédiée', ['shipped', 'delivered'].indexOf(order.status) !== -1], ['Livrée', order.status === 'delivered']].forEach(function (step) { var item = document.createElement('li'); item.className = step[1] ? 'is-complete' : ''; item.textContent = step[0]; timeline.appendChild(item); }); content.appendChild(timeline);
				if (order.status !== 'delivered') { var action = document.createElement('button'); action.type = 'button'; action.className = 'dsa-button dsa-button-primary'; action.textContent = order.status === 'pending' ? 'Simuler la transmission fournisseur' : (order.status === 'shipped' ? 'Simuler la livraison' : 'Simuler l’expédition'); action.setAttribute('data-order-action', order.status === 'pending' ? 'transmit' : (order.status === 'shipped' ? 'deliver' : 'ship')); action.setAttribute('data-order-id', order.id); content.appendChild(action); }
				panel.setAttribute('aria-hidden', 'false'); panel.inert = false; panel.classList.add('is-open');
			}
			if (event.target.closest('[data-panel-close]')) { panel.classList.remove('is-open'); panel.setAttribute('aria-hidden', 'true'); panel.inert = true; }
			var action = event.target.closest('[data-order-action]');
			if (action) { request({ area: 'order', action: action.getAttribute('data-order-action'), id: Number(action.getAttribute('data-order-id')) }).then(function () {
				var status = { transmit: 'processing', ship: 'shipped', deliver: 'delivered' }[action.getAttribute('data-order-action')];
				var orderId = action.getAttribute('data-order-id');
				var tableRow = Array.prototype.find.call(root.querySelectorAll('[data-order-detail]'), function (button) { return JSON.parse(button.getAttribute('data-order-detail')).id === Number(orderId); }).closest('tr');
				var statusBadge = tableRow.querySelector('.dsa-demo-status');
				statusBadge.className = 'dsa-demo-status is-' + status;
				statusBadge.textContent = { processing: 'Transmise au fournisseur', shipped: 'Expédiée', delivered: 'Livrée' }[status];
				action.remove(); toast();
			}).catch(function (error) { if (window.dsaToast) { window.dsaToast(error.message, 'error'); } }); }
		});
	}());

	(function () {
		var root = document.querySelector('[data-business-page="support"]');
		if (!root) { return; }
		root.querySelectorAll('[data-support-action]').forEach(function (button) {
			button.addEventListener('click', function () {
				request({ area: 'support', action: button.getAttribute('data-support-action'), id: Number(button.getAttribute('data-support-id')) }).then(function () {
					button.disabled = true; button.textContent = 'Action simulée'; button.parentNode.querySelector('.dsa-demo-status').textContent = 'Action simulée'; toast();
				}).catch(function (error) { if (window.dsaToast) { window.dsaToast(error.message, 'error'); } });
			});
		});
	}());
}());
