<?php
/**
 * Plugin Name: Tatsmood – imágenes
 * Description: Tres tamaños propios de cada foto (600, 1200 y 2400 px) en WebP al 90 %, generados al subirla; la API le da a Next.js la lista (srcset) para que sirva las fotos tal cual, sin procesarlas.
 *
 * Así el trabajo de las imágenes lo hace WordPress una sola vez, al subirlas, y la web en
 * Next.js no redimensiona nada. Los tamaños por defecto de WordPress (que se pixelaban)
 * ya no se generan; solo se dejan las miniaturas pequeñas que usan el escritorio y
 * WooCommerce (checkout, carrito, correos).
 *
 * Para rehacer los tamaños de las fotos que ya están subidas: wp media regenerate --yes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Anchos propios, sin recortar (cada tamaño mantiene la proporción de la foto):
 * - 600 px: tarjetas de producto en móvil (2 por fila en un móvil retina: unos 585 px).
 * - 1200 px: fotos a todo el ancho en móvil (unos 1170 px) y tarjetas en escritorio.
 * - 2400 px: fichas y cabeceras en escritorio y tablet (también pantallas retina).
 * Por encima, la original. Si una foto es más pequeña que un tamaño, ese no se genera.
 */
const TATSMOOD_IMAGE_WIDTHS = array( 600, 1200, 2400 );

/* 1. JPG y PNG se guardan en WebP (todos sus tamaños). */
add_filter(
	'image_editor_output_format',
	static function ( array $formats ): array {
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/jpg']  = 'image/webp';
		$formats['image/png']  = 'image/webp';
		return $formats;
	}
);

/* 2. Calidad 90 % en todo lo que genera WordPress. */
add_filter( 'wp_editor_set_quality', static fn() => 90 );

/* La original se guarda tal cual: WordPress no la reduce a 2560 px («-scaled»). */
add_filter( 'big_image_size_threshold', '__return_false' );

/* 3. Nuestros tamaños… */
add_action(
	'after_setup_theme',
	static function () {
		foreach ( TATSMOOD_IMAGE_WIDTHS as $w ) {
			add_image_size( "tatsmood-{$w}", $w, 0, false );
		}
	},
	20
);

/* …y fuera los de WordPress y WooCommerce que no hacen falta. Se quedan solo las
   miniaturas pequeñas (escritorio, checkout, carrito y correos). */
add_filter(
	'intermediate_image_sizes_advanced',
	static function ( array $sizes ): array {
		foreach ( array( 'medium_large', 'large', '1536x1536', '2048x2048', 'woocommerce_single' ) as $name ) {
			unset( $sizes[ $name ] );
		}
		return $sizes;
	}
);

/**
 * Lista de tamaños de una foto para el atributo srcset («url 600w, url 1200w, url 2400w, url original»): solo los
 * nuestros y la original.
 */
function tatsmood_srcset( int $id ): string {
	$meta = $id ? wp_get_attachment_metadata( $id ) : false;
	if ( ! is_array( $meta ) || empty( $meta['width'] ) ) {
		return '';
	}
	$base    = trailingslashit( dirname( (string) wp_get_attachment_url( $id ) ) );
	$sources = array();
	foreach ( TATSMOOD_IMAGE_WIDTHS as $w ) {
		$size = $meta['sizes'][ "tatsmood-{$w}" ] ?? null;
		if ( $size && ! empty( $size['file'] ) && ! empty( $size['width'] ) ) {
			$sources[ (int) $size['width'] ] = $base . rawurlencode( $size['file'] ) . ' ' . (int) $size['width'] . 'w';
		}
	}
	$sources[ (int) $meta['width'] ] = wp_get_attachment_url( $id ) . ' ' . (int) $meta['width'] . 'w';
	ksort( $sources );
	return count( $sources ) > 1 ? implode( ', ', $sources ) : '';
}

/* 4. La API le pasa el srcset a Next.js: fotos de productos (WooCommerce) y de Medios. */
add_filter(
	'woocommerce_rest_prepare_product_object',
	static function ( $response ) {
		if ( $response instanceof WP_REST_Response && ! empty( $response->data['images'] ) && is_array( $response->data['images'] ) ) {
			foreach ( $response->data['images'] as &$img ) {
				if ( ! empty( $img['id'] ) ) {
					$img['srcset'] = tatsmood_srcset( (int) $img['id'] );
				}
			}
			unset( $img );
		}
		return $response;
	},
	20
);

add_action(
	'rest_api_init',
	static function () {
		register_rest_field(
			'attachment',
			'tatsmood_srcset',
			array( 'get_callback' => static fn( $post ) => tatsmood_srcset( (int) $post['id'] ) )
		);
	}
);
