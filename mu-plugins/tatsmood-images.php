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
 * (En la web nunca se sirve una foto de más de 900 KB: ver TATSMOOD_MAX_SERVED_BYTES.)
 */

defined( 'ABSPATH' ) || exit;

/**
 * Anchos propios, sin recortar (cada tamaño mantiene la proporción de la foto):
 * - 600 px: tarjetas de producto en móvil (2 por fila en un móvil retina: unos 585 px).
 * - 1200 px: fotos a todo el ancho en móvil (unos 1170 px) y tarjetas en escritorio.
 * - 2400 px: fichas y cabeceras en escritorio y tablet (también pantallas retina).
 * Por encima, la original (solo en la web si pesa 900 KB o menos). Si una foto es más
 * pequeña que un tamaño, ese no se genera.
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
 * Peso máximo de una foto servida en la web: 900 KB.
 *
 * En Medios se guarda la original tal cual, pese lo que pese (máxima calidad para
 * el archivo). Pero en la web solo se ofrecen los tamaños que no pasan de este peso:
 * si la original pesa más, el navegador usa como mucho la versión de 2400 px (y si
 * esa también pasara, la de 1200).
 */
const TATSMOOD_MAX_SERVED_BYTES = 900 * 1024;

/** Peso en bytes de un archivo de la foto: el que guarda WordPress o, si no está, el del disco. */
function tatsmood_image_bytes( array $data, string $path ): int {
	if ( ! empty( $data['filesize'] ) ) {
		return (int) $data['filesize'];
	}
	return is_readable( $path ) ? (int) filesize( $path ) : 0;
}

/**
 * Lista de tamaños de una foto para el atributo srcset («url 600w, url 1200w, url 2400w»):
 * solo los nuestros y la original, y solo los que pesan 900 KB o menos. El más pequeño
 * se queda siempre, para que nunca falte imagen.
 */
function tatsmood_srcset( int $id ): string {
	$meta = $id ? wp_get_attachment_metadata( $id ) : false;
	if ( ! is_array( $meta ) || empty( $meta['width'] ) ) {
		return '';
	}
	$full_url = (string) wp_get_attachment_url( $id );
	$full_dir = trailingslashit( dirname( (string) get_attached_file( $id ) ) );
	$base     = trailingslashit( dirname( $full_url ) );

	$candidates = array(); // ancho => [ entrada srcset, bytes ]
	foreach ( TATSMOOD_IMAGE_WIDTHS as $w ) {
		$size = $meta['sizes'][ "tatsmood-{$w}" ] ?? null;
		if ( $size && ! empty( $size['file'] ) && ! empty( $size['width'] ) ) {
			$candidates[ (int) $size['width'] ] = array(
				$base . rawurlencode( $size['file'] ) . ' ' . (int) $size['width'] . 'w',
				tatsmood_image_bytes( $size, $full_dir . $size['file'] ),
			);
		}
	}
	$candidates[ (int) $meta['width'] ] = array(
		$full_url . ' ' . (int) $meta['width'] . 'w',
		tatsmood_image_bytes( $meta, (string) get_attached_file( $id ) ),
	);
	ksort( $candidates );

	$sources = array();
	foreach ( $candidates as $c ) {
		if ( ! $sources || ( $c[1] > 0 && $c[1] <= TATSMOOD_MAX_SERVED_BYTES ) ) {
			$sources[] = $c[0];
		}
	}
	return count( $sources ) > 1 || count( $candidates ) > 1 ? implode( ', ', $sources ) : '';
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

/* Pedidos (página de confirmación): la foto de cada línea también lleva su srcset. */
add_filter(
	'woocommerce_rest_prepare_shop_order_object',
	static function ( $response ) {
		if ( $response instanceof WP_REST_Response && ! empty( $response->data['line_items'] ) && is_array( $response->data['line_items'] ) ) {
			foreach ( $response->data['line_items'] as &$item ) {
				if ( ! empty( $item['image']['id'] ) ) {
					$item['image']['srcset'] = tatsmood_srcset( (int) $item['image']['id'] );
				}
			}
			unset( $item );
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
