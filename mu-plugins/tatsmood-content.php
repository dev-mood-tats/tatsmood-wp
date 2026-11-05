<?php
/**
 * Plugin Name: Tatsmood – Contenidos de la web
 * Description: Todo lo que Tat edita desde WordPress para la web en Next.js: colecciones (taxonomía «Collection»: foto, fecha y lookbook), ajustes de la home y páginas de texto. Lo sirve en la API /wp-json/tatsmood/v1/ y avisa a Next.js cuando algo cambia.
 * Version:     1.0.0
 *
 * Los campos se registran aquí con código (ACF, versión gratuita), no desde el panel de ACF:
 * así viajan con el proyecto y no se pueden romper sin querer.
 *
 * Aviso a Next.js: en wp-config.php, el mismo secreto que WC_WEBHOOK_SECRET en Next.js:
 *   define( 'TATSMOOD_REVALIDATE_SECRET', '...' );
 */

defined( 'ABSPATH' ) || exit;

const TATSMOOD_LOOKBOOK_ROWS = 12;

/**
 * Plantillas de fila del lookbook: nombre y columnas (proporción de ancho de cada foto). Todas
 * las filas ocupan el mismo ancho en la web. Las mismas proporciones están en Next.js
 * (lib/collections.ts). «width»: las filas de una foto van más estrechas y centradas (% del
 * ancho del lookbook). «hidden»: de la primera versión, igual que otra; no se ofrece.
 */
function tatsmood_lookbook_layouts(): array {
	return array(
		'centrada'       => array( 'label' => 'Una centrada', 'cols' => array( 1 ), 'width' => 61.6 ),
		'ancha'          => array( 'label' => 'Una ancha', 'cols' => array( 1 ), 'width' => 74 ),
		'dos-pequenas'   => array( 'label' => 'Dos iguales', 'cols' => array( 1, 1 ) ),
		'grande-pequena' => array( 'label' => 'Grande + pequeña', 'cols' => array( 5, 3 ) ),
		'pequena-grande' => array( 'label' => 'Pequeña + grande', 'cols' => array( 3, 5 ) ),
		'tres'           => array( 'label' => 'Tres iguales', 'cols' => array( 1, 1, 1 ) ),
		'dos-medianas'   => array( 'label' => 'Dos iguales', 'cols' => array( 1, 1 ), 'hidden' => true ),
	);
}

/* -------------------------------------------------------------------------
 * 1. Campos de las colecciones (Productos → Collections)
 * ---------------------------------------------------------------------- */
add_action(
	'acf/include_fields',
	static function () {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}
		$fields = array(
			array( 'key' => 'field_tm_col_tab_general', 'label' => 'Colección', 'type' => 'tab' ),
			array( 'key' => 'field_tm_col_fecha', 'name' => 'fecha', 'label' => 'Fecha', 'type' => 'text', 'instructions' => 'Sale delante de la descripción, entre corchetes. Ej.: October, 2026' ),
			array( 'key' => 'field_tm_col_cabecera', 'name' => 'foto_cabecera', 'label' => 'Foto de cabecera', 'type' => 'image', 'return_format' => 'id', 'preview_size' => 'medium', 'instructions' => 'La foto grande de arriba de la página de la colección.' ),
			array( 'key' => 'field_tm_col_menu_help', 'label' => '', 'type' => 'message', 'message' => 'La colección tiene su página en la web en cuanto tiene foto de cabecera. Para que salga en el menú «Collections»: <a href="nav-menus.php">Apariencia → Menús</a>.' ),
			array( 'key' => 'field_tm_col_tab_home', 'label' => 'Home', 'type' => 'tab' ),
			array( 'key' => 'field_tm_col_home_help', 'label' => '', 'type' => 'message', 'message' => 'Cómo sale esta colección cuando está en la home. Para ponerla, quitarla u ordenarla en la home: menú <a href="admin.php?page=tatsmood-web">Web tatsmood</a>.' ),
			array( 'key' => 'field_tm_col_home_foto', 'name' => 'foto_home', 'label' => 'Foto en la home', 'type' => 'image', 'return_format' => 'id', 'preview_size' => 'medium', 'instructions' => 'Si se deja vacía, se usa la foto de cabecera.' ),
			array( 'key' => 'field_tm_col_home_titulo', 'name' => 'titulo_home', 'label' => 'Título en la home', 'type' => 'text', 'instructions' => 'Opcional. Si se deja vacío, se usa el nombre de la colección.' ),
		);

		acf_add_local_field_group(
			array(
				'key'      => 'group_tatsmood_collection',
				'title'    => 'Colección (web)',
				'fields'   => $fields,
				'location' => array( array( array( 'param' => 'taxonomy', 'operator' => '==', 'value' => 'collection' ) ) ),
			)
		);
	}
);

/* -------------------------------------------------------------------------
 * 2. Ajustes generales (menú «Web tatsmood» del escritorio)
 * ---------------------------------------------------------------------- */
const TATSMOOD_HOME_OPTION = 'tatsmood_home';

function tatsmood_home_settings(): array {
	$s = wp_parse_args( (array) get_option( TATSMOOD_HOME_OPTION, array() ), array( 'intro' => '', 'fondo' => 0, 'destacados' => array() ) );
	if ( ! isset( $s['colecciones'] ) ) {
		// Primera vez: las que tenían «Mostrar en la home» (primera versión), por su orden.
		$terms = get_terms( array( 'taxonomy' => 'collection', 'hide_empty' => false, 'meta_key' => 'en_home', 'meta_value' => '1' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$terms = is_array( $terms ) ? $terms : array();
		usort( $terms, static fn( $a, $b ) => (int) get_term_meta( $a->term_id, 'orden', true ) <=> (int) get_term_meta( $b->term_id, 'orden', true ) );
		$s['colecciones'] = wp_list_pluck( $terms, 'term_id' );
	}
	return $s;
}

/** Lista de ids «1,2,3» (o array) → array de enteros, sin repetidos. */
function tatsmood_id_list( $v, int $max = 0 ): array {
	$ids = array_values( array_unique( array_filter( array_map( 'absint', is_array( $v ) ? $v : explode( ',', (string) $v ) ) ) ) );
	return $max ? array_slice( $ids, 0, $max ) : $ids;
}

add_action(
	'admin_menu',
	static function () {
		add_menu_page( 'Web tatsmood', 'Web tatsmood', 'manage_woocommerce', 'tatsmood-web', 'tatsmood_render_settings_page', 'dashicons-admin-site-alt3', 56 );
	}
);

add_action(
	'admin_init',
	static function () {
		register_setting(
			'tatsmood_web',
			TATSMOOD_HOME_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => static fn( $v ) => array(
					'intro' => sanitize_textarea_field( $v['intro'] ?? '' ),
					'fondo'       => absint( $v['fondo'] ?? 0 ),
					// Productos de «Featured», en orden (como mucho 5, los que caben en la fila).
					'destacados'  => tatsmood_id_list( $v['destacados'] ?? '', 5 ),
					// Colecciones que salen en la home, en orden.
					'colecciones' => tatsmood_id_list( $v['colecciones'] ?? '' ),
				),
			)
		);
	}
);
add_filter( 'option_page_capability_tatsmood_web', static fn() => 'manage_woocommerce' );

function tatsmood_render_settings_page(): void {
	wp_enqueue_media();
	$s   = tatsmood_home_settings();
	$img = $s['fondo'] ? tatsmood_thumb_url( (int) $s['fondo'], 'medium' ) : '';
	$opt = TATSMOOD_HOME_OPTION;
	?>
	<div class="wrap">
		<h1>Web tatsmood</h1>
		<p>Dónde se edita cada parte de la web:</p>
		<ul style="list-style:disc;padding-left:20px">
			<li><strong>Colecciones</strong> (textos, fotos y lookbook): <a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=collection&post_type=product' ) ); ?>">Productos → Collections</a>.</li>
			<li><strong>Menús</strong> (Type, Collections, redes sociales y enlaces del footer): <a href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>">Apariencia → Menús</a>.</li>
			<li><strong>FAQs, Legal notice y Privacy policy</strong>: en <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>">Páginas</a>.</li>
		</ul>
		<form method="post" action="options.php">
			<?php settings_fields( 'tatsmood_web' ); ?>
			<h2>Colecciones de la home</h2>
			<p class="description">Cada una es un bloque de la home: foto grande, texto y sus productos. Arrástralas para ordenarlas (de arriba abajo). La foto y el título de cada bloque se cambian en su colección (pestaña «Home»).</p>
			<?php tatsmood_render_collections_picker( $s['colecciones'], $opt . '[colecciones]' ); ?>

			<h2 style="margin-top:40px">Featured de la home</h2>
			<p class="description">Hasta 5 productos. Arrástralos para cambiar el orden (de izquierda a derecha, como salen en la web).</p>
			<?php tatsmood_render_featured_picker( $s['destacados'], $opt . '[destacados]' ); ?>

			<h2 style="margin-top:40px">Final de la home</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="tm-intro">Texto de bienvenida</label></th>
					<td><textarea id="tm-intro" name="<?php echo esc_attr( $opt ); ?>[intro]" rows="6" class="large-text"><?php echo esc_textarea( $s['intro'] ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row">Foto de fondo</th>
					<td>
						<input type="hidden" id="tm-fondo" name="<?php echo esc_attr( $opt ); ?>[fondo]" value="<?php echo esc_attr( (string) $s['fondo'] ); ?>">
						<img id="tm-fondo-preview" alt="" src="<?php echo esc_url( (string) $img ); ?>" style="max-width:300px;height:auto;display:<?php echo $img ? 'block' : 'none'; ?>;margin-bottom:8px">
						<button type="button" class="button" id="tm-fondo-elegir">Elegir foto</button>
						<button type="button" class="button-link" id="tm-fondo-quitar" style="margin-left:8px">Quitar</button>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Guardar' ); ?>
		</form>
	</div>
	<script>
	jQuery(function ($) {
		let frame;
		$('#tm-fondo-elegir').on('click', function () {
			frame = frame || wp.media({ title: 'Foto de fondo', library: { type: 'image' }, multiple: false });
			// Se abre en la biblioteca con la foto actual ya seleccionada (y su centro de interés a mano).
			frame.off('open').on('open', function () {
				const id = parseInt($('#tm-fondo').val(), 10);
				const selection = frame.state().get('selection');
				selection.reset(id ? [wp.media.attachment(id)] : []);
				if (id) wp.media.attachment(id).fetch();
				frame.content.mode('browse');
			});
			frame.off('select').on('select', function () {
				const a = frame.state().get('selection').first().toJSON();
				$('#tm-fondo').val(a.id);
				$('#tm-fondo-preview').attr('src', a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url).show();
			});
			frame.open();
		});
		$('#tm-fondo-quitar').on('click', function () {
			$('#tm-fondo').val('');
			$('#tm-fondo-preview').hide();
		});
	});
	</script>
	<?php
}

/* -------------------------------------------------------------------------
 * 3. API para Next.js: /wp-json/tatsmood/v1/…
 * ---------------------------------------------------------------------- */
function tatsmood_image( $id ): ?array {
	$id  = absint( $id );
	$src = $id ? wp_get_attachment_image_src( $id, 'full' ) : false;
	if ( ! $src ) {
		return null;
	}
	return array(
		'url'    => $src[0],
		'width'  => (int) $src[1],
		'height' => (int) $src[2],
		'alt'    => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
		// Centro de interés (en %): la zona que siempre se ve cuando la foto se recorta.
		'focal'  => tatsmood_focal( $id ),
	);
}

function tatsmood_collection_data( WP_Term $term ): array {
	$key  = 'term_' . $term->term_id;
	$get  = static fn( $name ) => function_exists( 'get_field' ) ? get_field( $name, $key ) : get_term_meta( $term->term_id, $name, true );
	$rows = array();
	foreach ( tatsmood_lookbook_get( $term->term_id ) as $row ) {
		$images = array();
		foreach ( $row['images'] as $item ) {
			$img = tatsmood_image( $item['id'] );
			if ( $img ) {
				$img['crop'] = $item['crop'];
				$img['link'] = $item['link'];
				$images[]    = $img;
			}
		}
		if ( $images ) {
			$rows[] = array( 'layout' => $row['layout'], 'tight' => $row['tight'], 'images' => $images );
		}
	}
	$hero = tatsmood_image( $get( 'foto_cabecera' ) );
	return array(
		'slug'        => $term->slug,
		'name'        => html_entity_decode( $term->name, ENT_QUOTES ),
		'description' => trim( (string) $term->description ),
		'date'        => (string) $get( 'fecha' ),
		'homeTitle'   => (string) $get( 'titulo_home' ),
		'homeImage'   => tatsmood_image( $get( 'foto_home' ) ) ?? $hero,
		'hero'        => $hero,
		'rows'        => $rows,
		// Productos publicados de la colección, de más nuevo a más antiguo.
		'products'    => array_map(
			'intval',
			get_posts(
				array(
					'post_type'   => 'product',
					'post_status' => 'publish',
					'numberposts' => -1,
					'fields'      => 'ids',
					'orderby'     => 'date',
					'order'       => 'DESC',
					'tax_query'   => array( array( 'taxonomy' => 'collection', 'terms' => $term->term_id ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			)
		),
	);
}

/** FAQs: cada título (H2) abre un grupo y cada bloque «Detalles» es una pregunta con su respuesta. */
function tatsmood_faq_groups( string $content ): array {
	$groups = array();
	foreach ( parse_blocks( $content ) as $block ) {
		if ( 'core/heading' === $block['blockName'] ) {
			$groups[] = array( 'title' => trim( html_entity_decode( wp_strip_all_tags( render_block( $block ) ), ENT_QUOTES ) ), 'items' => array() );
		} elseif ( 'core/details' === $block['blockName'] ) {
			if ( ! $groups ) {
				$groups[] = array( 'title' => '', 'items' => array() );
			}
			preg_match( '/<summary[^>]*>(.*?)<\/summary>/s', $block['innerHTML'], $m );
			$answer = '';
			foreach ( $block['innerBlocks'] as $inner ) {
				$answer .= render_block( $inner );
			}
			$groups[ count( $groups ) - 1 ]['items'][] = array(
				'question' => trim( html_entity_decode( wp_strip_all_tags( $m[1] ?? '' ), ENT_QUOTES ) ),
				'answer'   => trim( $answer ),
			);
		}
	}
	return $groups;
}

add_action(
	'rest_api_init',
	static function () {
		$public = array( 'methods' => 'GET', 'permission_callback' => '__return_true' );

		register_rest_route(
			'tatsmood/v1',
			'/collections',
			$public + array(
				'callback' => static function () {
					$terms = get_terms( array( 'taxonomy' => 'collection', 'hide_empty' => false ) );
					$out   = array_map( 'tatsmood_collection_data', is_array( $terms ) ? $terms : array() );
					usort( $out, static fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );
					return rest_ensure_response( $out );
				},
			)
		);

		register_rest_route(
			'tatsmood/v1',
			'/home',
			$public + array(
				'callback' => static function () {
					$s = tatsmood_home_settings();
					return rest_ensure_response(
						array(
							'intro'      => $s['intro'],
							'background' => tatsmood_image( $s['fondo'] ),
							'featured'    => array_map( 'intval', (array) $s['destacados'] ),
							'collections' => array_values(
								array_filter(
									array_map(
										static function ( $id ) {
											$t = get_term( (int) $id, 'collection' );
											return $t instanceof WP_Term ? $t->slug : null;
										},
										(array) $s['colecciones']
									)
								)
							),
						)
					);
				},
			)
		);

		register_rest_route(
			'tatsmood/v1',
			'/page/(?P<slug>[a-z0-9-]+)',
			$public + array(
				'callback' => static function ( WP_REST_Request $req ) {
					$page = get_page_by_path( $req['slug'] );
					if ( ! $page || 'publish' !== $page->post_status ) {
						return new WP_Error( 'not_found', 'Página no encontrada', array( 'status' => 404 ) );
					}
					$data = array(
						'title' => html_entity_decode( get_the_title( $page ), ENT_QUOTES ),
						'html'  => do_blocks( $page->post_content ),
					);
					if ( 'faqs' === $page->post_name ) {
						$data['faqs'] = tatsmood_faq_groups( $page->post_content );
					}
					return rest_ensure_response( $data );
				},
			)
		);
	}
);

/* -------------------------------------------------------------------------
 * 4. Avisar a Next.js cuando cambia algo, para que lo muestre al momento
 *    (Next.js comprueba la firma con su WC_WEBHOOK_SECRET).
 * ---------------------------------------------------------------------- */
function tatsmood_notify_frontend(): void {
	static $sent = false;
	if ( $sent || ! defined( 'TATSMOOD_REVALIDATE_SECRET' ) || ! function_exists( 'tatsmood_frontend_url' ) ) {
		return;
	}
	$sent = true;
	$body = (string) wp_json_encode( array( 'source' => 'wordpress' ) );
	wp_remote_post(
		tatsmood_frontend_url( '/api/revalidate' ),
		array(
			'blocking' => false,
			'timeout'  => 3,
			'headers'  => array(
				'Content-Type'         => 'application/json',
				'X-Tatsmood-Signature' => base64_encode( hash_hmac( 'sha256', $body, TATSMOOD_REVALIDATE_SECRET, true ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			),
			'body'     => $body,
		)
	);
}

foreach ( array( 'saved_collection', 'delete_collection', 'saved_product_cat', 'delete_product_cat', 'update_option_' . TATSMOOD_HOME_OPTION, 'add_option_' . TATSMOOD_HOME_OPTION ) as $tatsmood_hook ) {
	add_action( $tatsmood_hook, 'tatsmood_notify_frontend' );
}
add_action(
	'save_post_page',
	static function ( $post_id ) {
		if ( ! wp_is_post_revision( $post_id ) && ! wp_is_post_autosave( $post_id ) ) {
			tatsmood_notify_frontend();
		}
	}
);

/* -------------------------------------------------------------------------
 * 5. Editores visuales: lookbook de las colecciones y Featured de la home
 *    (JS y CSS en la carpeta tatsmood-content/, junto a este archivo).
 * ---------------------------------------------------------------------- */
const TATSMOOD_LOOKBOOK_META = 'tatsmood_lookbook';
const TATSMOOD_CROPS         = array( '', '4:3', '1:1', '4:5' );

/**
 * URL de una miniatura para el escritorio. Si ese tamaño no existe en el servidor (fotos
 * subidas sin generar tamaños), usa la original.
 */
function tatsmood_thumb_url( int $id, string $size = 'medium' ): string {
	$src  = wp_get_attachment_image_src( $id, $size );
	$file = get_attached_file( $id );
	if ( $src && $file && file_exists( trailingslashit( dirname( $file ) ) . wp_basename( $src[0] ) ) ) {
		return $src[0];
	}
	return (string) wp_get_attachment_url( $id );
}

function tatsmood_asset_url( string $file ): string {
	// Ruta real de esta carpeta dentro de wp-content (vale esté donde esté: mu-plugins o el repositorio).
	return content_url( ltrim( str_replace( wp_normalize_path( WP_CONTENT_DIR ), '', wp_normalize_path( __DIR__ ) ), '/' ) . '/tatsmood-content/' . $file );
}
function tatsmood_asset_ver( string $file ): string {
	return (string) filemtime( __DIR__ . '/tatsmood-content/' . $file );
}

/** Lookbook guardado: filas [{layout, tight, images: [{id, crop}]}]. */
function tatsmood_lookbook_get( int $term_id ): array {
	$rows = json_decode( (string) get_term_meta( $term_id, TATSMOOD_LOOKBOOK_META, true ), true );
	if ( ! is_array( $rows ) ) {
		$rows = tatsmood_lookbook_from_acf( $term_id );
	}
	return tatsmood_lookbook_sanitize( $rows );
}

function tatsmood_lookbook_sanitize( $rows ): array {
	$layouts = tatsmood_lookbook_layouts();
	$out     = array();
	foreach ( (array) $rows as $row ) {
		$layout = (string) ( $row['layout'] ?? '' );
		if ( ! isset( $layouts[ $layout ] ) ) {
			continue;
		}
		$images = array();
		foreach ( array_slice( (array) ( $row['images'] ?? array() ), 0, count( $layouts[ $layout ]['cols'] ) ) as $img ) {
			$images[] = array(
				'id'   => absint( $img['id'] ?? 0 ),
				'crop' => in_array( $img['crop'] ?? '', TATSMOOD_CROPS, true ) ? $img['crop'] : '',
				'link' => tatsmood_sanitize_link( (string) ( $img['link'] ?? '' ) ),
			);
		}
		$out[] = array( 'layout' => $layout, 'tight' => ! empty( $row['tight'] ), 'images' => $images );
	}
	return $out;
}

/**
 * Enlace de una foto del lookbook: una ruta de la web («/product/…», «/yolo»)
 * o una dirección completa (https://…). Las direcciones de la propia web se guardan como ruta.
 */
function tatsmood_sanitize_link( string $link ): string {
	$link = trim( $link );
	if ( '' === $link ) {
		return '';
	}
	$front = function_exists( 'tatsmood_frontend_url' ) ? untrailingslashit( tatsmood_frontend_url( '/' ) ) : '';
	foreach ( array( $front, 'https://tatsmood.com', 'http://tatsmood.com', 'https://www.tatsmood.com' ) as $base ) {
		if ( $base && str_starts_with( $link, $base ) ) {
			$link = substr( $link, strlen( $base ) ) ?: '/';
		}
	}
	if ( str_starts_with( $link, '/' ) ) {
		return '/' . ltrim( preg_replace( '/[^A-Za-z0-9\/_\-\.\?=&#%]/', '', $link ), '/' );
	}
	return (string) esc_url_raw( preg_match( '#^https?://#', $link ) ? $link : 'https://' . $link );
}

/** Sugerencias para el campo «Enlace» del lookbook: productos, colecciones y páginas. */
function tatsmood_link_suggestions(): array {
	$out = array();
	foreach ( function_exists( 'wc_get_products' ) ? wc_get_products( array( 'status' => 'publish', 'limit' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) : array() as $product ) {
		$out[] = array( 'value' => '/product/' . $product->get_slug(), 'label' => 'Producto · ' . html_entity_decode( $product->get_name(), ENT_QUOTES ) );
	}
	$terms = get_terms( array( 'taxonomy' => 'collection', 'hide_empty' => false ) );
	foreach ( is_array( $terms ) ? $terms : array() as $term ) {
		$out[] = array( 'value' => '/' . $term->slug, 'label' => 'Colección · ' . html_entity_decode( $term->name, ENT_QUOTES ) );
	}
	$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true ) );
	foreach ( is_array( $cats ) ? $cats : array() as $term ) {
		$out[] = array( 'value' => '/store/' . $term->slug, 'label' => 'Tienda · ' . html_entity_decode( $term->name, ENT_QUOTES ) );
	}
	foreach ( get_pages() as $page ) {
		if ( (int) $page->ID !== (int) get_option( 'woocommerce_checkout_page_id' ) ) {
			$out[] = array( 'value' => '/' . get_page_uri( $page ), 'label' => 'Página · ' . html_entity_decode( $page->post_title, ENT_QUOTES ) );
		}
	}
	return $out;
}

/** Lookbooks guardados con la primera versión (campos de ACF fila_1…fila_12). */
function tatsmood_lookbook_from_acf( int $term_id ): array {
	$rows = array();
	for ( $i = 1; $i <= TATSMOOD_LOOKBOOK_ROWS; $i++ ) {
		$p      = "fila_{$i}_";
		$layout = (string) get_term_meta( $term_id, $p . 'plantilla', true );
		if ( '' === $layout ) {
			continue;
		}
		$images = array();
		for ( $f = 1; $f <= 3; $f++ ) {
			$images[] = array( 'id' => (int) get_term_meta( $term_id, $p . "foto_{$f}", true ), 'crop' => (string) get_term_meta( $term_id, $p . "recorte_{$f}", true ) );
		}
		$rows[] = array( 'layout' => $layout, 'tight' => (bool) get_term_meta( $term_id, $p . 'pegada', true ), 'images' => $images );
	}
	return $rows;
}

/** Datos para el editor: cada foto con su miniatura y proporción. */
function tatsmood_lookbook_for_editor( int $term_id ): array {
	return array_map(
		static function ( $row ) {
			$row['images'] = array_map(
				static function ( $img ) {
					$full = $img['id'] ? wp_get_attachment_image_src( $img['id'], 'full' ) : false;
					return $img + ( $full ? array( 'url' => tatsmood_thumb_url( $img['id'], 'medium_large' ), 'w' => (int) $full[1], 'h' => (int) $full[2], 'focal' => tatsmood_focal( $img['id'] ) ) : array( 'id' => 0 ) );
				},
				$row['images']
			);
			return $row;
		},
		tatsmood_lookbook_get( $term_id )
	);
}

// Editor del lookbook en «Editar etiqueta».
add_action(
	'collection_edit_form_fields',
	static function ( WP_Term $term ) {
		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_style( 'tatsmood-admin', tatsmood_asset_url( 'admin.css' ), array(), tatsmood_asset_ver( 'admin.css' ) );
		wp_enqueue_script( 'tatsmood-lookbook', tatsmood_asset_url( 'lookbook.js' ), array( 'jquery', 'jquery-ui-sortable' ), tatsmood_asset_ver( 'lookbook.js' ), true );
		wp_localize_script(
			'tatsmood-lookbook',
			'tatsmoodLookbook',
			array(
				'rows'    => tatsmood_lookbook_for_editor( $term->term_id ),
				'layouts' => tatsmood_lookbook_layouts(),
				'links'   => tatsmood_link_suggestions(),
			)
		);
		?>
		<tr class="form-field">
			<th scope="row">Lookbook</th>
			<td>
				<?php wp_nonce_field( 'tatsmood_lookbook', 'tatsmood_lookbook_nonce' ); ?>
				<input type="hidden" name="tatsmood_lookbook" id="tatsmood-lookbook-input" value="">
				<div id="tatsmood-lookbook"></div>
				<p class="description">Así se ven las fotos en la página de la colección (en escritorio). Arrastra las filas por el asa ⠿ para reordenarlas y pulsa un hueco para elegir o cambiar su foto. En «Enlace» puedes escribir o elegir a qué página lleva la foto al pulsarla (un producto, una colección, una página o una dirección externa). En móvil van todas una debajo de otra.</p>
			</td>
		</tr>
		<?php
	}
);

add_action(
	'edited_collection',
	static function ( int $term_id ) {
		if ( ! isset( $_POST['tatsmood_lookbook'], $_POST['tatsmood_lookbook_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['tatsmood_lookbook_nonce'] ), 'tatsmood_lookbook' ) ) { // phpcs:ignore
			return;
		}
		$rows = json_decode( wp_unslash( $_POST['tatsmood_lookbook'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! is_array( $rows ) ) {
			return;
		}
		update_term_meta( $term_id, TATSMOOD_LOOKBOOK_META, wp_json_encode( tatsmood_lookbook_sanitize( $rows ) ) );
		// Fuera los campos de la primera versión (ACF fila_N_…), ya no se usan.
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->termmeta} WHERE term_id = %d AND (meta_key LIKE %s OR meta_key LIKE %s)", $term_id, $wpdb->esc_like( 'fila_' ) . '%', $wpdb->esc_like( '_fila_' ) . '%' ) );
	},
	5 // Antes del aviso a Next.js (prioridad 10 en «saved_collection»).
);

/** Lista ordenable de tarjetas (ver tatsmood-content/picker.js). */
function tatsmood_render_picker( string $id, string $name, array $items, array $selected, array $cfg ): void {
	wp_enqueue_script( 'jquery-ui-sortable' );
	wp_enqueue_style( 'tatsmood-admin', tatsmood_asset_url( 'admin.css' ), array(), tatsmood_asset_ver( 'admin.css' ) );
	wp_enqueue_script( 'tatsmood-picker', tatsmood_asset_url( 'picker.js' ), array( 'jquery', 'jquery-ui-sortable' ), tatsmood_asset_ver( 'picker.js' ), true );
	static $pickers = array();
	$pickers[ $id ] = $cfg + array( 'items' => $items, 'selected' => array_map( 'intval', $selected ) );
	wp_localize_script( 'tatsmood-picker', 'tatsmoodPickers', $pickers );
	?>
	<input type="hidden" id="<?php echo esc_attr( $id ); ?>-input" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( implode( ',', $selected ) ); ?>">
	<div id="<?php echo esc_attr( $id ); ?>"></div>
	<?php
}

function tatsmood_render_featured_picker( array $selected, string $name ): void {
	$products = function_exists( 'wc_get_products' ) ? wc_get_products( array( 'status' => 'publish', 'limit' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) : array();
	$items    = array();
	foreach ( $products as $product ) {
		$items[] = array(
			'id'    => $product->get_id(),
			'name'  => html_entity_decode( $product->get_name(), ENT_QUOTES ),
			'thumb' => $product->get_image_id() ? tatsmood_thumb_url( (int) $product->get_image_id(), 'woocommerce_thumbnail' ) : '',
		);
	}
	tatsmood_render_picker( 'tatsmood-featured', $name, $items, $selected, array( 'max' => 5, 'horizontal' => true, 'addLabel' => 'Añadir un producto…' ) );
}

function tatsmood_render_collections_picker( array $selected, string $name ): void {
	$terms = get_terms( array( 'taxonomy' => 'collection', 'hide_empty' => false, 'orderby' => 'name' ) );
	$items = array();
	foreach ( is_array( $terms ) ? $terms : array() as $term ) {
		$key     = 'term_' . $term->term_id;
		$img     = (int) ( function_exists( 'get_field' ) ? ( get_field( 'foto_home', $key ) ?: get_field( 'foto_cabecera', $key ) ) : 0 );
		$title   = function_exists( 'get_field' ) ? (string) get_field( 'titulo_home', $key ) : '';
		$items[] = array(
			'id'    => $term->term_id,
			'name'  => html_entity_decode( $title ?: $term->name, ENT_QUOTES ),
			'note'  => $img ? sprintf( '%d productos', $term->count ) : sprintf( '%d productos · sin foto: añádela en su colección', $term->count ),
			'thumb' => $img ? tatsmood_thumb_url( $img, 'medium' ) : '',
		);
	}
	tatsmood_render_picker( 'tatsmood-home-collections', $name, $items, $selected, array( 'max' => 0, 'horizontal' => false, 'addLabel' => 'Añadir una colección a la home…' ) );
}

/* -------------------------------------------------------------------------
 * 6. Centro de interés de las fotos (como en Shopify)
 *    En Medios, al abrir una foto, se pulsa sobre la zona que tiene que verse siempre.
 *    La web la usa al recortar la foto (cabeceras, home, lookbook…) en cualquier pantalla.
 * ---------------------------------------------------------------------- */
const TATSMOOD_FOCAL_META = 'tatsmood_focal';

/** [x, y] en %, por defecto el centro. */
function tatsmood_focal( int $id ): array {
	$v = get_post_meta( $id, TATSMOOD_FOCAL_META, true );
	if ( is_string( $v ) && preg_match( '/^(\d{1,3}(?:\.\d+)?),(\d{1,3}(?:\.\d+)?)$/', $v, $m ) ) {
		return array( min( 100.0, (float) $m[1] ), min( 100.0, (float) $m[2] ) );
	}
	return array( 50.0, 50.0 );
}

add_filter(
	'attachment_fields_to_edit',
	static function ( array $fields, WP_Post $post ) {
		if ( ! wp_attachment_is_image( $post ) ) {
			return $fields;
		}
		[ $x, $y ] = tatsmood_focal( $post->ID );
		$src       = tatsmood_thumb_url( $post->ID, 'medium_large' );
		$name      = "attachments[{$post->ID}][" . TATSMOOD_FOCAL_META . ']';
		$fields[ TATSMOOD_FOCAL_META ] = array(
			'label' => 'Centro de interés',
			'input' => 'html',
			'html'  => sprintf(
				'<span class="tm-focal" data-tm-focal><img src="%1$s" alt=""><span class="tm-focal-dot" style="left:%2$s%%;top:%3$s%%"></span></span>'
				. '<input type="hidden" name="%4$s" id="%4$s" value="%2$s,%3$s">'
				. '<span class="tm-focal-help">Pulsa sobre la zona de la foto que tiene que verse siempre (por ejemplo, la cara). Cuando la web recorta la foto, en ordenador o en móvil, la mantiene a la vista.</span>',
				esc_url( (string) $src ),
				esc_attr( (string) $x ),
				esc_attr( (string) $y ),
				esc_attr( $name )
			),
		);
		return $fields;
	},
	10,
	2
);

add_filter(
	'attachment_fields_to_save',
	static function ( array $post, array $attachment ) {
		if ( isset( $attachment[ TATSMOOD_FOCAL_META ] ) && preg_match( '/^\d{1,3}(\.\d+)?,\d{1,3}(\.\d+)?$/', (string) $attachment[ TATSMOOD_FOCAL_META ] ) ) {
			update_post_meta( (int) $post['ID'], TATSMOOD_FOCAL_META, $attachment[ TATSMOOD_FOCAL_META ] );
			tatsmood_notify_frontend();
		}
		return $post;
	},
	10,
	2
);

// El selector funciona en todo el escritorio (biblioteca de Medios, ventana de elegir foto…).
add_action(
	'admin_enqueue_scripts',
	static function () {
		wp_enqueue_style( 'tatsmood-admin', tatsmood_asset_url( 'admin.css' ), array(), tatsmood_asset_ver( 'admin.css' ) );
		wp_enqueue_script( 'tatsmood-focal', tatsmood_asset_url( 'focal.js' ), array( 'jquery' ), tatsmood_asset_ver( 'focal.js' ), true );
	}
);

/* -------------------------------------------------------------------------
 * 7. Menús de la web (Apariencia → Menús)
 *    Cuatro ubicaciones; los elementos pueden ser colecciones, tipos
 *    (categorías), páginas o enlaces personalizados. La API los da ya con la ruta de
 *    Next.js (/yolo, /store/…, /faqs…).
 * ---------------------------------------------------------------------- */
function tatsmood_menu_locations(): array {
	return array(
		'tienda'      => 'Type (menú y final de la home)',
		'colecciones' => 'Collections (menú y final de la home)',
		'redes'       => 'Redes sociales (menú y footer)',
		'footer'      => 'Footer (enlaces de la izquierda)',
	);
}

add_action( 'after_setup_theme', static fn() => register_nav_menus( tatsmood_menu_locations() ), 20 );

/** Ruta en Next.js de un elemento de menú (o la URL tal cual si es externa). */
function tatsmood_menu_item_href( WP_Post $item ): array {
	if ( 'taxonomy' === $item->type ) {
		$term = get_term( (int) $item->object_id, $item->object );
		if ( $term instanceof WP_Term ) {
			if ( 'collection' === $item->object ) {
				return array( '/' . $term->slug, false );
			}
			if ( 'product_cat' === $item->object ) {
				return array( '/store/' . $term->slug, false );
			}
		}
	}
	if ( 'post_type' === $item->type ) {
		$post = get_post( (int) $item->object_id );
		if ( $post && 'product' === $post->post_type ) {
			return array( '/product/' . $post->post_name, false );
		}
		if ( $post ) {
			return array( '/' . get_page_uri( $post ), false );
		}
	}
	$url = (string) $item->url;
	foreach ( array( home_url(), function_exists( 'tatsmood_frontend_url' ) ? untrailingslashit( tatsmood_frontend_url( '/' ) ) : '' ) as $base ) {
		if ( $base && str_starts_with( $url, $base ) ) {
			return array( '/' . ltrim( substr( $url, strlen( $base ) ), '/' ), false );
		}
	}
	return str_starts_with( $url, '/' ) ? array( $url, false ) : array( $url, true );
}

function tatsmood_menu_items( string $location ): array {
	$locations = get_nav_menu_locations();
	$items     = empty( $locations[ $location ] ) ? array() : (array) wp_get_nav_menu_items( $locations[ $location ] );
	$out       = array();
	foreach ( $items as $item ) {
		if ( (int) $item->menu_item_parent ) {
			continue; // Sin submenús.
		}
		[ $href, $external ] = tatsmood_menu_item_href( $item );
		$out[]               = array( 'label' => html_entity_decode( $item->title, ENT_QUOTES ), 'href' => $href, 'external' => $external );
	}
	return $out;
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'tatsmood/v1',
			'/menus',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => static function () {
					$out = array();
					foreach ( array_keys( tatsmood_menu_locations() ) as $location ) {
						$out[ $location ] = tatsmood_menu_items( $location );
					}
					return rest_ensure_response( $out );
				},
			)
		);
	}
);

add_action( 'wp_update_nav_menu', 'tatsmood_notify_frontend' );
add_action( 'wp_delete_nav_menu', 'tatsmood_notify_frontend' );

/**
 * Primera vez: crea los cuatro menús con lo que había escrito en Next.js, para que Tat
 * parta de la web tal y como está.
 */
add_action(
	'admin_init',
	static function () {
		if ( get_option( 'tatsmood_menus_created' ) || ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}
		$term_item = static fn( string $tax, string $slug, string $title = '' ) => ( $t = get_term_by( 'slug', $slug, $tax ) ) ? array(
			'menu-item-type'      => 'taxonomy',
			'menu-item-object'    => $tax,
			'menu-item-object-id' => $t->term_id,
			'menu-item-title'     => $title,
			'menu-item-status'    => 'publish',
		) : null;
		$page_item = static fn( string $slug, string $title ) => ( $p = get_page_by_path( $slug ) ) ? array(
			'menu-item-type'      => 'post_type',
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $p->ID,
			'menu-item-title'     => $title,
			'menu-item-status'    => 'publish',
		) : null;
		$link      = static fn( string $title, string $url ) => array(
			'menu-item-type'   => 'custom',
			'menu-item-title'  => $title,
			'menu-item-url'    => $url,
			'menu-item-status' => 'publish',
		);
		$shop      = function_exists( 'tatsmood_frontend_url' ) ? tatsmood_frontend_url( '/store' ) : home_url( '/store' );

		$menus = array(
			'tienda'      => array( 'Type', array( $link( 'Shop all', $shop ), $term_item( 'product_cat', 't-shirts', 'T-shirts' ), $term_item( 'product_cat', 'sweatshirts' ), $term_item( 'product_cat', 'accesories' ), $term_item( 'product_cat', 'mugs' ), $term_item( 'product_cat', 'prints' ) ) ),
			'colecciones' => array( 'Collections', array( $term_item( 'collection', 'hoodie-capsule' ), $term_item( 'collection', 'yolo' ), $term_item( 'collection', 'gracias-deluxe' ), $term_item( 'collection', 'gracias' ), $term_item( 'collection', 'hopecore' ), $term_item( 'collection', 'nature-is-home' ), $term_item( 'collection', 'connected' ) ) ),
			'redes'       => array( 'Redes sociales', array( $link( 'Instagram', 'https://www.instagram.com/tatsmood/' ), $link( 'TikTok', 'https://www.tiktok.com/@tatsmood' ) ) ),
			'footer'      => array( 'Footer', array( $page_item( 'legal-notice', 'Legal Notice' ), $page_item( 'privacy-policy', 'Privacy policy' ), $page_item( 'faqs', 'FAQs' ) ) ),
		);
		$locations = (array) get_theme_mod( 'nav_menu_locations', array() );
		foreach ( $menus as $location => [ $name, $items ] ) {
			if ( ! empty( $locations[ $location ] ) ) {
				continue;
			}
			$existing = wp_get_nav_menu_object( $name );
			$menu_id  = $existing ? $existing->term_id : wp_create_nav_menu( $name );
			if ( is_wp_error( $menu_id ) ) {
				continue;
			}
			if ( ! $existing ) {
				foreach ( array_filter( $items ) as $item ) {
					wp_update_nav_menu_item( $menu_id, 0, $item );
				}
			}
			$locations[ $location ] = $menu_id;
		}
		set_theme_mod( 'nav_menu_locations', $locations );
		update_option( 'tatsmood_menus_created', 1 );
	}
);

/* -------------------------------------------------------------------------
 * 8. Centro de interés también en las fotos de productos
 *    (API de WooCommerce: cada foto de «images» lleva «focal»; Medios: campo
 *    «tatsmood_focal» en /wp/v2/media, que usa Next.js para las fotos de ACF).
 * ---------------------------------------------------------------------- */
add_filter(
	'woocommerce_rest_prepare_product_object',
	static function ( $response ) {
		if ( $response instanceof WP_REST_Response && ! empty( $response->data['images'] ) && is_array( $response->data['images'] ) ) {
			foreach ( $response->data['images'] as &$img ) {
				if ( ! empty( $img['id'] ) ) {
					$img['focal'] = tatsmood_focal( (int) $img['id'] );
				}
			}
			unset( $img );
		}
		return $response;
	}
);

add_action(
	'rest_api_init',
	static function () {
		register_rest_field(
			'attachment',
			TATSMOOD_FOCAL_META,
			array( 'get_callback' => static fn( $post ) => tatsmood_focal( (int) $post['id'] ) )
		);
	}
);

// Al cambiar el centro de interés de una foto de producto, el catálogo también se refresca
// (tatsmood_notify_frontend vacía «content» y «catalog»).

/**
 * Admin: si falta en disco el tamaño reducido de una foto (vista previa de ACF,
 * biblioteca de medios…), se usa la original para que nunca salga en blanco.
 */
function tatsmood_size_file_exists( int $id, string $url ): bool {
	$file = get_attached_file( $id );
	return $file && file_exists( trailingslashit( dirname( $file ) ) . wp_basename( $url ) );
}
add_filter(
	'wp_get_attachment_image_src',
	static function ( $image, $id, $size ) {
		if ( ! is_admin() || ! $image || 'full' === $size || tatsmood_size_file_exists( (int) $id, $image[0] ) ) {
			return $image;
		}
		$full = wp_get_attachment_url( $id );
		return $full ? array( $full, $image[1], $image[2], false ) : $image;
	},
	10,
	3
);
add_filter(
	'wp_prepare_attachment_for_js',
	static function ( $data, $attachment ) {
		if ( empty( $data['sizes'] ) || empty( $data['url'] ) ) {
			return $data;
		}
		foreach ( $data['sizes'] as $name => $size ) {
			if ( 'full' !== $name && ! empty( $size['url'] ) && ! tatsmood_size_file_exists( (int) $attachment->ID, $size['url'] ) ) {
				$data['sizes'][ $name ]['url'] = $data['url'];
			}
		}
		return $data;
	},
	10,
	2
);
