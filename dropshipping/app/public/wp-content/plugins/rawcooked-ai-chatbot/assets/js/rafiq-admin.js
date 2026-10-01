/**
 * RawCooked AI Chatbot — admin screen behavior.
 * Tabs, provider box switching, batched indexing with progress, provider test.
 */
(function () {
	'use strict';

	var cfg = window.RafiqAdmin || {};

	function qs(sel) { return document.querySelector(sel); }
	function qsa(sel) { return Array.prototype.slice.call(document.querySelectorAll(sel)); }

	/* Tabs. */
	qsa('.rafiq-tab').forEach(function (tab) {
		tab.addEventListener('click', function () {
			qsa('.rafiq-tab').forEach(function (t) { t.classList.remove('is-active'); });
			qsa('.rafiq-panel').forEach(function (p) { p.classList.remove('is-active'); });
			tab.classList.add('is-active');
			var panel = qs('.rafiq-panel[data-panel="' + tab.getAttribute('data-tab') + '"]');
			if (panel) { panel.classList.add('is-active'); }
		});
	});

	/* Show only the selected provider's box. */
	var providerSelect = qs('#rafiq-provider');
	function syncProviderBoxes() {
		if (!providerSelect) { return; }
		qsa('.rafiq-provider-box').forEach(function (box) {
			box.style.display = box.getAttribute('data-provider') === providerSelect.value ? '' : 'none';
		});
	}
	if (providerSelect) {
		providerSelect.addEventListener('change', syncProviderBoxes);
		syncProviderBoxes();
	}

	function restFetch(url, options) {
		options = options || {};
		options.headers = options.headers || {};
		options.headers['X-WP-Nonce'] = cfg.nonce;
		options.headers['Content-Type'] = 'application/json';
		options.credentials = 'same-origin';
		return window.fetch(url, options).then(function (res) { return res.json(); });
	}

	/* Index stats on load. */
	var stats = qs('#rafiq-index-stats');
	function refreshStats() {
		if (!stats) { return; }
		restFetch(cfg.statusUrl, { method: 'GET' }).then(function (data) {
			var embed = data.embedding_provider ? data.embedding_provider : 'keyword-only';
			stats.textContent = data.total_items + ' items → ' + data.chunks +
				' chunks (' + data.chunks_embedded + ' embedded, mode: ' + embed + ')';
		}).catch(function () { /* ignore */ });
	}
	refreshStats();

	/* Batched indexing loop. */
	var running = false;
	function runIndex(reset) {
		if (running) { return; }
		running = true;
		var bar = qs('#rafiq-progress-bar');
		var wrap = qs('#rafiq-progress');
		var msg = qs('#rafiq-index-message');
		wrap.hidden = false;
		bar.style.width = '2%';
		msg.textContent = cfg.i18n.indexing;

		var errors = [];
		function step(offset) {
			restFetch(cfg.indexUrl, {
				method: 'POST',
				body: JSON.stringify({ offset: offset, reset: reset ? 1 : 0, force: reset ? 1 : 0 })
			}).then(function (data) {
				if (data.errors && data.errors.length) {
					errors = errors.concat(data.errors);
				}
				var pct = data.total ? Math.round((data.offset / data.total) * 100) : 100;
				bar.style.width = Math.max(2, pct) + '%';
				msg.textContent = cfg.i18n.indexing + ' ' + data.offset + '/' + data.total;
				if (!data.done) {
					step(data.offset);
				} else {
					running = false;
					bar.style.width = '100%';
					msg.textContent = cfg.i18n.done + (errors.length ? ' ⚠ ' + errors.join(' | ') : '');
					refreshStats();
				}
			}).catch(function (err) {
				running = false;
				msg.textContent = cfg.i18n.failed + ' ' + err;
			});
		}
		step(0);
	}

	var runBtn = qs('#rafiq-index-run');
	var rebuildBtn = qs('#rafiq-index-rebuild');
	if (runBtn) { runBtn.addEventListener('click', function () { runIndex(false); }); }
	if (rebuildBtn) { rebuildBtn.addEventListener('click', function () { runIndex(true); }); }

	/* Conversations tab. */
	function escText(s) {
		var d = document.createElement('span');
		d.textContent = s == null ? '' : String(s);
		return d.innerHTML;
	}

	var convBody = qs('#rafiq-conv-body');
	var convLoaded = false;
	function loadConversations() {
		if (!convBody || convLoaded) { return; }
		convLoaded = true;
		restFetch(cfg.convUrl, { method: 'GET' }).then(function (data) {
			Object.keys(data.stats || {}).forEach(function (key) {
				var elm = qs('[data-stat="' + key + '"]');
				if (elm) {
					elm.textContent = key === 'week_no_context' ? data.stats[key] + '%' : data.stats[key];
				}
			});
			var rows = data.recent || [];
			if (!rows.length) {
				convBody.innerHTML = '<tr><td colspan="5">' + escText(cfg.i18n.no_convs) + '</td></tr>';
				return;
			}
			convBody.innerHTML = rows.map(function (r) {
				var who = r.lead_email ? (r.lead_name ? r.lead_name + ' — ' : '') + r.lead_email
					: (parseInt(r.user_id, 10) ? 'User #' + r.user_id : cfg.i18n.anonymous);
				return '<tr><td>' + escText(r.started_at) + '</td><td>' + escText(who) + '</td><td>' +
					escText(r.first_message) + '</td><td>' + escText(r.msg_count) + '</td>' +
					'<td><button type="button" class="button button-small rafiq-view-conv" data-id="' + parseInt(r.id, 10) + '">' +
					escText(cfg.i18n.view) + '</button></td></tr>';
			}).join('');
		}).catch(function () { convLoaded = false; });
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('.rafiq-view-conv') : null;
		if (!btn) { return; }
		var box = qs('#rafiq-transcript');
		restFetch(cfg.convUrl + '/' + btn.getAttribute('data-id'), { method: 'GET' }).then(function (data) {
			box.hidden = false;
			box.innerHTML = (data.messages || []).map(function (m) {
				return '<div class="rafiq-transcript__msg rafiq-transcript__msg--' + (m.r === 'u' ? 'u' : 'a') + '">' +
					escText(m.c) + (m.x ? ' <em class="rafiq-transcript__flag">[no site content matched]</em>' : '') + '</div>';
			}).join('');
			box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
		});
	});

	var exportBtn = qs('#rafiq-export-leads');
	if (exportBtn) {
		exportBtn.addEventListener('click', function () {
			restFetch(cfg.leadsUrl, { method: 'GET' }).then(function (leads) {
				var csv = 'name,email,first_message,date\n' + (leads || []).map(function (l) {
					return [l.lead_name, l.lead_email, l.first_message, l.started_at].map(function (v) {
						return '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"';
					}).join(',');
				}).join('\n');
				var a = document.createElement('a');
				a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
				a.download = 'rafiq-leads.csv';
				document.body.appendChild(a);
				a.click();
				document.body.removeChild(a);
			});
		});
	}

	var deleteBtn = qs('#rafiq-delete-convs');
	if (deleteBtn) {
		deleteBtn.addEventListener('click', function () {
			if (!window.confirm(cfg.i18n.confirm_del)) { return; }
			restFetch(cfg.convUrl, { method: 'DELETE' }).then(function () {
				convLoaded = false;
				loadConversations();
			});
		});
	}

	// Lazy-load conversations when the tab is opened.
	qsa('.rafiq-tab').forEach(function (tab) {
		tab.addEventListener('click', function () {
			if (tab.getAttribute('data-tab') === 'conversations') {
				loadConversations();
			}
		});
	});

	/* Provider test. */
	var testBtn = qs('#rafiq-test-provider');
	var testResult = qs('#rafiq-test-result');
	if (testBtn) {
		testBtn.addEventListener('click', function () {
			testResult.textContent = cfg.i18n.testing;
			testResult.className = '';
			restFetch(cfg.testUrl, { method: 'POST', body: '{}' }).then(function (data) {
				if (data.ok) {
					testResult.textContent = '✓ ' + cfg.i18n.test_ok;
					testResult.className = 'rafiq-ok';
				} else {
					testResult.textContent = '✗ ' + (data.error || 'Error');
					testResult.className = 'rafiq-err';
				}
			}).catch(function (err) {
				testResult.textContent = '✗ ' + err;
				testResult.className = 'rafiq-err';
			});
		});
	}
})();
