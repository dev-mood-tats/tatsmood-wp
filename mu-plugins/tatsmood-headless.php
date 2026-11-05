<?php
/**
 * Plugin Name: Tatsmood – Tienda headless
 * Description: Conecta la tienda en Next.js con el checkout de WooCommerce: recibe el carrito desde Next.js, manda al cliente a las páginas de gracias/error de Next.js al terminar y redirige a Next.js el resto de páginas públicas de WordPress.
 * Version:     1.0.0
 *
 * Es un "must-use plugin": se carga siempre y no se puede desactivar desde el panel.
 *
 * Dirección de la web en Next.js: por defecto http://localhost:3000. En producción, añade en wp-config.php:
 *   define( 'TATSMOOD_FRONTEND_URL', 'https://tatsmood.com' );
 */

defined( 'ABSPATH' ) || exit;

function tatsmood_frontend_url( string $path = '/' ): string {
	$base = defined( 'TATSMOOD_FRONTEND_URL' ) ? TATSMOOD_FRONTEND_URL : 'http://localhost:3000';
	return untrailingslashit( $base ) . '/' . ltrim( $path, '/' );
}

/**
 * 1. Traspaso del carrito.
 * Next.js manda al cliente a /checkout/?session=<Cart-Token>. WooCommerce copia ese carrito
 * a una sesión normal del navegador, pero si el navegador ya tenía una copia de una visita anterior
 * la reutiliza aunque el carrito haya cambiado en Next.js. Para usar siempre el carrito actual,
 * ignoramos la cookie de sesión de WooCommerce en las peticiones que traen ?session=.
 */
add_action(
	'plugins_loaded',
	static function () {
		if ( is_admin() || empty( $_GET['session'] ) || ! is_string( $_GET['session'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$cookie = apply_filters( 'woocommerce_cookie', 'wp_woocommerce_session_' . COOKIEHASH );
		unset( $_COOKIE[ $cookie ] );
	},
	1
);

/**
 * 1b. WooCommerce redirige automáticamente el checkout a la página de carrito si lo ve
 * vacío. El carrito real llega poco después vía JavaScript (el bloque de Checkout pide
 * el carrito a la Store API usando el ?session=), así que desactivamos esa redirección
 * automática justo cuando venimos con ?session=: si no, WordPress nos manda fuera antes
 * de que el bloque tenga ocasión de cargar el carrito.
 */
add_filter(
	'woocommerce_checkout_redirect_empty_cart',
	static function ( $redirect ) {
		if ( ! empty( $_GET['session'] ) && is_string( $_GET['session'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}
		return $redirect;
	}
);

/**
 * 2. Final de la compra → páginas de Next.js.
 * Al llegar a «Pedido recibido» de WooCommerce, el cliente pasa a la web:
 *   - Pedido correcto (pagado o con el pago pendiente de confirmar): se vacía el carrito original
 *     de Next.js (la sesión de la que se copió el carrito) y se va a /order/thank-you.
 *   - Pago fallido: se conserva el carrito y se va a /order/error, con opción de reintentar el pago.
 * Se pasan el número y la clave del pedido; Next.js comprueba la clave antes de mostrar nada.
 */
add_action(
	'template_redirect',
	static function () {
		if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
			return;
		}

		$order_id = absint( get_query_var( 'order-received' ) );
		$key      = isset( $_GET['key'] ) && is_string( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order || ! $key || ! hash_equals( $order->get_order_key(), $key ) ) {
			wp_redirect( tatsmood_frontend_url( '/order/error' ), 302 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			exit;
		}

		$args = array(
			'order' => $order->get_id(),
			'key'   => rawurlencode( $order->get_order_key() ),
		);

		if ( $order->has_status( 'failed' ) ) {
			wp_redirect( add_query_arg( $args, tatsmood_frontend_url( '/order/error' ) ), 302 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			exit;
		}

		if ( WC()->session ) {
			$previous = WC()->session->get( 'previous_customer_id' );
			if ( is_string( $previous ) && str_starts_with( $previous, 't_' ) ) {
				WC()->session->delete_session( $previous );
				WC()->session->set( 'previous_customer_id', null );
			}
		}

		wp_redirect( add_query_arg( $args, tatsmood_frontend_url( '/order/thank-you' ) ), 302 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	},
	5
);

/**
 * 2b. Si el cliente cancela el pedido (p. ej. desde la pasarela de pago), va a la página de aviso de
 * Next.js en vez de al carrito de WordPress. El carrito de la web no se toca.
 */
add_action(
	'woocommerce_cancelled_order',
	static function () {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		wp_redirect( add_query_arg( 'reason', 'cancelled', tatsmood_frontend_url( '/order/error' ) ), 302 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}
);

/**
 * 2c. Volver atrás desde la página de gracias.
 * Tras pagar, el carrito queda vacío. Si el cliente pulsa «Atrás» en el navegador, este podría mostrar
 * la copia del checkout que tenía guardada (con el pedido antiguo y un error). Para evitarlo:
 *   - El checkout no se guarda en la caché de «Atrás/Adelante»: si el navegador lo restaura, se recarga.
 *   - Al cargarse con el carrito vacío, el checkout manda al cliente a la tienda de Next.js.
 */
add_action(
	'template_redirect',
	static function () {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() || is_checkout_pay_page() ) {
			return;
		}
		if ( ! empty( $_GET['session'] ) || ! WC()->cart || ! WC()->cart->is_empty() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		wp_redirect( tatsmood_frontend_url( '/store' ), 302 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	},
	4
);

add_action(
	'wp_head',
	static function () {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}
		echo "<script>window.addEventListener('pageshow',function(e){if(e.persisted){location.reload();}});</script>\n";
	},
	1
);

/**
 * 3. La parte pública de WordPress solo se usa para el checkout. Cualquier otra página
 * (portada, tienda, productos, colecciones, carrito…) redirige a la misma ruta en Next.js.
 */
add_action(
	'template_redirect',
	static function () {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_preview() || is_customize_preview() ) {
			return;
		}
		if ( function_exists( 'is_checkout' ) && ( is_checkout() || is_account_page() ) ) {
			return; // Checkout, pago de pedidos, "pedido recibido" y Mi cuenta se quedan en WordPress.
		}
		if ( isset( $_GET['wc-api'] ) || isset( $_GET['wc-ajax'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ) ?: '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			$path = '/store';
		} elseif ( function_exists( 'is_product_category' ) && is_product_category() ) {
			$path = '/store/' . get_queried_object()->slug;
		} elseif ( is_tax( 'collection' ) ) {
			$path = '/' . get_queried_object()->slug;
		} elseif ( function_exists( 'is_product' ) && is_product() ) {
			$path = '/product/' . get_post_field( 'post_name', get_queried_object_id() );
		} elseif ( function_exists( 'is_cart' ) && is_cart() ) {
			$path = '/';
		}

		wp_redirect( tatsmood_frontend_url( $path ), 302 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	},
	20
);

/**
 * 4. Enlaces de "Seguir comprando" / "Volver a la tienda" de WooCommerce → web en Next.js.
 */
add_filter( 'woocommerce_return_to_shop_redirect', static fn() => tatsmood_frontend_url( '/store' ) );
add_filter( 'woocommerce_continue_shopping_redirect', static fn() => tatsmood_frontend_url( '/store' ) );

/**
 * 5. Envío gratis: cuando el pedido tiene envío gratis, se ocultan los demás métodos de envío
 * (el cliente no tiene que elegir pagar un envío que le sale gratis). Si hay varias opciones
 * gratuitas (p. ej. recogida en tienda), se mantienen todas.
 */
add_filter(
	'woocommerce_package_rates',
	static function ( array $rates ): array {
		$free = array_filter( $rates, static fn( $rate ) => 'free_shipping' === $rate->get_method_id() );
		return $free ?: $rates;
	},
	100
);

/*
 * Webhooks al momento. Por defecto WooCommerce no envía el aviso al guardar: lo deja en
 * cola y lo manda en la siguiente visita a WordPress (WP-Cron), y el cambio tardaba
 * 30-40 s en verse en la web. Así se envía en el mismo guardado: guardar un producto tarda
 * una décima más, a cambio de que la web (que solo vacía su caché) lo muestre enseguida.
 */
add_filter( 'woocommerce_webhook_deliver_async', '__return_false' );
