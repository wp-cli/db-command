<?php

/**
 * SQLite-specific database operations for DB_Command.
 *
 * This trait provides SQLite-specific implementations for database operations
 * that would otherwise use MySQL/MariaDB binaries.
 */
trait DB_Command_SQLite {

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
	 * Get a PDO connection to the SQLite database.
	 *
	 * @return PDO|false PDO connection or false on failure.
	 */
	protected function get_sqlite_pdo() {
		$db_path = $this->get_sqlite_db_path();

		if ( ! $db_path ) {
			return false;
		}

		try {
			$pdo = new PDO( 'sqlite:' . $db_path );
			$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
			return $pdo;
		} catch ( PDOException $e ) {
			WP_CLI::debug( 'SQLite PDO connection failed: ' . $e->getMessage(), 'db' );
			return false;
		}
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

		// Create the SQLite database file.
		try {
			$pdo = new PDO( 'sqlite:' . $db_path );
			$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
			// Execute a simple query to initialize the database.
			$pdo->exec( 'CREATE TABLE IF NOT EXISTS _wpcli_test (id INTEGER)' );
			$pdo->exec( 'DROP TABLE _wpcli_test' );
		} catch ( PDOException $e ) {
			WP_CLI::error( 'Could not create SQLite database: ' . $e->getMessage() );
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

		// Recreate the SQLite database file.
		try {
			$pdo = new PDO( 'sqlite:' . $db_path );
			$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
			// Execute a simple query to initialize the database.
			$pdo->exec( 'CREATE TABLE IF NOT EXISTS _wpcli_test (id INTEGER)' );
			$pdo->exec( 'DROP TABLE _wpcli_test' );
		} catch ( PDOException $e ) {
			WP_CLI::error( 'Could not create SQLite database: ' . $e->getMessage() );
		}

		WP_CLI::success( 'Database reset.' );
	}

	/**
	 * Execute a query against the SQLite database.
	 *
	 * @param string $query SQL query to execute.
	 */
	protected function sqlite_query( $query ) {
		$pdo = $this->get_sqlite_pdo();

		if ( ! $pdo ) {
			WP_CLI::error( 'Could not connect to SQLite database.' );
		}

		try {
			$is_row_modifying_query = preg_match( '/\b(UPDATE|DELETE|INSERT|REPLACE)\b/i', $query );

			if ( $is_row_modifying_query ) {
				$stmt = $pdo->prepare( $query );
				$stmt->execute();
				$affected_rows = $stmt->rowCount();
				WP_CLI::success( "Query succeeded. Rows affected: {$affected_rows}" );
			} else {
				$stmt = $pdo->query( $query );

				if ( ! $stmt ) {
					// There was an error.
					$error_info = $pdo->errorInfo();
					WP_CLI::error( 'Query failed: ' . $error_info[2] );
				}

				// Fetch and display results.
				$results = $stmt->fetchAll( PDO::FETCH_ASSOC );

				if ( empty( $results ) ) {
					// No results to display.
					return;
				}

				// Display as a table similar to MySQL output.
				$headers = array_keys( $results[0] );
				$this->display_table( $headers, $results );
			}
		} catch ( PDOException $e ) {
			WP_CLI::error( 'Query failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Display results as a table similar to MySQL output.
	 *
	 * @param array $headers Column headers.
	 * @param array $rows    Data rows.
	 */
	protected function display_table( $headers, $rows ) {
		// Calculate column widths.
		$widths = [];
		foreach ( $headers as $header ) {
			$widths[ $header ] = strlen( $header );
		}

		foreach ( $rows as $row ) {
			foreach ( $row as $key => $value ) {
				$widths[ $key ] = max( $widths[ $key ], strlen( (string) $value ) );
			}
		}

		// Display header.
		$separator   = '+';
		$header_line = '|';
		foreach ( $headers as $header ) {
			$separator   .= str_repeat( '-', $widths[ $header ] + 2 ) . '+';
			$header_line .= ' ' . str_pad( $header, $widths[ $header ] ) . ' |';
		}

		WP_CLI::line( $separator );
		WP_CLI::line( $header_line );
		WP_CLI::line( $separator );

		// Display rows.
		foreach ( $rows as $row ) {
			$row_line = '|';
			foreach ( $headers as $header ) {
				$value     = isset( $row[ $header ] ) ? $row[ $header ] : '';
				$row_line .= ' ' . str_pad( (string) $value, $widths[ $header ] ) . ' |';
			}
			WP_CLI::line( $row_line );
		}

		WP_CLI::line( $separator );
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

		$pdo = $this->get_sqlite_pdo();
		if ( ! $pdo ) {
			WP_CLI::error( 'Could not connect to SQLite database.' );
		}

		$stdout = ( '-' === $file );

		if ( $stdout ) {
			$output = fopen( 'php://stdout', 'w' );
		} else {
			$output = fopen( $file, 'w' );
		}

		if ( ! $output ) {
			WP_CLI::error( "Could not open file for writing: {$file}" );
		}

		try {
			// Export schema and data as SQL.
			fwrite( $output, "-- SQLite database dump\n" );
			fwrite( $output, '-- Database: ' . basename( $db_path ) . "\n\n" );

			// Get all tables.
			$stmt = $pdo->query( "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name" );
			if ( ! $stmt ) {
				// There was an error.
				$error_info = $pdo->errorInfo();
				WP_CLI::error( 'Could not retrieve table list: ' . $error_info[2] );
			}
			$tables = $stmt->fetchAll( PDO::FETCH_COLUMN );

			foreach ( $tables as $table ) {
				// Escape table name for identifiers.
				$escaped_table = '"' . str_replace( '"', '""', $table ) . '"';

				// Get CREATE TABLE statement.
				$stmt = $pdo->query( "SELECT sql FROM sqlite_master WHERE type='table' AND name=" . $pdo->quote( $table ) );
				if ( ! $stmt ) {
					// There was an error.
					$error_info = $pdo->errorInfo();
					WP_CLI::error( "Could not retrieve CREATE TABLE statement for table {$escaped_table}: " . $error_info[2] );
				}
				$create_stmt = $stmt->fetchColumn();

				if ( isset( $assoc_args['add-drop-table'] ) ) {
					fwrite( $output, "DROP TABLE IF EXISTS {$escaped_table};\n" );
				}

				fwrite( $output, $create_stmt . ";\n\n" );

				// Export data.
				$stmt = $pdo->query( "SELECT * FROM {$escaped_table}" );
				if ( ! $stmt ) {
					// There was an error.
					$error_info = $pdo->errorInfo();
					WP_CLI::error( "Could not retrieve data for table {$escaped_table}: " . $error_info[2] );
				}
				$rows = $stmt->fetchAll( PDO::FETCH_ASSOC );

				foreach ( $rows as $row ) {
					$columns = array_keys( $row );
					$values  = array_map( [ $pdo, 'quote' ], array_values( $row ) );

					fwrite( $output, "INSERT INTO {$escaped_table} (" . implode( ', ', $columns ) . ') VALUES (' . implode( ', ', $values ) . ");\n" );
				}

				fwrite( $output, "\n" );
			}

			fwrite( $output, '-- Dump completed on ' . date( 'Y-m-d H:i:s' ) . "\n\n" );
		} catch ( PDOException $e ) {
			fclose( $output );
			WP_CLI::error( 'Export failed: ' . $e->getMessage() );
		}

		fclose( $output );

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
	 * @param string $file Input file path.
	 */
	protected function sqlite_import( $file ) {
		$pdo = $this->get_sqlite_pdo();

		if ( ! $pdo ) {
			WP_CLI::error( 'Could not connect to SQLite database.' );
		}

		if ( '-' === $file ) {
			$sql = stream_get_contents( STDIN );
		} else {
			if ( ! is_readable( $file ) ) {
				WP_CLI::error( sprintf( 'Import file missing or not readable: %s', $file ) );
			}
			$sql = file_get_contents( $file );
		}

		if ( false === $sql ) {
			WP_CLI::error( 'Could not read import file.' );
		}

		try {
			// Split SQL into individual statements.
			$lines = preg_split( '/;[\r\n]+/', $sql );
			if ( ! is_array( $lines ) ) {
				$lines = [];
			}
			$statements = array_filter(
				array_map(
					'trim',
					$lines
				)
			);

			$pdo->beginTransaction();

			foreach ( $statements as $statement ) {
				if ( empty( $statement ) || 0 === strpos( $statement, '--' ) ) {
					continue;
				}

				$pdo->exec( $statement );
			}

			$pdo->commit();

			WP_CLI::success( sprintf( "Imported from '%s'.", $file ) );
		} catch ( PDOException $e ) {
			$pdo->rollBack();
			WP_CLI::error( 'Import failed: ' . $e->getMessage() );
		}
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

		// Load required WordPress files if not already loaded.
		if ( ! function_exists( 'add_action' ) ) {
			$wpinc = defined( 'WPINC' ) ? WPINC : 'wp-includes';

			$required_files = [
				ABSPATH . $wpinc . '/compat.php',
				ABSPATH . $wpinc . '/plugin.php',
				ABSPATH . $wpinc . '/class-wpdb.php',
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
