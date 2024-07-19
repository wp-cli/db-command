<?php

class WP_SQLite_Base {

	protected $unsupported_arguments = [];

	/**
	 * Tries to determine if the current site is using SQL by checking
	 * for an active sqlite integration plugin.
	 * @return false|void
	 */
	public static function get_sqlite_version() {
		// Check if there is a db.php file in the wp-content directory.
		if ( ! file_exists( ABSPATH . '/wp-content/db.php') ) {
			return false;
		}
		// If the file is found, we need to check that it is the sqlite integration plugin.
		$plugin_file = file_get_contents( ABSPATH . '/wp-content/db.php' );
		preg_match( '/define\( \'SQLITE_DB_DROPIN_VERSION\', \'([0-9.]+)\' \)/', $plugin_file, $matches );
		return isset( $matches[1] ) ? $matches[1] : false;
	}

	protected function load_dependencies() {
		$plugin_directory = $this->get_plugin_directory();
		if ( ! $plugin_directory ) {
			throw new Exception( 'Could not locate the SQLite integration plugin.' );
		}

		// Load the translator class from the plugin.
		if( ! defined( 'SQLITE_DB_DROPIN_VERSION' ) ) {
			define( 'SQLITE_DB_DROPIN_VERSION', self::get_sqlite_version() );
		}

		# A hack to add the do_action and apply_filters functions to the global namespace.
		# This is necessary because during the import WP has not been loaded, and we
		# need to define these functions to avoid fatal errors.
		if( ! function_exists( 'do_action') ) {
			function do_action(){};
			function apply_filters(){};
		}

		// We also need to selectively load the necessary classes from the plugin.
		require_once  $plugin_directory . '/php-polyfills.php';
		require_once $plugin_directory . '/constants.php';
		require_once $plugin_directory . '/wp-includes/sqlite/class-wp-sqlite-lexer.php';
		require_once $plugin_directory . '/wp-includes/sqlite/class-wp-sqlite-query-rewriter.php';
		require_once $plugin_directory . '/wp-includes/sqlite/class-wp-sqlite-translator.php';
		require_once $plugin_directory . '/wp-includes/sqlite/class-wp-sqlite-token.php';
		require_once $plugin_directory . '/wp-includes/sqlite/class-wp-sqlite-pdo-user-defined-functions.php';
	}

	/**
	 * @return string|null
	 */
	protected function get_plugin_directory() {
		$plugin_folders = [
			ABSPATH . '/wp-content/plugins/sqlite-database-integration',
			ABSPATH . '/wp-content/mu-plugins/sqlite-database-integration',
		];

		foreach ( $plugin_folders as $folder ) {
			if ( file_exists( $folder ) && is_dir( $folder ) ) {
				return $folder;
			}
		}

		return null;
	}

	protected function check_arguments( $args ) {
		if ( array_intersect_key( $args, array_flip( $this->unsupported_arguments ) ) ) {
			WP_CLI::error(
				sprintf(
					'The following arguments are not supported by SQLite exports: %s',
					implode( ', ', $this->unsupported_arguments )
				)
			);
		}
	}
}
