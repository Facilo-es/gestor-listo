( function () {
	var l10n = window.gestlistoText || {};
	var box = document.querySelector( '[data-gl-pack]' );
	if ( box ) {
		var d = box.dataset;
		var text = box.querySelector( '[data-gl-text]' );
		var bar = box.querySelector( '.barra i' );
		var post = function ( body ) {
			body._wpnonce = d.nonce;
			return fetch( d.ajax, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams( body ).toString() } ).then( function ( r ) {
				return r.json();
			} );
		};
		var step = function ( job ) {
			post( { action: 'gestlisto_pack_step', job: job } ).then( function ( r ) {
				var s = r.data;
				text.textContent = l10n.preparing.replace( '%1$s', s.done ).replace( '%2$s', s.total );
				bar.style.width = ( s.total ? ( s.done * 100 ) / s.total : 100 ) + '%';
				if ( s.finished ) {
					text.textContent = l10n.ready;
					var ready = box.querySelector( '[data-gl-ready]' );
					ready.hidden = false;
					ready.querySelector( '[data-gl-zip]' ).href = s.url;
					ready.querySelector( '[name=id]' ).value = s.id;
				} else {
					step( job );
				}
			} ).catch( function () {
				text.textContent = l10n.failed;
			} );
		};
		box.querySelector( '[data-gl-go]' ).addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var checked = box.querySelector( '[name=scope]:checked' );
			var delta = box.querySelector( '[name=delta]' );
			box.querySelector( '[data-gl-work]' ).hidden = false;
			e.target.hidden = true;
			post( { action: 'gestlisto_pack_start', year: d.year, q: d.q, scope: checked ? checked.value : 'quarter', delta: delta && delta.checked ? 1 : '' } ).then( function ( r ) {
				step( r.data.job );
			} );
		} );
	}
	document.querySelectorAll( '[data-gl-copy]' ).forEach( function ( b ) {
		b.addEventListener( 'click', function () {
			var text = document.querySelector( b.dataset.glCopy ).textContent;
			var done = function () {
				b.textContent = l10n.copied;
			};
			if ( navigator.clipboard ) {
				navigator.clipboard.writeText( text ).then( done );
			} else {
				var t = document.createElement( 'textarea' );
				t.value = text;
				document.body.appendChild( t );
				t.select();
				document.execCommand( 'copy' );
				t.remove();
				done();
			}
		} );
	} );
}() );
