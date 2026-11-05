<?php
/**
 * Plugin Name: Tatsmood – código del repositorio
 * Description: Carga los mu-plugins de wp-content/tatsmood-wp (el repositorio de GitHub que Hostinger despliega solo con cada push).
 *
 * Este archivo va copiado una sola vez en wp-content/mu-plugins/ (WordPress solo carga los
 * mu-plugins sueltos de esa carpeta); aquí está solo como copia de referencia.
 * - mu-plugins/*.php del repositorio → se cargan aquí, en orden alfabético, como hacía WordPress.
 * - El tema: wp-content/themes/tatsmood-headless es un enlace simbólico a
 *   tatsmood-wp/themes/tatsmood-headless (WordPress lo ve como siempre).
 */

defined( 'ABSPATH' ) || exit;

$tatsmood_mu = glob( WP_CONTENT_DIR . '/tatsmood-wp/mu-plugins/*.php' ) ?: array();
sort( $tatsmood_mu );
foreach ( $tatsmood_mu as $tatsmood_file ) {
	require_once $tatsmood_file;
}
unset( $tatsmood_mu, $tatsmood_file );
