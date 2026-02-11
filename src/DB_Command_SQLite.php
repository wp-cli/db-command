<?php

use WP_CLI\Formatter;
use WP_CLI\Utils;

/**
 * SQLite-specific database operations for DB_Command.
 *
 * This trait provides SQLite-specific implementations for database operations
 * that would otherwise use MySQL/MariaDB binaries.
 */
trait DB_Command_SQLite {

	/**
	 * Check if sqlite3 CLI binary is available.
	 *
	 * @return bool True if sqlite3 is available, false otherwise.
	 */
	protected function is_sqlite3_available() {
		static $available = null;

		if ( null === $available ) {
			$result    = \WP_CLI\Process::create( 'which sqlite3', null, null )->run();
			$available = 0 === $result->return_code;
		}

		return $available;
	}

	/**
	 * Check if SQLite is being used.
	 *
	 * @return bool True if SQLite is detected, false otherwise.
	 */
	protected function is_sqlite() {
		// Check if DB_ENGINE constant is defined and set to 'sqlite'.
		if ( defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ) {
			return true;
		}

		// Check if the SQLite drop-in is loaded by looking for SQLITE_DB_DROPIN_VERSION constant.
		if ( defined( 'SQLITE_DB_DROPIN_VERSION' ) ) {
			return true;
		}

		// Check if db.php drop-in exists and contains SQLite markers.
		$wp_content_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
		$db_dropin_path = $wp_content_dir . '/db.php';

		if ( file_exists( $db_dropin_path ) ) {
			$db_dropin_contents = file_get_contents( $db_dropin_path );
			if ( false !== $db_dropin_contents && false !== strpos( $db_dropin_contents, 'SQLITE_DB_DROPIN_VERSION' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the path to the SQLite database file.
	 *
	 * @return string|false Path to SQLite database file, or false if not found.
	 */
	protected function get_sqlite_db_path() {
		// Check for FQDB constant (fully qualified database path).
		if ( defined( 'FQDB' ) ) {
			return FQDB;
		}

		// Check for FQDBDIR and construct path.
		$db_dir  = defined( 'FQDBDIR' ) ? FQDBDIR : ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content' ) . '/database';
		$db_file = defined( 'DB_FILE' ) ? DB_FILE : '.ht.sqlite';

		$db_path = rtrim( $db_dir, '/' ) . '/' . ltrim( $db_file, '/' );

		// If the file exists, return it.
		if ( file_exists( $db_path ) ) {
			return $db_path;
		}

		// Try alternative common locations.
		$wp_content_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
		$alt_paths      = [
			$wp_content_dir . '/database/.ht.sqlite',
			$wp_content_dir . '/.ht.sqlite',
			ABSPATH . '.ht.sqlite',
		];

		foreach ( $alt_paths as $alt_path ) {
			if ( file_exists( $alt_path ) ) {
				return $alt_path;
			}
		}

		// Return the default expected path even if it doesn't exist yet.
		return $db_path;
	}

	/**
	 * Create SQLite database.
	 */
	protected function sqlite_create() {
		$db_path = $this->get_sqlite_db_path();
		if ( ! $db_path ) {
			WP_CLI::error( 'Could not determine the database path.' );
		}
		$db_dir = dirname( $db_path );

		// Create directory if it doesn't exist.
		if ( ! is_dir( $db_dir ) ) {
			if ( ! mkdir( $db_dir, 0755, true ) ) {
				WP_CLI::error( "Could not create directory: {$db_dir}" );
			}
		}

		// Check if database already exists.
		if ( file_exists( $db_path ) ) {
			WP_CLI::error( 'Database already exists.' );
		}

		// Check if sqlite3 binary is available.
		if ( ! $this->is_sqlite3_available() ) {
			WP_CLI::error( 'The sqlite3 CLI binary is required but not found. Please install SQLite3.' );
		}

		// Use Utils\esc_cmd to properly escape the command and arguments.
		$command = Utils\esc_cmd( 'sqlite3 %s %s', $db_path, '' );

		WP_CLI::debug( "Running shell command: {$command}", 'db' );

		$result = \WP_CLI\Process::create( $command, null, null )->run();

		if ( 0 !== $result->return_code ) {
			WP_CLI::error( 'Could not create database' );
		}

		WP_CLI::success( 'Database created.' );
	}

	/**
	 * Drop SQLite database.
	 */
	protected function sqlite_drop() {
		$db_path = $this->get_sqlite_db_path();

		if ( ! $db_path ) {
			WP_CLI::error( 'Could not determine the database path.' );
		}

		if ( ! file_exists( $db_path ) ) {
			WP_CLI::error( 'Database does not exist.' );
		}

		if ( ! unlink( $db_path ) ) {
			WP_CLI::error( "Could not delete database file: {$db_path}" );
		}

		WP_CLI::success( 'Database dropped.' );
	}

	/**
	 * Reset SQLite database.
	 */
	protected function sqlite_reset() {
		$db_path = $this->get_sqlite_db_path();

		if ( ! $db_path ) {
			WP_CLI::error( 'Could not determine the database path.' );
		}

		// Delete if exists.
		if ( file_exists( $db_path ) ) {
			if ( ! unlink( $db_path ) ) {
				WP_CLI::error( "Could not delete database file: {$db_path}" );
			}
		}

		// Create directory if needed.
		$db_dir = dirname( $db_path );
		if ( ! is_dir( $db_dir ) ) {
			if ( ! mkdir( $db_dir, 0755, true ) ) {
				WP_CLI::error( "Could not create directory: {$db_dir}" );
			}
		}

		// Check if sqlite3 binary is available.
		if ( ! $this->is_sqlite3_available() ) {
			WP_CLI::error( 'The sqlite3 CLI binary is required but not found. Please install SQLite3.' );
		}

		// Use Utils\esc_cmd to properly escape the command and arguments.
		$command = Utils\esc_cmd( 'sqlite3 %s %s', $db_path, '' );

		WP_CLI::debug( "Running shell command: {$command}", 'db' );

		$result = \WP_CLI\Process::create( $command, null, null )->run();

		if ( 0 !== $result->return_code ) {
			WP_CLI::error( 'Could not create database' );
		}

		WP_CLI::success( 'Database reset.' );
	}

	/**
	 * Execute a query against the SQLite database.
	 *
	 * @param string $query      SQL query to execute.
	 * @param array  $assoc_args Associative arguments.
	 */
	protected function sqlite_query( $query, $assoc_args = [] ) {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! $wpdb instanceof \WP_SQLite_DB ) {
			WP_CLI::error( 'SQLite database not available.' );
		}

		$skip_column_names = Utils\get_flag_value( $assoc_args, 'skip-column-names', false );

		try {
			$is_row_modifying_query = preg_match( '/\b(UPDATE|DELETE|INSERT|REPLACE)\b/i', $query );

			if ( $is_row_modifying_query ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$affected_rows = $wpdb->query( $query );
				if ( false === $affected_rows ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
					WP_CLI::error( 'Query failed: ' . strip_tags( $wpdb->last_error ) );
				}
				WP_CLI::success( "Query succeeded. Rows affected: {$affected_rows}" );
			} else {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$results = $wpdb->get_results( $query, ARRAY_A );

				if ( $wpdb->last_error ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
					WP_CLI::error( 'Query failed: ' . strip_tags( $wpdb->last_error ) );
				}

				if ( empty( $results ) ) {
					// No results to display.
					return;
				}

				// Display results using the Formatter class.
				$headers = array_keys( $results[0] );
				$this->display_query_results( $headers, $results, $skip_column_names );
			}
		} catch ( Exception $e ) {
			WP_CLI::error( 'Query failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Display query results using the Formatter class.
	 *
	 * @param array $headers          Column headers.
	 * @param array $rows             Data rows.
	 * @param bool  $skip_column_names Whether to skip displaying column names.
	 */
	protected function display_query_results( $headers, $rows, $skip_column_names = false ) {
		if ( $skip_column_names ) {
			// Display rows without headers - just the values.
			foreach ( $rows as $row ) {
				WP_CLI::line( implode( "\t", array_values( $row ) ) );
			}
		} else {
			// Use the Formatter class to display results as a table.
			$assoc_args = [];
			$formatter  = new Formatter( $assoc_args, $headers );
			$formatter->display_items( $rows );
		}
	}

	/**
	 * Export SQLite database.
	 *
	 * @param string $file       Output file path.
	 * @param array  $assoc_args Associative arguments.
	 */
	protected function sqlite_export( $file, $assoc_args ) {
		$db_path = $this->get_sqlite_db_path();

		if ( ! $db_path ) {
			WP_CLI::error( 'Could not determine the database path.' );
		}

		if ( ! file_exists( $db_path ) ) {
			WP_CLI::error( 'Database does not exist.' );
		}

		$temp_db   = tempnam( sys_get_temp_dir(), 'temp.db' );
		$export_db = tempnam( sys_get_temp_dir(), 'export.db' );

		copy( $db_path, $temp_db );

		$exclude_tables = [];

		// When passing --tables, exclude everything *except* the tables requested.
		if ( isset( $assoc_args['tables'] ) ) {
			$include_tables = explode( ',', trim( $assoc_args['tables'], ',' ) );
			unset( $assoc_args['tables'] );

			// Use the sqlite3 binary to fetch all table names
			$query = "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';";

			// Build the command safely
			$command = 'sqlite3 ' . escapeshellarg( $temp_db ) . ' ' . escapeshellarg( $query );

			WP_CLI::debug( "Running shell command: {$command}", 'db' );

			$result = \WP_CLI\Process::create( $command, null, null )->run();

			if ( 0 !== $result->return_code ) {
				WP_CLI::error( 'Could not export database' );
			}

			$all_tables = explode( "\n", $result->stdout );

			$exclude_tables = array_diff( $all_tables, $include_tables );
		}

		if ( isset( $assoc_args['exclude_tables'] ) ) {
			$exclude_tables = explode( ',', trim( $assoc_args['exclude_tables'], ',' ) );
			unset( $assoc_args['exclude_tables'] );
		}

		// Always exclude this one created by the drop-in.
		$exclude_tables[] = '_mysql_data_types_cache';

		$exclude_tables = array_unique( array_filter( $exclude_tables ) );

		// Build DROP TABLE statements with safely-escaped identifiers.
		$drop_statements = array();
		foreach ( $exclude_tables as $table ) {
			// Escape double quotes within the table name and wrap it in double quotes.
			$escaped_identifier = '"' . str_replace( '"', '""', $table ) . '"';
			$drop_statements[] = sprintf( 'DROP TABLE %s;', $escaped_identifier );
		}

		if ( ! empty( $drop_statements ) ) {
			// Build the sqlite3 command with properly escaped shell arguments.
			$args         = array_merge( array( 'sqlite3', $temp_db ), $drop_statements );
			$placeholders = array_fill( 0, count( $args ), '%s' );
			$command      = Utils\esc_cmd( implode( ' ', $placeholders ), ...$args );

			WP_CLI::debug( "Running shell command: {$command}", 'db' );

			$result = \WP_CLI\Process::create( $command, null, null )->run();

			if ( 0 !== $result->return_code ) {
				WP_CLI::error( 'Could not export database' );
			}
		}

		// Dump the database to the export file.
		$command = Utils\esc_cmd( 'sqlite3 %s .dump > %s', $temp_db, $export_db );

		WP_CLI::debug( "Running shell command: {$command}", 'db' );

		$result = \WP_CLI\Process::create( $command, null, null )->run();

		if ( 0 !== $result->return_code ) {
			WP_CLI::error( 'Could not export database' );
		}

		$stdout = ( '-' === $file );

		if ( $stdout ) {
			readfile( $export_db );
			unlink( $export_db );
		} else {
			if ( ! @rename( $export_db, $file ) ) {
				// Clean up temporary files and surface a clear error if the export cannot be moved.
				if ( file_exists( $export_db ) ) {
					unlink( $export_db );
				}
				if ( file_exists( $temp_db ) ) {
					unlink( $temp_db );
				}
				WP_CLI::error( "Could not move exported database to '{$file}'. Please check that the path is writable and on the same filesystem." );
			}
		}
		unlink( $temp_db );

		if ( ! $stdout ) {
			if ( isset( $assoc_args['porcelain'] ) ) {
				WP_CLI::line( $file );
			} else {
				WP_CLI::success( "Exported to '{$file}'." );
			}
		}
	}

	/**
	 * Import SQL into SQLite database.
	 *
	 * @param string $file       Input file path.
	 * @param array  $assoc_args Associative arguments.
	 */
	protected function sqlite_import( $file, $assoc_args ) {
		$db_path = $this->get_sqlite_db_path();

		if ( ! $db_path ) {
			WP_CLI::error( 'Could not determine the database path.' );
		}

		if ( ! file_exists( $db_path ) ) {
			WP_CLI::error( 'Database does not exist.' );
		}

		$contents = (string) file_get_contents( $file );

		if ( '-' === $file ) {
			$contents = file_get_contents( 'php://stdin' );
			if ( false === $contents ) {
				WP_CLI::error( 'Failed to read from stdin.' );
			}

			$file = 'STDIN';
		} elseif ( ! is_readable( $file ) ) {
				WP_CLI::error( sprintf( 'Import file missing or not readable: %s', $file ) );
		}

		// Ignore errors about unique constraints and existing indexes.
		$contents = str_replace( 'INSERT INTO', 'INSERT OR IGNORE INTO', $contents );
		$contents = str_replace( 'CREATE INDEX "', 'CREATE INDEX IF NOT EXISTS "', $contents );
		$contents = str_replace( 'CREATE UNIQUE INDEX "', 'CREATE UNIQUE INDEX IF NOT EXISTS "', $contents );

		// Build sqlite3 command as an argument array to avoid shell injection.
		$command = array( 'sqlite3' );

		if ( ! Utils\get_flag_value( $assoc_args, 'skip-optimization' ) ) {
			$command[] = '-cmd';
			$command[] = 'PRAGMA foreign_keys=OFF;';
			$command[] = '-cmd';
			$command[] = 'PRAGMA ignore_check_constraints=ON;';
			$command[] = '-cmd';
			$command[] = 'PRAGMA synchronous=OFF;';
			$command[] = '-cmd';
			$command[] = 'PRAGMA journal_mode=MEMORY;';
		}

		// Add database path as final argument.
		$command[] = $db_path;

		// For debugging, show a safely escaped shell-like representation.
		$debug_command = implode( ' ', array_map( 'escapeshellarg', $command ) );
		WP_CLI::debug( "Running shell command: {$debug_command}", 'db' );

		// Pass the SQL contents via stdin instead of using shell redirection.
		$result = \WP_CLI\Process::create( $command, null, null, null, array( 'stdin' => $contents ) )->run();

		if ( 0 !== $result->return_code ) {
			WP_CLI::error( 'Could not import database.' );
		}

		WP_CLI::success( sprintf( "Imported from '%s'.", $file ) );
	}

	/**
	 * Get SQLite database size.
	 *
	 * @return int Database file size in bytes, or 0 if not found.
	 */
	protected function sqlite_size() {
		$db_path = $this->get_sqlite_db_path();

		if ( ! $db_path || ! file_exists( $db_path ) ) {
			return 0;
		}

		$size = filesize( $db_path );
		if ( false === $size ) {
			return 0;
		}

		return $size;
	}

	/**
	 * Load WordPress db.php drop-in if SQLite is detected.
	 *
	 * This should be called early in commands that run at after_wp_config_load.
	 */
	protected function maybe_load_sqlite_dropin() {
		if ( ! $this->is_sqlite() ) {
			return;
		}

		// Check if already loaded.
		if ( defined( 'SQLITE_DB_DROPIN_VERSION' ) ) {
			return;
		}

		$wp_content_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
		$db_dropin_path = $wp_content_dir . '/db.php';

		if ( ! file_exists( $db_dropin_path ) ) {
			return;
		}

		// Constants used in wp-includes/functions.php
		if ( ! defined( 'WPINC' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
			define( 'WPINC', 'wp-includes' );
		}

		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
		}

		// Load required WordPress files if not already loaded.
		if ( ! function_exists( 'add_action' ) ) {
			$required_files = [
				ABSPATH . WPINC . '/compat.php',
				ABSPATH . WPINC . '/plugin.php',
				// Defines `wp_debug_backtrace_summary()` as used by wpdb.
				ABSPATH . WPINC . '/functions.php',
				ABSPATH . WPINC . '/class-wpdb.php',
			];

			foreach ( $required_files as $required_file ) {
				if ( file_exists( $required_file ) ) {
					require_once $required_file;
				}
			}
		}

		// Load the db.php drop-in.
		require_once $db_dropin_path;
	}
}
