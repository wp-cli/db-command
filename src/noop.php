<?php
/**
 * While using the SQLite database integration plugin for the import and export command, do_action and
 * apply_filters are used to hook into the WordPress core. These functions might not be available in
 * the context of the import and export commands of the WP-CLI. To prevent the fatal error, we can
 * define these functions as no-op functions.
 */

if ( ! function_exists( 'do_action' ) ) {
	function do_action() {} // phpcs:ignore
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters() {} // phpcs:ignore
}
