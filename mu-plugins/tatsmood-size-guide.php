<?php
/**
 * Plugin Name: Tatsmood – tabla de tallas
 * Description: Panel «Tabla de tallas» en la ficha de producto: tallas en columnas, medidas en filas, sin tocar código. La web la enseña en «Size guide».
 *
 * Se guarda como JSON en el campo «tatsmood_size_table» del producto (sale en la API de
 * WooCommerce y Next.js pinta la tabla). Si un producto no tiene tabla aquí, la web sigue
 * usando la que hubiera escrita a mano en el texto de «Size guide». Si la tiene, la escrita
 * a mano se ignora (el texto que haya alrededor de ella se sigue enseñando).
 */

defined( 'ABSPATH' ) || exit;

const TATSMOOD_SIZE_TABLE_META = 'tatsmood_size_table';

/** Tabla guardada (o null). */
function tatsmood_size_table( int $product_id ): ?array {
	$raw  = (string) get_post_meta( $product_id, TATSMOOD_SIZE_TABLE_META, true );
	$data = $raw ? json_decode( $raw, true ) : null;
	return is_array( $data ) && ! empty( $data['sizes'] ) && ! empty( $data['rows'] ) ? $data : null;
}

add_action(
	'add_meta_boxes_product',
	static function () {
		add_meta_box( 'tatsmood-size-table', 'Tabla de tallas', 'tatsmood_size_table_box', 'product', 'normal', 'high' );
	}
);

function tatsmood_size_table_box( WP_Post $post ): void {
	wp_nonce_field( 'tatsmood_size_table', 'tatsmood_size_table_nonce' );
	$table = tatsmood_size_table( $post->ID );
	// Para «Importar»: la tabla que hubiera escrita a mano en el texto de «Size guide».
	$legacy = (string) get_post_meta( $post->ID, 'desplegable_2', true );
	?>
	<p class="tm-st-help">Tallas en las columnas y medidas en las filas. Escribe solo el número (p. ej. <code>45</code>): la unidad se añade sola. Deja la tabla vacía (sin tallas) para no enseñar ninguna.</p>
	<div id="tm-st"
		data-table="<?php echo esc_attr( $table ? wp_json_encode( $table ) : '' ); ?>"
		data-legacy="<?php echo esc_attr( $legacy ); ?>"></div>
	<input type="hidden" name="tatsmood_size_table" id="tm-st-input" value="<?php echo esc_attr( $table ? wp_json_encode( $table ) : '' ); ?>">
	<?php
}

add_action(
	'save_post_product',
	static function ( int $post_id ) {
		if ( ! isset( $_POST['tatsmood_size_table_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tatsmood_size_table_nonce'] ) ), 'tatsmood_size_table' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$raw  = isset( $_POST['tatsmood_size_table'] ) ? wp_unslash( $_POST['tatsmood_size_table'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$data = $raw ? json_decode( (string) $raw, true ) : null;
		$text = static fn( $v ) => mb_substr( sanitize_text_field( (string) $v ), 0, 40 );

		$sizes = array_values( array_filter( array_map( $text, (array) ( $data['sizes'] ?? array() ) ), 'strlen' ) );
		$sizes = array_slice( $sizes, 0, 12 );
		$rows  = array();
		foreach ( array_slice( (array) ( $data['rows'] ?? array() ), 0, 15 ) as $row ) {
			$label = $text( $row['label'] ?? '' );
			if ( '' === $label ) {
				continue;
			}
			$values = array_map( $text, array_slice( (array) ( $row['values'] ?? array() ), 0, count( $sizes ) ) );
			$rows[] = array( 'label' => $label, 'values' => array_pad( $values, count( $sizes ), '' ) );
		}

		if ( ! $sizes || ! $rows ) {
			delete_post_meta( $post_id, TATSMOOD_SIZE_TABLE_META );
			return;
		}
		$unit = $text( $data['unit'] ?? 'cm' );
		update_post_meta( $post_id, TATSMOOD_SIZE_TABLE_META, wp_slash( wp_json_encode( array( 'unit' => $unit, 'sizes' => $sizes, 'rows' => $rows ) ) ) );
	}
);

/* Editor de la tabla (solo en la pantalla de edición de productos). */
add_action(
	'admin_footer-post.php',
	'tatsmood_size_table_assets'
);
add_action(
	'admin_footer-post-new.php',
	'tatsmood_size_table_assets'
);

function tatsmood_size_table_assets(): void {
	$screen = get_current_screen();
	if ( ! $screen || 'product' !== $screen->post_type ) {
		return;
	}
	?>
	<style>
		#tm-st .tm-st-unit { margin: 0 0 12px; }
		#tm-st table { border-collapse: collapse; margin-bottom: 10px; }
		#tm-st th, #tm-st td { padding: 3px; vertical-align: middle; }
		#tm-st input[type="text"] { width: 90px; }
		#tm-st .tm-st-label input { width: 130px; }
		#tm-st .tm-st-x { color: #b32d2e; cursor: pointer; border: 0; background: none; font-size: 16px; line-height: 1; padding: 0 4px; }
		#tm-st .tm-st-actions .button { margin-right: 6px; }
		.tm-st-help { color: #646970; }
	</style>
	<script>
	(function () {
		var box = document.getElementById('tm-st');
		var input = document.getElementById('tm-st-input');
		if (!box || !input) return;

		var empty = { unit: 'cm', sizes: [], rows: [] };
		var data;
		try { data = JSON.parse(box.dataset.table || 'null'); } catch (e) { data = null; }
		data = data || JSON.parse(JSON.stringify(empty));

		// Tabla escrita a mano en «Size guide» → filas y columnas (para «Importar»).
		function parseLegacy(html) {
			var t = new DOMParser().parseFromString(html || '', 'text/html').querySelector('table');
			if (!t) return null;
			var rows = Array.prototype.map.call(t.querySelectorAll('tr'), function (tr) {
				return Array.prototype.map.call(tr.children, function (c) { return c.textContent.trim(); });
			}).filter(function (r) { return r.length > 1; });
			if (rows.length < 2) return null;
			var sizes = rows[0].slice(1);
			var unit = '';
			var body = rows.slice(1).map(function (r) {
				return { label: r[0], values: sizes.map(function (_, i) {
					var v = r[i + 1] || '';
					var m = v.match(/^([\d.,]+)\s*([a-zA-Z"]+)$/);
					if (m) { unit = unit || m[2]; return m[1]; }
					return v;
				}) };
			});
			return { unit: unit || 'cm', sizes: sizes, rows: body };
		}
		var legacy = parseLegacy(box.dataset.legacy);

		function save() { input.value = data.sizes.length && data.rows.length ? JSON.stringify(data) : ''; }

		function el(tag, attrs, children) {
			var e = document.createElement(tag);
			Object.keys(attrs || {}).forEach(function (k) { if (k === 'on') { Object.keys(attrs.on).forEach(function (ev) { e.addEventListener(ev, attrs.on[ev]); }); } else e.setAttribute(k, attrs[k]); });
			(children || []).forEach(function (c) { e.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
			return e;
		}
		function field(value, onInput, extra) {
			var i = el('input', Object.assign({ type: 'text', value: value || '' }, extra || {}));
			i.addEventListener('input', function () { onInput(i.value); save(); });
			return i;
		}
		function remove(title, fn) {
			return el('button', { type: 'button', class: 'tm-st-x', title: title, 'aria-label': title, on: { click: function () { fn(); save(); render(); } } }, ['×']);
		}

		function render() {
			box.innerHTML = '';
			box.appendChild(el('p', { class: 'tm-st-unit' }, [
				el('label', {}, ['Unidad: ', field(data.unit, function (v) { data.unit = v; }, { style: 'width:60px' })]),
			]));

			if (data.sizes.length) {
				var head = el('tr', {}, [el('th', {}, ['Medida ↓ / Talla →'])].concat(data.sizes.map(function (s, i) {
					return el('th', {}, [field(s, function (v) { data.sizes[i] = v; }, { placeholder: 'Talla' }), remove('Quitar esta talla', function () {
						data.sizes.splice(i, 1);
						data.rows.forEach(function (r) { r.values.splice(i, 1); });
					})]);
				})));
				var body = data.rows.map(function (r, ri) {
					return el('tr', {}, [el('td', { class: 'tm-st-label' }, [field(r.label, function (v) { r.label = v; }, { placeholder: 'Medida (p. ej. Width)' }), remove('Quitar esta medida', function () { data.rows.splice(ri, 1); })])]
						.concat(data.sizes.map(function (_, ci) {
							return el('td', {}, [field(r.values[ci], function (v) { r.values[ci] = v; }, { placeholder: '—' })]);
						})));
				});
				box.appendChild(el('table', {}, [el('thead', {}, [head]), el('tbody', {}, body)]));
			}

			var actions = [
				el('button', { type: 'button', class: 'button', on: { click: function () {
					data.sizes.push('');
					data.rows.forEach(function (r) { r.values.push(''); });
					if (!data.rows.length) data.rows.push({ label: '', values: data.sizes.map(function () { return ''; }) });
					save(); render();
				} } }, ['+ Añadir talla']),
				el('button', { type: 'button', class: 'button', on: { click: function () {
					if (!data.sizes.length) data.sizes.push('');
					data.rows.push({ label: '', values: data.sizes.map(function () { return ''; }) });
					save(); render();
				} } }, ['+ Añadir medida']),
			];
			if (legacy) {
				actions.push(el('button', { type: 'button', class: 'button', on: { click: function () {
					if (data.sizes.length && !confirm('Se sustituirá la tabla de este panel por la que hay escrita en «Size guide». ¿Seguir?')) return;
					data = JSON.parse(JSON.stringify(legacy)); save(); render();
				} } }, ['Importar la tabla de «Size guide»']));
			}
			if (data.sizes.length) {
				actions.push(el('button', { type: 'button', class: 'button-link-delete', on: { click: function () {
					if (!confirm('¿Vaciar la tabla de tallas?')) return;
					data = JSON.parse(JSON.stringify(empty)); save(); render();
				} } }, ['Vaciar tabla']));
			}
			box.appendChild(el('p', { class: 'tm-st-actions' }, actions));
		}

		save();
		render();
	})();
	</script>
	<?php
}
