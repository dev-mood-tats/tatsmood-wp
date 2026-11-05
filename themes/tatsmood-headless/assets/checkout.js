/**
 * Packlink PRO: el selector de punto de recogida es una ventana flotante (fija, por encima
 * de todo) y su aviso «There are no drop-off locations…» sale encima de todo el checkout.
 * Aquí los dos van dentro del checkout, justo debajo de las opciones de envío, en este orden:
 * opciones de envío → aviso → selector. checkout.css les quita lo de ventana y caja azul.
 * Packlink los sigue abriendo y cerrando él mismo (style.display); no tocamos su código,
 * solo dónde están y, al cambiar de envío, los cerramos (ver más abajo).
 *
 * Cada elemento tiene un sitio fijo y solo se mueve si no está ya en él: si no, se
 * empujarían el uno al otro sin parar (y la página se quedaría colgada).
 */
(function () {
	function rates() {
		return document.querySelector('.wc-block-components-shipping-rates-control');
	}

	function putAfter(anchor, el) {
		if (anchor.nextElementSibling !== el) anchor.after(el);
	}

	function place() {
		// El checkout vuelve a pintar las opciones de envío a menudo: si cambian, se recolocan.
		var r = rates();
		if (!r) return;
		var notice = document.getElementById('no-drop-off-locations-message');
		var modal = document.getElementById('pl-picker-modal');

		if (notice) putAfter(r, notice);
		if (!modal) return;
		if (!modal.classList.contains('tm-inline')) modal.classList.add('tm-inline');
		if (modal.style.display !== 'block') {
			delete modal.dataset.tmOpen;
			return;
		}
		putAfter(notice || r, modal);
		// Solo al abrirse se acerca a la vista; al recolocarlo después, no (daría saltos).
		if (!modal.dataset.tmOpen) {
			modal.dataset.tmOpen = '1';
			modal.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
		}
	}

	/*
	 * Al cambiar de envío (p. ej. de «pickup» a «delivery»), Packlink oculta su botón pero deja
	 * abierta la lista de puntos y el aviso: antes no se notaba porque la lista tapaba toda la
	 * página. Si el envío elegido ya no lleva el botón «Select Drop-Off Location», se cierran.
	 * Se comprueba justo después del clic (cuando Packlink ya ha puesto o quitado su botón).
	 */
	function closeIfNotDropOff() {
		var r = rates();
		var checked = r && r.querySelector('input[type="radio"]:checked');
		if (!checked) return;
		var option = checked.closest('label') || checked.parentElement;
		var button = document.getElementById('packlink-drop-off-picker');
		var isDropOff = !!(button && option && option.contains(button) && button.style.display !== 'none');
		if (isDropOff) return;
		var modal = document.getElementById('pl-picker-modal');
		var notice = document.getElementById('no-drop-off-locations-message');
		if (modal && modal.style.display === 'block') modal.style.display = 'none';
		if (notice && notice.style.display !== 'none') notice.style.display = 'none';
	}

	document.addEventListener('change', function (e) {
		var target = e.target;
		if (!target || target.type !== 'radio') return;
		if (!target.closest('.wc-block-components-shipping-rates-control')) return;
		setTimeout(closeIfNotDropOff, 0);
	});

	new MutationObserver(place).observe(document.documentElement, {
		subtree: true,
		childList: true,
		attributes: true,
		attributeFilter: ['style'],
	});
	document.addEventListener('DOMContentLoaded', place);
})();
