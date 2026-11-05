<?php
/**
 * Correos de WooCommerce con el estilo de la web.
 *
 * No se sobrescriben las plantillas de WooCommerce (así siguen actualizándose solas): se
 * añade CSS encima del suyo («woocommerce_email_styles», que WooCommerce pasa a estilos en
 * línea antes de enviar) y se ajustan un par de detalles con filtros.
 * - Inter Tight (en Gmail/Outlook, que no cargan fuentes externas, Helvetica/Arial).
 * - Jerarquía de tamaños (22 / 15 / 13 / 11px), sin negritas; título e introducción centrados,
 *   el resto a la izquierda; líneas finas #e9e5e5.
 * - Fotos de producto en vertical (4:5), como en la web.
 * - Pie con enlaces a la tienda y redes ({shop_url} = la web en Next.js).
 * Colores, logo y texto del pie: WooCommerce → Ajustes → Correos electrónicos.
 */

defined( 'ABSPATH' ) || exit;

const TATSMOOD_EMAIL_FONT = "'Inter Tight', 'Helvetica Neue', Helvetica, Arial, sans-serif";

/** Fuente de la web (la cargan Apple Mail, iOS y la mayoría de apps; el resto usa Helvetica/Arial). */
add_action(
	'woocommerce_email_header',
	static function () {
		echo '<link href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@300;400&display=swap" rel="stylesheet" type="text/css">';
	},
	1
);

add_filter(
	'woocommerce_email_styles',
	static function ( $css ) {
		$font = TATSMOOD_EMAIL_FONT;
		$line = '#e9e5e5';
		return $css . "
#template_header, #body_content_inner, .text, .address-title, .order-item-data, .email-logo-text,
#template_footer #credit, #body_content table td td.email-additional-content, h1, h2, h3 {
	font-family: {$font};
}

/* Tamaños: título 22px · subtítulos 15px · texto (también el total) 13px · detalles 11px. */
#body_content_inner, .td, .address, .text, .order-item-data {
	color: #000000;
	font-size: 13px;
	font-weight: 300;
	line-height: 19px;
}
#template_header { font-weight: 300; }
#template_header_image { padding: 40px 32px 0; }
#header_wrapper { padding: 32px 32px 0; }
h1, #header_wrapper h1 {
	font-size: 22px;
	font-weight: 400;
	letter-spacing: -0.2px;
	line-height: 28px;
	text-align: center;
}
h2, h3 {
	font-size: 15px;
	font-weight: 400;
	line-height: 20px;
	margin: 0 0 6px;
	text-align: left;
}
h2.email-order-detail-heading span,
h2.email-order-detail-heading span a {
	display: block;
	color: #000000;
	font-size: 11px;
	font-weight: 300;
	line-height: 16px;
}
/* Todo el texto en grosor fino (300), como en la web; solo los títulos van a 400. Con
   !important porque WooCommerce pone «font-weight: normal/bold» en línea en algunas celdas. */
#body_content td, #body_content th, #body_content p, #body_content li, #body_content span,
#body_content address, #body_content strong, #body_content b, #body_content a,
#body_content .order-item-data h3, #body_content h3.wc-product-name { font-weight: 300 !important; }
/* Títulos pequeños (cabecera de la tabla, «Customer note», direcciones): 400, como los grandes. */
#body_content .email-order-details thead th, #body_content b.address-title,
#body_content .order-customer-note b, #body_content #addresses h2 { font-weight: 400 !important; }
#body_content .email-order-details thead th { font-size: 11px; }
#body_content .email-order-details .order-totals-total th,
#body_content .email-order-details .order-totals-total td {
	font-size: 13px;
	font-weight: 300;
}
.email-order-item-meta, #body_content td ul.wc-item-meta { font-size: 11px; line-height: 16px; color: #000000; }
.email-order-item-thumbnail img { width: 48px; height: auto; }

/* Centrados: el título, la introducción («Hi John, …»), «Order summary» con el número de
   pedido y el pie. A la izquierda: la tabla del pedido, la nota del cliente, las direcciones y el texto final. */
.email-introduction, .email-introduction p, #template_footer #credit,
#body_content h2.email-order-detail-heading, h2.email-order-detail-heading span { text-align: center; }
/* Aire entre «Order summary» y la tabla de productos. */
#body_content h2.email-order-detail-heading { margin-bottom: 24px; }
h2, h3, #body_content .email-additional-content p, .email-additional-content-aligned p,
.address, .address-title, #addresses td, #addresses h2 { text-align: left; }
#body_content p { margin: 0 0 12px; }
#body_content .email-order-details td p, .order-item-data, .order-item-data td, .order-item-data p { text-align: left; }
.order-item-data h3, h3.wc-product-name { font-size: 13px; line-height: 19px; margin: 0; text-align: left; }

/* Líneas finas como en la web. WooCommerce añade además un <hr> bajo cada tabla (salían
   dos líneas seguidas): fuera. */
#body_content hr { display: none !important; }
.hr { border-bottom: 1px solid {$line}; opacity: 1; }
#body_content .email-order-details tbody tr:last-child td,
#body_content .email-order-details .order-totals-last td,
#body_content .email-order-details .order-totals-last th,
#body_content .email-order-details .order-customer-note td,
#template_footer #credit { border-color: {$line}; }
#body_content .email-order-details .order-customer-note td { text-align: left; }
#addresses { margin-top: 24px; }

a, .link { color: #000000; font-weight: 300; text-decoration: underline; }
#template_footer #credit { font-size: 11px; font-weight: 300; line-height: 16px; }
#template_footer #credit a { color: #000000; }
";
	}
);

/** Fotos de producto del pedido en su proporción (4:5 en la web), no recortadas en cuadrado. */
add_filter(
	'woocommerce_order_item_thumbnail',
	static function ( $image, $item ) {
		$product = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : null;
		$id      = $product ? (int) $product->get_image_id() : 0;
		if ( ! $id && $product && $product->get_parent_id() ) {
			$parent = wc_get_product( $product->get_parent_id() );
			$id     = $parent ? (int) $parent->get_image_id() : 0;
		}
		if ( ! $id ) {
			return $image;
		}
		$src = function_exists( 'tatsmood_thumb_url' ) ? tatsmood_thumb_url( $id, 'medium' ) : wp_get_attachment_image_url( $id, 'medium' );
		return sprintf( '<img src="%s" alt="" width="48" style="width:48px;height:auto;display:block;border:0;" />', esc_url( $src ) );
	},
	10,
	2
);

/** {shop_url} en los textos de los correos (p. ej. el pie): la web en Next.js, no WordPress. */
function tatsmood_email_shop_url( $string ) {
	$url = function_exists( 'tatsmood_theme_frontend_url' ) ? tatsmood_theme_frontend_url( '/' ) : home_url( '/' );
	return str_replace( '{shop_url}', untrailingslashit( $url ), (string) $string );
}
add_filter( 'woocommerce_email_format_string', 'tatsmood_email_shop_url' );
add_filter( 'woocommerce_email_footer_text', 'tatsmood_email_shop_url', 20 );
