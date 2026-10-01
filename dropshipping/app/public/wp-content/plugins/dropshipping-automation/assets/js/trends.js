(function () {
	'use strict';

	var root = document.querySelector('[data-trends]');
	var config = window.dsaTrendsData;
	if (!root || !config || !config.restUrl || !window.Chart) { return; }

	var messages = config.messages || {};
	var state = { payload: null, charts: {}, sparklines: [], sort: { key: 'mentions', direction: 'desc' } };
	var colorNames = ['--dsa-chart-1', '--dsa-chart-2', '--dsa-chart-3', '--dsa-chart-4', '--dsa-chart-5', '--dsa-chart-6', '--dsa-chart-7', '--dsa-chart-8'];
	var money = new Intl.NumberFormat(document.documentElement.lang || 'fr-FR', { style: 'currency', currency: config.currency || 'EUR', maximumFractionDigits: 2 });

	function colors() {
		var styles = window.getComputedStyle(document.documentElement);
		return colorNames.map(function (name) { return styles.getPropertyValue(name).trim(); });
	}

	function apiUrl() {
		var params = new URLSearchParams();
		var period = root.querySelector('[data-filter="period"]').value;
		var platform = root.querySelector('[data-filter="platform"]').value;
		var source = root.querySelector('[data-filter="source"]').value;
		var categories = Array.from(root.querySelector('[data-filter="categories"]').selectedOptions).map(function (option) { return option.value; });
		params.set('period', period);
		params.set('platform', platform);
		params.set('source', source);
		params.set('categories', categories.join(','));
		return config.restUrl + '?' + params.toString();
	}

	function destroyCharts() {
		Object.keys(state.charts).forEach(function (key) { state.charts[key].destroy(); });
		state.charts = {};
		state.sparklines.forEach(function (chart) { chart.destroy(); });
		state.sparklines = [];
	}

	function chartState(name, mode, text) {
		var card = root.querySelector('[data-chart-card="' + name + '"]');
		if (!card) { return; }
		var status = card.querySelector('[data-chart-state]');
		var canvas = card.querySelector('[data-chart-canvas]');
		status.textContent = text;
		status.hidden = mode === 'ready';
		canvas.hidden = mode !== 'ready';
		card.setAttribute('data-state', mode);
	}

	function setLoading() {
		root.setAttribute('aria-busy', 'true');
		['price', 'categories', 'platforms'].forEach(function (name) { chartState(name, 'loading', messages.loading || 'Chargement…'); });
		['products', 'demanded'].forEach(function (name) {
			var body = root.querySelector('[data-table-body="' + name + '"]');
			var row = document.createElement('tr');
			var cell = document.createElement('td');
			cell.colSpan = name === 'products' ? 8 : 4;
			cell.dataset.tableState = '';
			cell.textContent = messages.loading || 'Chargement…';
			row.appendChild(cell);
			body.replaceChildren(row);
		});
	}

	function setError() {
		root.setAttribute('aria-busy', 'false');
		['price', 'categories', 'platforms'].forEach(function (name) { chartState(name, 'error', messages.error || 'Erreur de chargement.'); });
		['products', 'demanded'].forEach(function (name) {
			var cell = root.querySelector('[data-table-body="' + name + '"] [data-table-state]');
			if (cell) { cell.textContent = messages.error || 'Erreur de chargement.'; }
		});
	}

	function fillFilters(filters) {
		var categorySelect = root.querySelector('[data-filter="categories"]');
		var sourceSelect = root.querySelector('[data-filter="source"]');
		var selectedCategories = Array.from(categorySelect.selectedOptions).map(function (option) { return option.value; });
		var selectedSource = sourceSelect.value;
		categorySelect.replaceChildren();
		(filters.categories || []).forEach(function (category) {
			var option = document.createElement('option');
			option.value = String(category.id);
			option.textContent = category.name;
			option.selected = selectedCategories.indexOf(option.value) !== -1;
			categorySelect.appendChild(option);
		});
		sourceSelect.replaceChildren(new Option(messages.allSources || '', ''));
		(filters.sources || []).forEach(function (source) {
			var option = new Option(source, source);
			option.selected = source === selectedSource;
			sourceSelect.appendChild(option);
		});
	}

	function chartOptions(showLegend, horizontal) {
		var styles = window.getComputedStyle(document.documentElement);
		var textColor = styles.getPropertyValue('--dsa-muted').trim();
		var gridColor = styles.getPropertyValue('--dsa-line').trim();
		return {
			responsive: true,
			maintainAspectRatio: false,
			indexAxis: horizontal ? 'y' : 'x',
			interaction: { mode: 'index', intersect: false },
			plugins: {
				legend: { display: showLegend, position: 'bottom', labels: { color: textColor, usePointStyle: true, boxWidth: 8, padding: 16 } },
				tooltip: { enabled: true, backgroundColor: styles.getPropertyValue('--dsa-surface').trim(), titleColor: styles.getPropertyValue('--dsa-ink').trim(), bodyColor: styles.getPropertyValue('--dsa-ink').trim(), borderColor: gridColor, borderWidth: 1, padding: 10 }
			},
			scales: horizontal ? {
				x: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor, precision: 0 } },
				y: { grid: { display: false }, ticks: { color: textColor } }
			} : {
				x: { grid: { display: false }, ticks: { color: textColor, maxTicksLimit: 8 } },
				y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor } }
			}
		};
	}

	function drawChart(name, type, labels, datasets, options) {
		var card = root.querySelector('[data-chart-card="' + name + '"]');
		var hasValues = datasets.some(function (dataset) { return dataset.data.some(function (value) { return value !== null && value > 0; }); });
		if (!labels.length || !hasValues) {
			chartState(name, 'empty', messages.empty || 'Aucune donnée.');
			return;
		}
		try {
			chartState(name, 'ready', '');
			state.charts[name] = new Chart(card.querySelector('canvas'), { type: type, data: { labels: labels, datasets: datasets }, options: options });
		} catch (error) {
			chartState(name, 'error', messages.error || 'Erreur de chargement.');
		}
	}

	function drawPrice(payload) {
		var palette = colors();
		var datasets = (payload.price.series || []).map(function (series, index) {
			var color = palette[index % palette.length];
			return { label: series.label, data: series.data, borderColor: color, backgroundColor: color, pointRadius: 2, pointHoverRadius: 5, borderWidth: 2, spanGaps: true, tension: 0.28 };
		});
		drawChart('price', 'line', payload.price.labels || [], datasets, chartOptions(true, false));
		var changes = root.querySelector('[data-price-changes]');
		changes.replaceChildren();
		(payload.price.changes || []).forEach(function (item, index) {
			var badge = document.createElement('span');
			badge.className = 'dsa-price-change' + (item.change > 0 ? ' is-up' : (item.change < 0 ? ' is-down' : ''));
			badge.style.setProperty('--dsa-change-color', colors()[index % colors().length]);
			var name = document.createElement('span');
			name.textContent = item.category;
			var change = document.createElement('strong');
			change.textContent = item.change === null ? '—' : ((item.change > 0 ? '+' : '') + item.change + '%');
			badge.append(name, change);
			changes.appendChild(badge);
		});
	}

	function createCell(row, value, className) {
		var cell = document.createElement('td');
		if (className) { cell.className = className; }
		cell.textContent = value;
		row.appendChild(cell);
		return cell;
	}

	function emptyTable(name, columns) {
		var body = root.querySelector('[data-table-body="' + name + '"]');
		var row = document.createElement('tr');
		var cell = document.createElement('td');
		cell.colSpan = columns;
		cell.dataset.tableState = '';
		cell.textContent = messages.empty || 'Aucune donnée.';
		row.appendChild(cell);
		body.replaceChildren(row);
	}

	function renderProducts(products) {
		var body = root.querySelector('[data-table-body="products"]');
		var sorted = products.slice().sort(function (left, right) {
			var leftValue = left[state.sort.key];
			var rightValue = right[state.sort.key];
			var result = typeof leftValue === 'string' ? leftValue.localeCompare(rightValue, document.documentElement.lang || 'fr') : leftValue - rightValue;
			return state.sort.direction === 'asc' ? result : -result;
		});
		if (!sorted.length) { emptyTable('products', 8); return; }
		body.replaceChildren();
		sorted.forEach(function (product) {
			var row = document.createElement('tr');
			createCell(row, product.product, 'dsa-trend-product');
			createCell(row, product.category);
			createCell(row, Number(product.mentions).toLocaleString());
			createCell(row, (product.growth > 0 ? '+' : '') + product.growth + '%', product.growth >= 0 ? 'dsa-trend-positive' : 'dsa-trend-negative');
			createCell(row, money.format(product.average_price));
			createCell(row, product.margin + '%');
			createCell(row, String(product.score));
			var sparkCell = document.createElement('td');
			var canvas = document.createElement('canvas');
			canvas.className = 'dsa-sparkline';
			canvas.width = 76;
			canvas.height = 25;
			canvas.setAttribute('aria-label', (messages.sparklineLabel || '%s').replace('%s', product.product));
			sparkCell.appendChild(canvas);
			row.appendChild(sparkCell);
			body.appendChild(row);
			var color = colors()[0];
			state.sparklines.push(new Chart(canvas, { type: 'line', data: { labels: [1, 2, 3, 4, 5, 6, 7], datasets: [{ data: product.sparkline, borderColor: color, borderWidth: 1.5, pointRadius: 0, tension: 0.35 }] }, options: { responsive: false, animation: false, plugins: { legend: { display: false }, tooltip: { enabled: false } }, scales: { x: { display: false }, y: { display: false, beginAtZero: true } } } }));
		});
	}

	function renderDemanded(rows) {
		var body = root.querySelector('[data-table-body="demanded"]');
		if (!rows.length) { emptyTable('demanded', 4); return; }
		body.replaceChildren();
		rows.forEach(function (item) {
			var row = document.createElement('tr');
			createCell(row, item.category);
			createCell(row, item.product, 'dsa-trend-product');
			createCell(row, Number(item.signals).toLocaleString());
			createCell(row, Number(item.mentions).toLocaleString());
			body.appendChild(row);
		});
	}

	function renderRun(run) {
		var date = root.querySelector('[data-last-run-date]');
		var label = root.querySelector('[data-last-run-label]');
		var status = root.querySelector('[data-last-run-status]');
		if (!run.date) {
			date.textContent = messages.noRun || 'Aucune exécution enregistrée.';
			label.textContent = '';
			status.textContent = messages.unknown || 'Inconnu';
			return;
		}
		var parsed = new Date(run.date.replace(' ', 'T') + 'Z');
		date.textContent = Number.isNaN(parsed.getTime()) ? run.date : new Intl.DateTimeFormat(document.documentElement.lang || 'fr-FR', { dateStyle: 'medium', timeStyle: 'short' }).format(parsed);
		label.textContent = run.label || '';
		status.textContent = messages.status && messages.status[run.status] ? messages.status[run.status] : (run.status || messages.unknown || 'Inconnu');
		status.className = 'dsa-run-status' + (run.status === 'completed' ? ' is-ok' : (run.status === 'failed' ? ' is-error' : ''));
	}

	function render(payload) {
		state.payload = payload;
		fillFilters(payload.filters || {});
		drawPrice(payload);
		var palette = colors();
		var categoryLabels = payload.categories.labels || [];
		drawChart('categories', 'bar', categoryLabels, [{ label: messages.signalLabel || '', data: payload.categories.values || [], backgroundColor: categoryLabels.map(function (_label, index) { return palette[index % palette.length]; }), borderRadius: 3, maxBarThickness: 28 }], chartOptions(false, true));
		drawChart('platforms', 'doughnut', payload.platforms.labels || [], [{ data: payload.platforms.values || [], backgroundColor: palette.slice(0, 3), borderColor: window.getComputedStyle(document.documentElement).getPropertyValue('--dsa-surface').trim(), borderWidth: 3, hoverOffset: 5 }], Object.assign(chartOptions(true, false), { cutout: '68%', scales: {} }));
		renderProducts(payload.products || []);
		renderDemanded(payload.demanded || []);
		renderRun(payload.last_run || {});
		root.setAttribute('aria-busy', 'false');
	}

	function load() {
		destroyCharts();
		setLoading();
		fetch(apiUrl(), { headers: { 'X-WP-Nonce': config.nonce, 'Accept': 'application/json' }, credentials: 'same-origin' })
			.then(function (response) {
				return response.json().then(function (payload) {
					if (!response.ok) { throw new Error(payload.message || 'Request failed'); }
					return payload;
				});
			})
			.then(render)
			.catch(setError);
	}

	function csvCell(value) {
		return '"' + String(value === null || value === undefined ? '' : value).replace(/"/g, '""') + '"';
	}

	function exportCsv(table) {
		if (!state.payload) { return; }
		var rows;
		var filename;
		if (table === 'products') {
			rows = (state.payload.products || []).map(function (item) { return [item.product, item.category, item.mentions, item.growth, item.average_price, item.margin, item.score, item.sparkline.join(' ')]; });
			filename = 'dsa-produits-tendance.csv';
		} else {
			rows = (state.payload.demanded || []).map(function (item) { return [item.category, item.product, item.signals, item.mentions]; });
			filename = 'dsa-produits-demandes-30-jours.csv';
		}
		var headers = messages.csvHeaders && messages.csvHeaders[table] ? messages.csvHeaders[table] : [];
		var csv = '\ufeff' + [headers].concat(rows).map(function (row) { return row.map(csvCell).join(';'); }).join('\r\n');
		var url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
		var anchor = document.createElement('a');
		anchor.href = url;
		anchor.download = filename;
		anchor.click();
		URL.revokeObjectURL(url);
	}

	root.querySelector('[data-apply-filters]').addEventListener('click', load);
	root.querySelectorAll('[data-export]').forEach(function (button) { button.addEventListener('click', function () { exportCsv(button.dataset.export); }); });
	root.querySelectorAll('[data-sort]').forEach(function (button) {
		button.addEventListener('click', function () {
			var key = button.dataset.sort;
			state.sort.direction = state.sort.key === key && state.sort.direction === 'desc' ? 'asc' : 'desc';
			state.sort.key = key;
			if (state.payload) { state.sparklines.forEach(function (chart) { chart.destroy(); }); state.sparklines = []; renderProducts(state.payload.products || []); }
		});
	});
	load();
}());