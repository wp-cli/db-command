<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

$autoload = dirname( __FILE__ ) . '/vendor/autoload.php';
if ( file_exists( $autoload ) && ! class_exists( 'DB_Command' ) ) {
	require_once $autoload;
}

WP_CLI::add_command( 'db', 'DB_Command' );
