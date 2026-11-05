<?php
/**
 * Pasa las tablas de tallas escritas a mano (campo «Size guide», desplegable_2) al panel
 * «Tabla de tallas» (mu-plugins/tatsmood-size-guide.php).
 *
 * Uso (en public_html):
 *   wp eval-file wp-content/tatsmood-wp/tools/migrate-size-tables.php          → solo enseña lo que haría
 *   wp eval-file wp-content/tatsmood-wp/tools/migrate-size-tables.php run      → lo hace
 *
 * No borra nada: el texto de «Size guide» se queda igual (la web, cuando hay tabla en el
 * panel, enseña la del panel en el lugar de la escrita a mano). Los productos que ya tienen
 * tabla en el panel se saltan.
 */

defined( 'ABSPATH' ) || exit;

$tm_run = in_array( 'run', (array) ( $args ?? array() ), true );
echo $tm_run ? "== MIGRANDO ==\n" : "== PRUEBA (no se guarda nada; añade «run» al final para hacerlo) ==\n";

$tm_ids  = get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) );
$tm_done = 0;
$tm_skip = 0;

foreach ( $tm_ids as $tm_id ) {
	$html = (string) get_post_meta( $tm_id, 'desplegable_2', true );
	if ( false === stripos( $html, '<table' ) ) {
		continue;
	}
	$name = get_the_title( $tm_id );
	if ( get_post_meta( $tm_id, 'tatsmood_size_table', true ) ) {
		echo "· #$tm_id $name: ya tiene tabla en el panel, se salta\n";
		++$tm_skip;
		continue;
	}

	libxml_use_internal_errors( true );
	$doc = new DOMDocument();
	$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
	libxml_clear_errors();
	$table = $doc->getElementsByTagName( 'table' )->item( 0 );
	$rows  = array();
	foreach ( $table ? $table->getElementsByTagName( 'tr' ) : array() as $tr ) {
		$cells = array();
		foreach ( $tr->childNodes as $c ) {
			if ( XML_ELEMENT_NODE === $c->nodeType && in_array( strtolower( $c->nodeName ), array( 'th', 'td' ), true ) ) {
				$cells[] = trim( preg_replace( '/\s+/u', ' ', $c->textContent ) );
			}
		}
		if ( count( $cells ) > 1 ) {
			$rows[] = $cells;
		}
	}
	if ( count( $rows ) < 2 ) {
		echo "! #$tm_id $name: no se entiende la tabla, se salta (revísala a mano)\n";
		++$tm_skip;
		continue;
	}

	$sizes = array_slice( $rows[0], 1 );
	$unit  = '';
	$body  = array();
	foreach ( array_slice( $rows, 1 ) as $r ) {
		$values = array();
		foreach ( $sizes as $i => $unused ) {
			$v = $r[ $i + 1 ] ?? '';
			if ( preg_match( '/^([\d.,]+)\s*([a-zA-Z"]+)$/u', $v, $m ) ) {
				$unit = $unit ?: $m[2];
				$v    = $m[1];
			}
			$values[] = $v;
		}
		$body[] = array( 'label' => $r[0], 'values' => $values );
	}
	$data = array( 'unit' => $unit ?: 'cm', 'sizes' => $sizes, 'rows' => $body );

	$preview = implode( ' | ', array_map( static fn( $r ) => $r['label'] . ': ' . implode( ', ', $r['values'] ), $body ) );
	echo "✓ #$tm_id $name — tallas: " . implode( ', ', $sizes ) . " — unidad: {$data['unit']} — $preview\n";
	if ( $tm_run ) {
		update_post_meta( $tm_id, 'tatsmood_size_table', wp_slash( wp_json_encode( $data ) ) );
	}
	++$tm_done;
}

echo "\n$tm_done tablas " . ( $tm_run ? 'migradas' : 'para migrar' ) . ", $tm_skip saltadas.\n";

// La web guarda el catálogo en caché: se le avisa para que lo vuelva a leer.
if ( $tm_run && $tm_done && function_exists( 'tatsmood_frontend_url' ) && defined( 'TATSMOOD_REVALIDATE_SECRET' ) ) {
	$body = (string) wp_json_encode( array( 'source' => 'wordpress' ) );
	$res  = wp_remote_post(
		tatsmood_frontend_url( '/api/revalidate' ),
		array(
			'timeout' => 10,
			'headers' => array(
				'Content-Type'         => 'application/json',
				'X-Tatsmood-Signature' => base64_encode( hash_hmac( 'sha256', $body, TATSMOOD_REVALIDATE_SECRET, true ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			),
			'body'    => $body,
		)
	);
	echo 'Aviso a la web: ' . ( is_wp_error( $res ) ? $res->get_error_message() : wp_remote_retrieve_response_code( $res ) ) . "\n";
}
