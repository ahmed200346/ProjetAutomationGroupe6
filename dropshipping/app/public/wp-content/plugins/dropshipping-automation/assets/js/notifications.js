( function () {
	'use strict';

	const config = window.dsaNotificationsData;
	if ( ! config || ! config.apiUrl ) {
		return;
	}

	const request = async ( route, options = {} ) => {
		const response = await fetch( config.apiUrl + route, {
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
			...options,
		} );
		const payload = await response.json();
		if ( ! response.ok ) {
			throw new Error( payload.message || config.messages.error );
		}
		return payload;
	};

	const make = ( tag, text, className ) => {
		const element = document.createElement( tag );
		if ( text ) element.textContent = text;
		if ( className ) element.className = className;
		return element;
	};

	const bell = document.querySelector( '[data-dsa-notifications]' );
	if ( bell ) {
		const bellButton = bell.querySelector( '[data-dsa-bell]' );
		const panel = bell.querySelector( '[data-dsa-notification-panel]' );
		const list = bell.querySelector( '[data-dsa-notification-list]' );
		const badge = bell.querySelector( '[data-dsa-unread]' );
		const setUnread = ( count ) => {
			badge.hidden = ! count;
			badge.textContent = count > 99 ? '99+' : String( count );
		};
		const loadNotifications = async () => {
			try {
				const data = await request( 'notifications?limit=8' );
				setUnread( data.unread );
				list.replaceChildren();
				if ( ! data.items.length ) {
					list.append( make( 'li', config.messages.empty, 'is-empty' ) );
					return;
				}
				data.items.forEach( ( item ) => {
					const entry = make( 'li', '', item.read_at ? '' : 'is-unread' );
					const button = make( 'button' );
					button.type = 'button';
					button.append( make( 'strong', item.title ), make( 'span', item.message ) );
					button.addEventListener( 'click', async () => {
						if ( ! item.read_at ) {
							await request( 'notifications/' + encodeURIComponent( item.id ) + '/read', { method: 'POST', body: '{}' } );
							loadNotifications();
						}
					} );
					entry.append( button );
					list.append( entry );
				} );
			} catch ( error ) {
				list.replaceChildren( make( 'li', error.message, 'is-empty' ) );
			}
		};
		bellButton.addEventListener( 'click', () => {
			const open = 'true' !== bellButton.getAttribute( 'aria-expanded' );
			bellButton.setAttribute( 'aria-expanded', String( open ) );
			panel.hidden = ! open;
			if ( open ) loadNotifications();
		} );
		bell.querySelector( '[data-dsa-read-all]' ).addEventListener( 'click', async () => {
			await request( 'notifications/read-all', { method: 'POST', body: '{}' } );
			loadNotifications();
		} );
		loadNotifications();
	}

	const logsPage = document.querySelector( '[data-dsa-logs-page]' );
	if ( ! logsPage ) return;

	let page = 1;
	let pages = 1;
	const filters = logsPage.querySelector( '[data-log-filters]' );
	const rows = logsPage.querySelector( '[data-log-rows]' );
	const pageLabel = logsPage.querySelector( '[data-log-page]' );
	const detailDialog = document.querySelector( '[data-log-detail-dialog]' );
	const detailBody = detailDialog.querySelector( '[data-detail-body]' );
	const detailTitle = detailDialog.querySelector( '[data-detail-title]' );

	const formatDuration = ( stages ) => {
		const seconds = stages.reduce( ( total, stage ) => total + Number( stage.duration_seconds || 0 ), 0 );
		return seconds >= 60 ? Math.round( seconds / 60 ) + ' min' : seconds + ' s';
	};

	const loadLogs = async () => {
		const params = new URLSearchParams( new FormData( filters ) );
		params.set( 'page', String( page ) );
		try {
			const data = await request( 'logs?' + params.toString() );
			pages = data.pages;
			rows.replaceChildren();
			data.items.forEach( ( item ) => {
				const row = document.createElement( 'tr' );
				const cells = [
					'#' + item.id,
					item.workflow + ' · ' + item.module,
					item.started_at,
					formatDuration( item.stages ),
					item.status === 'failed' ? config.messages.failed : config.messages.completed,
					item.counts.found + ' / ' + item.counts.created,
				];
				cells.forEach( ( value ) => row.append( make( 'td', value ) ) );
				const actionCell = make( 'td' );
				const detailButton = make( 'button', config.messages.details, 'dsa-button dsa-button-quiet' );
				detailButton.type = 'button';
				detailButton.dataset.logDetail = String( item.id );
				detailButton.addEventListener( 'click', () => showRunDetail( item.id ) );
				actionCell.append( detailButton );
				row.append( actionCell );
				rows.append( row );
			} );
			if ( ! data.items.length ) {
				const emptyRow = document.createElement( 'tr' );
				const emptyCell = make( 'td', config.messages.noRuns );
				emptyCell.colSpan = 7;
				emptyRow.append( emptyCell );
				rows.append( emptyRow );
			}
			page = data.page;
			pageLabel.textContent = page + ' / ' + pages;
			logsPage.querySelector( '[data-log-total]' ).textContent = data.total + ' ' + config.messages.runs;
			logsPage.querySelector( '[data-log-previous]' ).disabled = page <= 1;
			logsPage.querySelector( '[data-log-next]' ).disabled = page >= pages;
		} catch ( error ) {
			rows.replaceChildren();
			const row = document.createElement( 'tr' );
			const cell = make( 'td', error.message );
			cell.colSpan = 7;
			row.append( cell );
			rows.append( row );
		}
	};

	const showRunDetail = async ( id ) => {
		try {
			const run = await request( 'logs/' + encodeURIComponent( id ) );
			detailTitle.textContent = run.workflow + ' · #' + run.id;
			detailBody.replaceChildren();
			const list = make( 'ol', '', 'dsa-stage-list' );
			run.stages.forEach( ( stage ) => {
				const item = make( 'li' );
				item.append( make( 'strong', stage.stage ), make( 'span', stage.duration_seconds + ' s' ), make( 'small', stage.status ) );
				if ( stage.error_summary ) item.append( make( 'span', stage.error_summary, 'dsa-stage-error' ) );
				list.append( item );
			} );
			detailBody.append( list );
			if ( detailDialog.showModal ) detailDialog.showModal();
		} catch ( error ) {
			window.alert( error.message );
		}
	};

	filters.addEventListener( 'submit', ( event ) => { event.preventDefault(); page = 1; loadLogs(); } );
	logsPage.querySelector( '[data-log-previous]' ).addEventListener( 'click', () => { page = Math.max( 1, page - 1 ); loadLogs(); } );
	logsPage.querySelector( '[data-log-next]' ).addEventListener( 'click', () => { page = Math.min( pages, page + 1 ); loadLogs(); } );
	logsPage.querySelector( '[data-log-export]' ).addEventListener( 'click', async () => {
		const params = new URLSearchParams( new FormData( filters ) );
		const result = await request( 'logs/export?' + params.toString() );
		const link = document.createElement( 'a' );
		link.href = URL.createObjectURL( new Blob( [ result.csv ], { type: 'text/csv;charset=utf-8' } ) );
		link.download = result.filename;
		link.click();
		URL.revokeObjectURL( link.href );
	} );

	const activateTab = ( tab, focus ) => {
		logsPage.querySelectorAll( '[data-log-tab]' ).forEach( ( candidate ) => {
			const selected = candidate === tab;
			candidate.setAttribute( 'aria-selected', String( selected ) );
			candidate.tabIndex = selected ? 0 : -1;
		} );
		logsPage.querySelectorAll( '[data-log-panel]' ).forEach( ( panel ) => { panel.hidden = panel.dataset.logPanel !== tab.dataset.logTab; } );
		if ( focus ) tab.focus();
	};
	const tabs = Array.from( logsPage.querySelectorAll( '[data-log-tab]' ) );
	tabs.forEach( ( tab, index ) => {
		tab.addEventListener( 'click', () => activateTab( tab, false ) );
		tab.addEventListener( 'keydown', ( event ) => {
			if ( ! [ 'ArrowLeft', 'ArrowRight', 'Home', 'End' ].includes( event.key ) ) return;
			event.preventDefault();
			const nextIndex = event.key === 'Home' ? 0 : ( event.key === 'End' ? tabs.length - 1 : ( index + ( event.key === 'ArrowRight' ? 1 : -1 ) + tabs.length ) % tabs.length );
			activateTab( tabs[ nextIndex ], true );
		} );
	} );

	const settingsForm = logsPage.querySelector( '[data-notification-settings]' );
	const settingsStatus = settingsForm.querySelector( '[data-settings-status]' );
	const fillSettings = async () => {
		const settings = await request( 'notifications/settings' );
		settingsForm.elements.email_enabled.checked = settings.email_enabled;
		settingsForm.elements.recipient_mode.value = settings.recipient_mode;
		settingsForm.elements.email.value = settings.email;
		settingsForm.elements.frequency.value = settings.frequency;
		settingsForm.elements.retention_days.value = settings.retention_days;
		settingsForm.querySelectorAll( '[name="events[]"]' ).forEach( ( input ) => { input.checked = settings.events.includes( input.value ); } );
		[ 'slack', 'telegram' ].forEach( ( provider ) => {
			settingsForm.elements[ 'webhook_' + provider ].value = '';
			settingsForm.querySelector( '[data-webhook-state="' + provider + '"]' ).textContent = settings.webhooks_configured[ provider ] ? config.messages.webhookConfigured : config.messages.webhookEmpty;
		} );
	};
	settingsForm.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		const form = new FormData( settingsForm );
		const payload = {
			email_enabled: form.has( 'email_enabled' ),
			recipient_mode: form.get( 'recipient_mode' ),
			email: form.get( 'email' ),
			webhooks: { slack: form.get( 'webhook_slack' ), telegram: form.get( 'webhook_telegram' ) },
			clear_webhooks: { slack: form.has( 'clear_slack' ), telegram: form.has( 'clear_telegram' ) },
			events: form.getAll( 'events[]' ),
			frequency: form.get( 'frequency' ),
			retention_days: form.get( 'retention_days' ),
		};
		try {
			await request( 'notifications/settings', { method: 'POST', body: JSON.stringify( payload ) } );
			settingsStatus.textContent = config.messages.saved;
			fillSettings();
		} catch ( error ) { settingsStatus.textContent = error.message; }
	} );

	settingsForm.querySelector( '[data-test-email]' ).addEventListener( 'click', async () => {
		try {
			await request( 'notifications/test-email', { method: 'POST', body: '{}' } );
			settingsStatus.textContent = config.messages.testSent;
		} catch ( error ) { settingsStatus.textContent = error.message; }
	} );
	settingsForm.querySelector( '[data-purge-notifications]' ).addEventListener( 'click', async () => {
		try {
			const result = await request( 'logs/purge', { method: 'POST', body: '{}' } );
			settingsStatus.textContent = result.message + ' (' + result.removed + ')';
		} catch ( error ) { settingsStatus.textContent = error.message; }
	} );

	const digestDialog = document.querySelector( '[data-digest-dialog]' );
	logsPage.querySelector( '[data-open-digest]' ).addEventListener( 'click', async () => {
		try {
			const result = await request( 'notifications/preview' );
			digestDialog.querySelector( '[data-digest-frame]' ).srcdoc = result.html;
			if ( digestDialog.showModal ) digestDialog.showModal();
		} catch ( error ) { settingsStatus.textContent = error.message; }
	} );

	document.querySelectorAll( '[data-close-dialog]' ).forEach( ( button ) => button.addEventListener( 'click', () => button.closest( 'dialog' ).close() ) );
	loadLogs();
	fillSettings().catch( ( error ) => { settingsStatus.textContent = error.message; } );
} )();