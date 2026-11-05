<?php
// Drop-in de WordPress: copiar UNA vez como wp-content/db.php (fuera del repositorio).
// Carga la conexión con reintentos del repositorio; si no está, WordPress conecta como siempre.
if ( is_file( __DIR__ . '/tatsmood-wp/db.php' ) ) {
	require __DIR__ . '/tatsmood-wp/db.php';
}
