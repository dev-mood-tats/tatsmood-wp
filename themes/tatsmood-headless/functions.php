<?php
/**
 * tatsmood headless — tema mínimo para el checkout de WooCommerce.
 *
 * La web pública vive en Next.js (ver mu-plugins/tatsmood-headless.php, que redirige allí
 * todo lo demás). Este tema solo pinta el checkout y la página de pagar un pedido con el
 * mismo aspecto que la web: cabecera con el logo, Inter Tight a 12px, negro sobre blanco.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'woocommerce' );
		add_theme_support( 'html5', array( 'style', 'script' ) );
	}
);

/** URL de la web en Next.js (la define el mu-plugin; si no estuviera, la del propio WP). */
function tatsmood_theme_frontend_url( string $path = '/' ): string {
	return function_exists( 'tatsmood_frontend_url' ) ? tatsmood_frontend_url( $path ) : home_url( $path );
}

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_enqueue_style( 'tatsmood-inter-tight', 'https://fonts.googleapis.com/css2?family=Inter+Tight:wght@300;400;500&display=swap', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		$ver = (string) filemtime( get_theme_file_path( 'assets/checkout.css' ) );
		wp_enqueue_style( 'tatsmood-checkout', get_theme_file_uri( 'assets/checkout.css' ), array(), $ver );
		// Packlink PRO: su selector de punto de recogida, dentro del checkout (ver el archivo).
		wp_enqueue_script( 'tatsmood-checkout', get_theme_file_uri( 'assets/checkout.js' ), array(), (string) filemtime( get_theme_file_path( 'assets/checkout.js' ) ), true );

		// Fuera lo que la web no usa (estilos de bloques genéricos, emojis…).
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'classic-theme-styles' );
		wp_dequeue_style( 'global-styles' );
	},
	20
);

remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );

/**
 * Formulario de tarjeta de Stripe (va en un iframe, así que no le llega el CSS del tema):
 * se le pasan la tipografía y los colores de la web.
 */
add_filter(
	'wc_stripe_upe_params',
	static function ( $params ) {
		$appearance = (object) array(
			'theme'     => 'stripe',
			'variables' => array(
				'fontFamily'      => '"Inter Tight", sans-serif',
				'fontSizeBase'    => '12px',
				'fontWeightNormal' => '300',
				'colorText'       => '#000000',
				'colorPrimary'    => '#000000',
				'colorDanger'     => '#000000',
				'borderRadius'    => '0px',
				'spacingUnit'     => '4px',
			),
			'rules'     => array(
				'.Input' => array(
					'border'    => '1px solid #e9e5e5',
					'boxShadow' => 'none',
				),
				'.Input:focus' => array(
					'border'    => '1px solid #000000',
					'boxShadow' => 'none',
				),
				'.Tab'   => array(
					'border'    => '1px solid #e9e5e5',
					'boxShadow' => 'none',
				),
			),
		);
		$params['blocksAppearance'] = $appearance;
		$params['appearance']       = $appearance;
		return $params;
	}
);

/**
 * Apariencia simple: quedan «Temas», «Menús» (los menús de la web) y el «Editor de archivos
 * de temas» (para tocar los PHP/CSS del tema). Fuera Patrones, Fuentes y Personalizar (el
 * tema no tiene opciones), también el «Personalizar» de la barra superior.
 */
add_action(
	'admin_menu',
	static function () {
		global $submenu;
		foreach ( $submenu['themes.php'] ?? array() as $i => $item ) {
			$slug = (string) ( $item[2] ?? '' );
			foreach ( array( 'site-editor.php', 'font-library.php', 'customize.php', 'edit.php?post_type=wp_block' ) as $hide ) {
				if ( str_starts_with( $slug, $hide ) ) {
					unset( $submenu['themes.php'][ $i ] );
				}
			}
		}
	},
	999
);

add_action(
	'admin_bar_menu',
	static function ( $bar ) {
		$bar->remove_node( 'customize' );
	},
	999
);

require_once __DIR__ . '/emails.php';
