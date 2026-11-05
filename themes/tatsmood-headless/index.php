<?php
/**
 * Plantilla única: el checkout, el pago de pedidos y Mi cuenta usan el contenido de su
 * página (bloques o shortcodes de WooCommerce). El resto de URLs las redirige a Next.js
 * el mu-plugin tatsmood-headless.
 */
defined( 'ABSPATH' ) || exit;

get_header();
while ( have_posts() ) {
	the_post();
	the_content();
}
get_footer();
