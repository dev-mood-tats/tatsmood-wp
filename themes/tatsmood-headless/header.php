<?php defined( 'ABSPATH' ) || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php // Campos a 12px: en iPhone/iPad, Safari hace zoom al enfocarlos (lo hace con menos de 16px).
	// Con maximum-scale=1 deja de hacerlo, y en iOS el usuario puede seguir ampliando con los dedos
	// (Safari ignora ese límite para el pellizco). Solo en iOS: en Android sí bloquearía el pellizco. ?>
	<script>if(/iPad|iPhone|iPod/.test(navigator.userAgent)||(navigator.platform==="MacIntel"&&navigator.maxTouchPoints>1)){document.querySelector('meta[name="viewport"]').content="width=device-width, initial-scale=1, maximum-scale=1";}</script>
	<meta name="robots" content="noindex">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="tm-header">
	<a class="tm-logo" href="<?php echo esc_url( tatsmood_theme_frontend_url( '/' ) ); ?>" aria-label="tatsmood">
		<img src="<?php echo esc_url( get_theme_file_uri( 'assets/logo.svg' ) ); ?>" alt="tatsmood" width="80" height="29">
	</a>
	<a class="tm-link" href="<?php echo esc_url( tatsmood_theme_frontend_url( '/store' ) ); ?>"><?php esc_html_e( 'Back to shop', 'tatsmood-headless' ); ?></a>
</header>
<main class="tm-main">
