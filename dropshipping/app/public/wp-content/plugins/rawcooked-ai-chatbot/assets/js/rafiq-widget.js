/**
 * RawCooked AI Chatbot — frontend widget.
 *
 * Three mounts from the same engine:
 *  - bubble  : floating launcher in a corner, panel pops out of it
 *  - sidebar : slim tab on the screen edge, full-height panel slides in
 *  - inline  : rendered inside [rafiq_chatbot] shortcode (dedicated page)
 *
 * No dependencies. All rendering is escaped; only whitelisted markdown
 * (links, bold, italics, lists) is re-inserted as safe DOM.
 */
(function () {
	'use strict';

	if (typeof window.RafiqConfig === 'undefined') {
		return;
	}
	var cfg = window.RafiqConfig;
	var STORAGE_KEY = 'rafiq_chat_history_v1';
	var SESSION_KEY = 'rafiq_session_v1';
	var LEAD_KEY = 'rafiq_lead_v1';
	var AUTO_KEY = 'rafiq_auto_opened_v1';
	var TEASER_KEY = 'rafiq_teaser_v1';

	/* ------------------------------------------------------------------ *
	 * Utilities
	 * ------------------------------------------------------------------ */

	function el(tag, className, text) {
		var node = document.createElement(tag);
		if (className) {
			node.className = className;
		}
		if (text) {
			node.textContent = text;
		}
		return node;
	}

	function escapeHtml(str) {
		return str
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function isSafeUrl(url) {
		return /^(https?:\/\/|\/)/i.test(url);
	}

	/**
	 * Minimal markdown: escape everything first, then re-allow a small,
	 * safe subset. Output is HTML string for innerHTML.
	 */
	function renderMarkdown(text) {
		var html = escapeHtml(text);

		// Links: [label](url) — only http(s) or site-relative.
		html = html.replace(/\[([^\]]{1,120})\]\(([^)\s]{1,500})\)/g, function (m, label, url) {
			if (!isSafeUrl(url)) {
				return label;
			}
			return '<a href="' + url + '" target="_blank" rel="noopener noreferrer">' + label + '</a>';
		});

		html = html.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
		html = html.replace(/(^|[\s>])\*([^*\n]+)\*/g, '$1<em>$2</em>');

		// Bullet lists.
		var lines = html.split('\n');
		var out = [];
		var inList = false;
		for (var i = 0; i < lines.length; i++) {
			var line = lines[i];
			var m = line.match(/^\s*[-•]\s+(.*)$/);
			if (m) {
				if (!inList) {
					out.push('<ul>');
					inList = true;
				}
				out.push('<li>' + m[1] + '</li>');
			} else {
				if (inList) {
					out.push('</ul>');
					inList = false;
				}
				out.push(line);
			}
		}
		if (inList) {
			out.push('</ul>');
		}
		html = out.join('\n');
		// Newlines to <br> outside of list markup.
		html = html.replace(/\n(?!<\/?(ul|li)>)/g, '<br>');
		html = html.replace(/<br>(<\/?(ul|li)>)/g, '$1');
		return html;
	}

	function loadHistory() {
		try {
			var raw = window.sessionStorage.getItem(STORAGE_KEY);
			var data = raw ? JSON.parse(raw) : [];
			return Array.isArray(data) ? data : [];
		} catch (e) {
			return [];
		}
	}

	function saveHistory(history) {
		try {
			window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(history.slice(-40)));
		} catch (e) { /* storage unavailable: fine */ }
	}

	function sessionId() {
		var id = null;
		try {
			id = window.sessionStorage.getItem(SESSION_KEY);
		} catch (e) { /* ignore */ }
		if (!id) {
			id = (window.crypto && window.crypto.randomUUID)
				? window.crypto.randomUUID()
				: 'rs-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
			try {
				window.sessionStorage.setItem(SESSION_KEY, id);
			} catch (e) { /* ignore */ }
		}
		return id;
	}

	function getLead() {
		try {
			var raw = window.sessionStorage.getItem(LEAD_KEY);
			return raw ? JSON.parse(raw) : null;
		} catch (e) {
			return null;
		}
	}

	function saveLead(lead) {
		try {
			window.sessionStorage.setItem(LEAD_KEY, JSON.stringify(lead));
		} catch (e) { /* ignore */ }
	}

	/* ------------------------------------------------------------------ *
	 * Chat engine (shared by every mount)
	 * ------------------------------------------------------------------ */

	function ChatUI(container, variant, opts) {
		this.container = container;
		this.variant = variant; // 'panel' | 'inline'
		this.opts = opts || {};
		this.history = loadHistory();
		this.busy = false;
		this.build();
	}

	ChatUI.prototype.build = function () {
		var self = this;
		var root = el('div', 'rafiq-chat rafiq-chat--' + this.variant);

		// Header.
		var header = el('div', 'rafiq-chat__header');
		var avatar = el('span', 'rafiq-chat__avatar', cfg.avatar || '💬');
		if (cfg.avatarImage) {
			avatar.textContent = '';
			var avatarImg = el('img', 'rafiq-chat__avatar-img');
			avatarImg.src = cfg.avatarImage;
			avatarImg.alt = '';
			avatar.appendChild(avatarImg);
		}
		this.avatarEl = avatar;
		var title = el('div', 'rafiq-chat__title');
		title.appendChild(el('strong', null, cfg.botName || 'Assistant'));
		title.appendChild(el('span', 'rafiq-chat__status', 'online'));
		header.appendChild(avatar);
		header.appendChild(title);
		this.header = header;
		root.appendChild(header);

		// Messages.
		this.messagesEl = el('div', 'rafiq-chat__messages');
		root.appendChild(this.messagesEl);

		// Composer.
		var composer = el('form', 'rafiq-chat__composer');
		this.input = el('textarea', 'rafiq-chat__input');
		this.input.rows = 1;
		this.input.placeholder = cfg.i18n.placeholder;
		this.input.setAttribute('aria-label', cfg.i18n.placeholder);
		var send = el('button', 'rafiq-chat__send');
		send.type = 'submit';
		send.setAttribute('aria-label', cfg.i18n.send);
		send.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M3.4 20.4c-.66.29-1.39-.2-1.39-.91L2 14.88c0-.5.37-.93.87-1L17 12 2.87 10.12c-.5-.06-.87-.49-.87-.99l.01-4.62c0-.71.73-1.2 1.39-.91l17.45 7.48a1 1 0 0 1 0 1.84L3.4 20.4z"/></svg>';
		composer.appendChild(this.input);
		composer.appendChild(send);
		root.appendChild(composer);

		composer.addEventListener('submit', function (e) {
			e.preventDefault();
			self.send();
		});
		this.input.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				self.send();
			}
		});
		this.input.addEventListener('input', function () {
			self.input.style.height = 'auto';
			self.input.style.height = Math.min(self.input.scrollHeight, 120) + 'px';
		});

		this.container.appendChild(root);
		this.root = root;

		// Replay saved history, or show the welcome message.
		if (this.history.length) {
			for (var i = 0; i < this.history.length; i++) {
				this.addBubble(this.history[i].role, this.history[i].content, [], false);
			}
		} else if (cfg.welcome) {
			this.addBubble('assistant', cfg.welcome, [], false);
		}

		// Suggested questions for a fresh conversation.
		if (!this.history.length && cfg.quickReplies && cfg.quickReplies.length) {
			this.renderQuickReplies();
		}

		// Lead capture gate for fresh anonymous conversations.
		if (cfg.leadCapture && cfg.leadCapture !== 'off' && !getLead() && !this.history.length) {
			this.renderLeadForm();
		}

		this.scrollDown();
	};

	ChatUI.prototype.renderQuickReplies = function () {
		var self = this;
		var box = el('div', 'rafiq-quick');
		cfg.quickReplies.forEach(function (question) {
			var chip = el('button', 'rafiq-quick__chip', question);
			chip.type = 'button';
			chip.addEventListener('click', function () {
				self.input.value = question;
				self.send();
			});
			box.appendChild(chip);
		});
		this.quickEl = box;
		this.messagesEl.appendChild(box);
	};

	ChatUI.prototype.removeQuickReplies = function () {
		if (this.quickEl && this.quickEl.parentNode) {
			this.quickEl.parentNode.removeChild(this.quickEl);
		}
		this.quickEl = null;
	};

	ChatUI.prototype.renderLeadForm = function () {
		var self = this;
		var overlay = el('div', 'rafiq-lead');
		var card = el('div', 'rafiq-lead__card');
		card.appendChild(el('p', 'rafiq-lead__title', cfg.i18n.leadTitle));

		var name = el('input', 'rafiq-lead__input');
		name.type = 'text';
		name.placeholder = cfg.i18n.leadName;
		var email = el('input', 'rafiq-lead__input');
		email.type = 'email';
		email.placeholder = cfg.i18n.leadEmail;
		card.appendChild(name);
		card.appendChild(email);

		var start = el('button', 'rafiq-lead__start', cfg.i18n.leadStart);
		start.type = 'button';
		start.addEventListener('click', function () {
			var value = email.value.trim();
			if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
				email.classList.add('is-invalid');
				email.focus();
				return;
			}
			saveLead({ name: name.value.trim(), email: value });
			self.leadPending = true;
			overlay.parentNode.removeChild(overlay);
			self.input.focus();
		});
		card.appendChild(start);

		if (cfg.leadCapture === 'optional') {
			var skip = el('button', 'rafiq-lead__skip', cfg.i18n.leadSkip);
			skip.type = 'button';
			skip.addEventListener('click', function () {
				saveLead({ name: '', email: '' });
				overlay.parentNode.removeChild(overlay);
				self.input.focus();
			});
			card.appendChild(skip);
		}

		overlay.appendChild(card);
		this.root.appendChild(overlay);
	};

	ChatUI.prototype.addBubble = function (role, text, sources, animate) {
		var wrap = el('div', 'rafiq-msg rafiq-msg--' + role + (animate ? ' rafiq-msg--in' : ''));
		var bubble = el('div', 'rafiq-msg__bubble');
		if (role === 'assistant') {
			bubble.innerHTML = renderMarkdown(text);
		} else {
			bubble.textContent = text;
		}
		wrap.appendChild(bubble);

		if (sources && sources.length) {
			var box = el('div', 'rafiq-msg__sources');
			box.appendChild(el('span', 'rafiq-msg__sources-label', cfg.i18n.sources));
			for (var i = 0; i < sources.length; i++) {
				if (!sources[i] || !isSafeUrl(String(sources[i].url || ''))) {
					continue;
				}
				var chip = el('a', 'rafiq-chip', sources[i].title || sources[i].url);
				chip.href = sources[i].url;
				chip.target = '_blank';
				chip.rel = 'noopener noreferrer';
				box.appendChild(chip);
			}
			wrap.appendChild(box);
		}

		this.messagesEl.appendChild(wrap);
		this.scrollDown();
		return wrap;
	};

	ChatUI.prototype.showTyping = function () {
		this.typingEl = el('div', 'rafiq-msg rafiq-msg--assistant rafiq-msg--in');
		var bubble = el('div', 'rafiq-msg__bubble rafiq-typing');
		bubble.innerHTML = '<span></span><span></span><span></span>';
		this.typingEl.appendChild(bubble);
		this.messagesEl.appendChild(this.typingEl);
		this.scrollDown();
	};

	ChatUI.prototype.hideTyping = function () {
		if (this.typingEl && this.typingEl.parentNode) {
			this.typingEl.parentNode.removeChild(this.typingEl);
		}
		this.typingEl = null;
	};

	ChatUI.prototype.scrollDown = function () {
		this.messagesEl.scrollTop = this.messagesEl.scrollHeight;
	};

	/**
	 * Injects an assistant message locally (no API call). Used by add-ons
	 * for proactive engagement ("Need a hand with this product?").
	 */
	ChatUI.prototype.say = function (text) {
		if (!text) {
			return;
		}
		this.removeQuickReplies();
		this.addBubble('assistant', String(text), [], true);
		this.history.push({ role: 'assistant', content: String(text) });
		saveHistory(this.history);
	};

	/** Little life sign: the avatar hops when a reply lands. */
	ChatUI.prototype.celebrate = function () {
		var avatar = this.avatarEl;
		if (!avatar) {
			return;
		}
		avatar.classList.add('is-celebrating');
		window.setTimeout(function () {
			avatar.classList.remove('is-celebrating');
		}, 900);
	};

	ChatUI.prototype.send = function () {
		var self = this;
		var text = this.input.value.trim();
		if (!text || this.busy) {
			return;
		}
		this.busy = true;
		this.input.value = '';
		this.input.style.height = 'auto';
		this.removeQuickReplies();

		this.addBubble('user', text, [], true);
		var payloadHistory = this.history.slice(-20);
		this.history.push({ role: 'user', content: text });
		saveHistory(this.history);
		this.showTyping();

		var payload = { message: text, history: payloadHistory, session: sessionId() };
		var lead = getLead();
		if (lead && lead.email) {
			payload.lead_name = lead.name || '';
			payload.lead_email = lead.email;
		}

		window.fetch(cfg.restUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce
			},
			credentials: 'same-origin',
			body: JSON.stringify(payload)
		})
			.then(function (res) {
				return res.json().then(function (data) {
					return { ok: res.ok, data: data };
				});
			})
			.then(function (result) {
				self.hideTyping();
				if (result.ok && result.data && typeof result.data.reply === 'string') {
					self.addBubble('assistant', result.data.reply, result.data.sources || [], true);
					self.history.push({ role: 'assistant', content: result.data.reply });
					saveHistory(self.history);
					self.celebrate();
					if (self.opts.onReply) {
						self.opts.onReply();
					}
					// Public extension point (used by add-ons).
					document.dispatchEvent(new CustomEvent('rafiq:reply', {
						detail: {
							session: sessionId(),
							message: text,
							reply: result.data.reply,
							sources: result.data.sources || [],
							meta: result.data.meta || {}
						}
					}));
				} else {
					var message = (result.data && result.data.message) ? result.data.message : cfg.i18n.error;
					self.addBubble('assistant', message, [], true);
				}
			})
			.catch(function () {
				self.hideTyping();
				self.addBubble('assistant', cfg.i18n.error, [], true);
			})
			.then(function () {
				self.busy = false;
				self.input.focus();
			});
	};

	/* ------------------------------------------------------------------ *
	 * Mounts
	 * ------------------------------------------------------------------ */

	function mountFloating(rootEl) {
		var mode = cfg.mode; // bubble | sidebar
		var side = cfg.position === 'left' ? 'left' : 'right';
		rootEl.className = 'rafiq-root rafiq-root--' + mode + ' rafiq-root--' + side
			+ ' rafiq-theme-' + (cfg.theme || 'classic')
			+ ' rafiq-anim-' + (cfg.animation || 'pop')
			+ ' rafiq-mlayout-' + (cfg.mobileLayout || 'full');

		var panel = el('div', 'rafiq-panel');
		panel.setAttribute('role', 'dialog');
		panel.setAttribute('aria-label', cfg.botName || 'Chat');

		// Unread badge: lights up when a reply lands while the panel is closed.
		var badge = el('span', 'rafiq-badge');
		badge.hidden = true;
		var unread = 0;

		var chat = new ChatUI(panel, 'panel', {
			onReply: function () {
				if (!rootEl.classList.contains('is-open')) {
					unread++;
					badge.textContent = unread > 9 ? '9+' : String(unread);
					badge.hidden = false;
				}
			}
		});

		// Close button inside the header.
		var close = el('button', 'rafiq-panel__close');
		close.type = 'button';
		close.setAttribute('aria-label', cfg.i18n.close);
		close.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M19 6.4L17.6 5 12 10.6 6.4 5 5 6.4 10.6 12 5 17.6 6.4 19 12 13.4 17.6 19 19 17.6 13.4 12z"/></svg>';
		chat.header.appendChild(close);

		var launcher = el('button', 'rafiq-launcher');
		launcher.type = 'button';
		launcher.setAttribute('aria-label', cfg.i18n.open);
		launcher.setAttribute('aria-expanded', 'false');
		if (mode === 'sidebar') {
			launcher.innerHTML = '<span class="rafiq-launcher__tab">' + escapeHtml(cfg.botName || 'Chat') + '</span>';
		} else {
			launcher.innerHTML =
				'<span class="rafiq-launcher__icon rafiq-launcher__icon--chat"><svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"><path fill="currentColor" d="M12 3C6.5 3 2 6.9 2 11.7c0 2.5 1.2 4.7 3.2 6.3-.1 1.2-.6 2.4-1.5 3.5 1.9-.1 3.6-.8 4.9-1.8 1.1.3 2.2.5 3.4.5 5.5 0 10-3.9 10-8.5S17.5 3 12 3z"/></svg></span>' +
				'<span class="rafiq-launcher__icon rafiq-launcher__icon--close"><svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true"><path fill="currentColor" d="M19 6.4L17.6 5 12 10.6 6.4 5 5 6.4 10.6 12 5 17.6 6.4 19 12 13.4 17.6 19 19 17.6 13.4 12z"/></svg></span>';
		}

		function setOpen(open) {
			rootEl.classList.toggle('is-open', open);
			launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
			if (open) {
				unread = 0;
				badge.hidden = true;
				window.setTimeout(function () {
					chat.input.focus();
					chat.scrollDown();
				}, 250);
			}
		}
		launcher.addEventListener('click', function () {
			setOpen(!rootEl.classList.contains('is-open'));
		});
		close.addEventListener('click', function () {
			setOpen(false);
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && rootEl.classList.contains('is-open')) {
				setOpen(false);
			}
		});

		launcher.appendChild(badge);
		rootEl.appendChild(panel);
		rootEl.appendChild(launcher);

		// Teaser: a small dismissible speech bubble next to the launcher.
		var teaserEl = null;
		function showTeaser(text) {
			if (teaserEl || rootEl.classList.contains('is-open') || mode !== 'bubble') {
				return false;
			}
			try {
				if (window.sessionStorage.getItem(TEASER_KEY)) {
					return false;
				}
				window.sessionStorage.setItem(TEASER_KEY, '1');
			} catch (e) { /* ignore */ }

			teaserEl = el('div', 'rafiq-teaser');
			var bubbleBtn = el('button', 'rafiq-teaser__text', text);
			bubbleBtn.type = 'button';
			bubbleBtn.addEventListener('click', function () {
				hideTeaser();
				setOpen(true);
			});
			var closeBtn = el('button', 'rafiq-teaser__close');
			closeBtn.type = 'button';
			closeBtn.setAttribute('aria-label', cfg.i18n.close);
			closeBtn.textContent = '×';
			closeBtn.addEventListener('click', hideTeaser);
			teaserEl.appendChild(bubbleBtn);
			teaserEl.appendChild(closeBtn);
			rootEl.appendChild(teaserEl);
			return true;
		}
		function hideTeaser() {
			if (teaserEl && teaserEl.parentNode) {
				teaserEl.parentNode.removeChild(teaserEl);
			}
			teaserEl = null;
		}

		if (cfg.teaser && cfg.teaser.text && cfg.teaser.delay > 0) {
			window.setTimeout(function () {
				showTeaser(cfg.teaser.text);
			}, cfg.teaser.delay * 1000);
		}

		window.RafiqWidget._floating = {
			setOpen: setOpen,
			chat: chat,
			showTeaser: showTeaser,
			hideTeaser: hideTeaser,
			isOpen: function () {
				return rootEl.classList.contains('is-open');
			}
		};

		// Proactive open, once per browser session.
		var autoOpened = false;
		try {
			autoOpened = !!window.sessionStorage.getItem(AUTO_KEY);
		} catch (e) { /* ignore */ }
		if (cfg.autoOpen > 0 && !autoOpened) {
			window.setTimeout(function () {
				if (!rootEl.classList.contains('is-open')) {
					setOpen(true);
				}
				try {
					window.sessionStorage.setItem(AUTO_KEY, '1');
				} catch (e) { /* ignore */ }
			}, cfg.autoOpen * 1000);
		}
	}

	function init() {
		var inline = document.getElementById('rafiq-inline');
		if (inline) {
			inline.className += ' rafiq-theme-' + (cfg.theme || 'classic');
			window.RafiqWidget.instances.push(new ChatUI(inline, 'inline'));
		}
		var rootEl = document.getElementById('rafiq-root');
		if (rootEl && cfg.mode !== 'none' && !inline) {
			mountFloating(rootEl);
		}
		document.dispatchEvent(new CustomEvent('rafiq:mounted', {
			detail: { session: sessionId() }
		}));
	}

	/**
	 * Public API for add-ons: read the session id, listen to widget events.
	 * Events dispatched on `document`: "rafiq:reply", "rafiq:mounted".
	 */
	window.RafiqWidget = {
		version: '1.3.1',
		getSession: sessionId,
		instances: [],
		_floating: null,

		/**
		 * Opens the floating chat; with a message, the bot "speaks" it
		 * locally (no API call). No-op in inline/shortcode mode.
		 */
		open: function (message) {
			var f = window.RafiqWidget._floating;
			if (!f) {
				return false;
			}
			f.hideTeaser();
			var wasOpen = f.isOpen();
			f.setOpen(true);
			if (message && !wasOpen) {
				f.chat.say(message);
			}
			return true;
		},

		/** Shows the teaser speech bubble now (once per visit). */
		teaser: function (text) {
			var f = window.RafiqWidget._floating;
			return f ? f.showTeaser(String(text || '')) : false;
		}
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
