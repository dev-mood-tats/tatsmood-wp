<?php
/**
 * Conexión a MySQL: persistente y con reintentos (drop-in de WordPress).
 *
 * El problema: en este hosting, cuando varias peticiones intentan ABRIR una conexión a MySQL
 * en el mismo instante, parte se rechazan al momento («ERROR 2002: Operation not permitted»)
 * y WordPress respondía «Error establishing a database connection» (la tienda, la API que
 * usa la web y el checkout fallaban a ratos). Con 8 peticiones a la vez fallaba ~1 de cada 3.
 *
 * 1. Conexión persistente (la solución de fondo): cada proceso de PHP abre su conexión una
 *    vez y la reutiliza en las siguientes peticiones, en vez de abrir una nueva cada vez.
 *    Así casi no hay conexiones nuevas y desaparecen los rechazos (probado: 60 de 60 bien,
 *    frente a 25 de 60 sin ella). PHP limpia la conexión al reutilizarla (deshace
 *    transacciones a medias, etc.), y WordPress vuelve a fijar el juego de caracteres y el
 *    modo SQL en cada petición.
 *    WordPress no admite el prefijo «p:» en DB_HOST (lo confunde con un puerto), por eso se
 *    añade aquí, después de que WordPress lea el host.
 * 2. Reintentos, por si aun así un intento se rechaza: hasta 6, en menos de 1,5 s.
 *
 * WordPress solo carga este archivo desde wp-content/db.php; allí hay un archivo de una
 * línea (copiado una sola vez) que lo incluye desde el repositorio.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Tatsmood_wpdb' ) ) {
	class Tatsmood_wpdb extends wpdb {
		/** Host con «p:» delante: mysqli abre (o reutiliza) una conexión persistente. */
		public function parse_db_host( $host ) {
			$data = parent::parse_db_host( $host );
			if ( is_array( $data ) && ! empty( $data[0] ) && empty( $data[3] ) && 0 !== strpos( $data[0], 'p:' ) ) {
				$data[0] = 'p:' . $data[0];
			}
			return $data;
		}

		public function db_connect( $allow_bail = true ) {
			$tries = 6;
			for ( $i = 1; $i < $tries; $i++ ) {
				if ( parent::db_connect( false ) ) {
					return true;
				}
				// 0,1 s, 0,2 s, 0,3 s, 0,4 s, 0,4 s (+ un poco al azar, para no reintentar todas a la vez).
				usleep( min( 100000 * $i, 400000 ) + random_int( 0, 50000 ) );
			}
			return parent::db_connect( $allow_bail );
		}
	}
	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$wpdb = new Tatsmood_wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
}
