<?php
/**
 * Quita las tablas de tallas escritas a mano del texto de «Size guide» (desplegable_2) en los
 * productos que ya tienen la tabla en el panel «Tabla de tallas». El resto del texto (notas,
 * «Model wears M»…) se queda.
 *
 * Por si acaso, el texto original se guarda en el producto («_tatsmood_size_guide_backup»,
 * oculto) y se puede recuperar.
 *
 * Uso (en public_html):
 *   wp eval-file wp-content/tatsmood-wp/tools/remove-handwritten-size-tables.php        → solo enseña lo que haría
 *   wp eval-file wp-content/tatsmood-wp/tools/remove-handwritten-size-tables.php run    → lo hace
 */

defined( 'ABSPATH' ) || exit;

$tm_run = in_array( 'run', (array) ( $args ?? array() ), true );
echo $tm_run ? "== QUITANDO TABLAS ==\n" : "== PRUEBA (no se guarda nada; añade «run» al final para hacerlo) ==\n";

$tm_ids  = get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) );
$tm_done = 0;

foreach ( $tm_ids as $tm_id ) {
	$html = (string) get_post_meta( $tm_id, 'desplegable_2', true );
	if ( false === stripos( $html, '<table' ) || ! get_post_meta( $tm_id, 'tatsmood_size_table', true ) ) {
		continue;
	}
	$new = preg_replace( '#<figure[^>]*>\s*(<table.*?</table>)\s*</figure>#is', '$1', $html );
	$new = preg_replace( '#<table.*?</table>#is', '', $new );
	// Restos vacíos: párrafos sin texto y saltos de línea sobrantes.
	$new = preg_replace( '#<p>(\s|&nbsp;|<br\s*/?>)*</p>#i', '', $new );
	$new = trim( preg_replace( "/\n{3,}/", "\n\n", $new ) );

	$rest = trim( wp_strip_all_tags( $new ) );
	echo '✓ #' . $tm_id . ' ' . html_entity_decode( get_the_title( $tm_id ) ) . ' — queda: ' . ( '' === $rest ? '(nada)' : '«' . mb_substr( preg_replace( '/\s+/u', ' ', $rest ), 0, 80 ) . '»' ) . "\n";
	if ( $tm_run ) {
		if ( ! get_post_meta( $tm_id, '_tatsmood_size_guide_backup', true ) ) {
			update_post_meta( $tm_id, '_tatsmood_size_guide_backup', wp_slash( $html ) );
		}
		update_post_meta( $tm_id, 'desplegable_2', wp_slash( $new ) );
	}
	++$tm_done;
}

echo "\n$tm_done productos " . ( $tm_run ? 'limpiados' : 'para limpiar' ) . ".\n";

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
