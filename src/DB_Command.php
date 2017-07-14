<?php

use \WP_CLI\Utils;

/**
 * Perform basic database operations using credentials stored in wp-config.php
 *
 * ## EXAMPLES
 *
 *     # Create a new database.
 *     $ wp db create
 *     Success: Database created.
 *
 *     # Drop an existing database.
 *     $ wp db drop --yes
 *     Success: Database dropped.
 *
 *     # Reset the current database.
 *     $ wp db reset --yes
 *     Success: Database reset.
 *
 *     # Execute a SQL query stored in a file.
 *     $ wp db query < debug.sql
 */
class DB_Command extends WP_CLI_Command {

	/**
	 * Create a new database.
	 *
	 * Runs `CREATE_DATABASE` SQL statement using `DB_HOST`, `DB_NAME`,
	 * `DB_USER` and `DB_PASSWORD` database credentials specified in
	 * wp-config.php.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp db create
	 *     Success: Database created.
	 */
	public function create( $_, $assoc_args ) {

		self::run_query( self::get_create_query() );

		WP_CLI::success( "Database created." );
	}

	/**
	 * Delete the existing database.
	 *
	 * Runs `DROP_DATABASE` SQL statement using `DB_HOST`, `DB_NAME`,
	 * `DB_USER` and `DB_PASSWORD` database credentials specified in
	 * wp-config.php.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Answer yes to the confirmation message.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp db drop --yes
	 *     Success: Database dropped.
	 */
	public function drop( $_, $assoc_args ) {
		WP_CLI::confirm( "Are you sure you want to drop the '" . DB_NAME . "' database?", $assoc_args );

		self::run_query( sprintf( 'DROP DATABASE `%s`', DB_NAME ) );

		WP_CLI::success( "Database dropped." );
	}

	/**
	 * Remove all tables from the database.
	 *
	 * Runs `DROP_DATABASE` and `CREATE_DATABASE` SQL statements using
	 * `DB_HOST`, `DB_NAME`, `DB_USER` and `DB_PASSWORD` database credentials
	 * specified in wp-config.php.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Answer yes to the confirmation message.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp db reset --yes
	 *     Success: Database reset.
	 */
	public function reset( $_, $assoc_args ) {
		WP_CLI::confirm( "Are you sure you want to reset the '" . DB_NAME . "' database?", $assoc_args );

		self::run_query( sprintf( 'DROP DATABASE IF EXISTS `%s`', DB_NAME ) );
		self::run_query( self::get_create_query() );

		WP_CLI::success( "Database reset." );
	}

	/**
	 * Check the current status of the database.
	 *
	 * Runs `mysqlcheck` utility with `--check` using `DB_HOST`,
	 * `DB_NAME`, `DB_USER` and `DB_PASSWORD` database credentials
	 * specified in wp-config.php.
	 *
	 * [See docs](http://dev.mysql.com/doc/refman/5.7/en/check-table.html)
	 * for more details on the `CHECK TABLE` statement.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp db check
	 *     Success: Database checked.
	 */
	public function check() {
		self::run( Utils\esc_cmd( '/usr/bin/env mysqlcheck --no-defaults %s', DB_NAME ), array(
			'check' => true,
		) );

		WP_CLI::success( "Database checked." );
	}

	/**
	 * Optimize the database.
	 *
	 * Runs `mysqlcheck` utility with `--optimize=true` using `DB_HOST`,
	 * `DB_NAME`, `DB_USER` and `DB_PASSWORD` database credentials
	 * specified in wp-config.php.
	 *
	 * [See docs](http://dev.mysql.com/doc/refman/5.7/en/optimize-table.html)
	 * for more details on the `OPTIMIZE TABLE` statement.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp db optimize
	 *     Success: Database optimized.
	 */
	public function optimize() {
		self::run( Utils\esc_cmd( '/usr/bin/env mysqlcheck --no-defaults %s', DB_NAME ), array(
			'optimize' => true,
		) );

		WP_CLI::success( "Database optimized." );
	}

	/**
	 * Repair the database.
	 *
	 * Runs `mysqlcheck` utility with `--repair=true` using `DB_HOST`,
	 * `DB_NAME`, `DB_USER` and `DB_PASSWORD` database credentials
	 * specified in wp-config.php.
	 *
	 * [See docs](http://dev.mysql.com/doc/refman/5.7/en/repair-table.html) for
	 * more details on the `REPAIR TABLE` statement.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp db repair
	 *     Success: Database repaired.
	 */
	public function repair() {
		self::run( Utils\esc_cmd( '/usr/bin/env mysqlcheck --no-defaults %s', DB_NAME ), array(
			'repair' => true,
		) );

		WP_CLI::success( "Database repaired." );
	}

	/**
	 * Open a MySQL console using credentials from wp-config.php
	 *
	 * ## OPTIONS
	 *
	 * [--database=<database>]
	 * : Use a specific database. Defaults to DB_NAME.
	 *
	 * [--default-character-set=<character-set>]
	 * : Use a specific character set. Defaults to DB_CHARSET when defined.
	 *
	 * [--<field>=<value>]
	 * : Extra arguments to pass to the MySQL executable.
	 *
	 * ## EXAMPLES
	 *
	 *     # Open MySQL console
	 *     $ wp db cli
	 *     mysql>
	 *
	 * @alias connect
	 */
	public function cli( $args, $assoc_args ) {
		if ( ! isset( $assoc_args['database'] ) ) {
			$assoc_args['database'] = DB_NAME;
		}

		self::run( '/usr/bin/env mysql --no-defaults --no-auto-rehash', $assoc_args );
	}

	/**
	 * Execute a SQL query against the database.
	 *
	 * Executes an arbitrary SQL query using `DB_HOST`, `DB_NAME`, `DB_USER`
	 *  and `DB_PASSWORD` database credentials specified in wp-config.php.
	 *
	 * ## OPTIONS
	 *
	 * [<sql>]
	 * : A SQL query. If not passed, will try to read from STDIN.
	 *
	 * [--<field>=<value>]
	 * : Extra arguments to pass to mysql.
	 *
	 * ## EXAMPLES
	 *
	 *     # Execute a query stored in a file
	 *     $ wp db query < debug.sql
	 *
	 *     # Check all tables in the database
	 *     $ wp db query "CHECK TABLE $(wp db tables | paste -s -d',');"
	 *     +---------------------------------------+-------+----------+----------+
	 *     | Table                                 | Op    | Msg_type | Msg_text |
	 *     +---------------------------------------+-------+----------+----------+
	 *     | wordpress_dbase.wp_users              | check | status   | OK       |
	 *     | wordpress_dbase.wp_usermeta           | check | status   | OK       |
	 *     | wordpress_dbase.wp_posts              | check | status   | OK       |
	 *     | wordpress_dbase.wp_comments           | check | status   | OK       |
	 *     | wordpress_dbase.wp_links              | check | status   | OK       |
	 *     | wordpress_dbase.wp_options            | check | status   | OK       |
	 *     | wordpress_dbase.wp_postmeta           | check | status   | OK       |
	 *     | wordpress_dbase.wp_terms              | check | status   | OK       |
	 *     | wordpress_dbase.wp_term_taxonomy      | check | status   | OK       |
	 *     | wordpress_dbase.wp_term_relationships | check | status   | OK       |
	 *     | wordpress_dbase.wp_termmeta           | check | status   | OK       |
	 *     | wordpress_dbase.wp_commentmeta        | check | status   | OK       |
	 *     +---------------------------------------+-------+----------+----------+
	 *
	 *     # Pass extra arguments through to MySQL
	 *     $ wp db query 'SELECT * FROM wp_options WHERE option_name="home"' --skip-column-names
	 *     +---+------+------------------------------+-----+
	 *     | 2 | home | http://wordpress-develop.dev | yes |
	 *     +---+------+------------------------------+-----+
	 */
	public function query( $args, $assoc_args ) {
		$assoc_args['database'] = DB_NAME;

		// The query might come from STDIN
		if ( !empty( $args ) ) {
			$assoc_args['execute'] = $args[0];
		}

		self::run( '/usr/bin/env mysql --no-defaults --no-auto-rehash', $assoc_args );
	}

	/**
	 * Exports the database to a file or to STDOUT.
	 *
	 * Runs `mysqldump` utility using `DB_HOST`, `DB_NAME`, `DB_USER` and
	 * `DB_PASSWORD` database credentials specified in wp-config.php.
	 *
	 * ## OPTIONS
	 *
	 * [<file>]
	 * : The name of the SQL file to export. If '-', then outputs to STDOUT. If omitted, it will be '{dbname}.sql'.
	 *
	 * [--<field>=<value>]
	 * : Extra arguments to pass to mysqldump
	 *
	 * [--tables=<tables>]
	 * : The comma separated list of specific tables to export. Excluding this parameter will export all tables in the database.
	 *
	 * [--exclude_tables=<tables>]
	 * : The comma separated list of specific tables that should be skipped from exporting. Excluding this parameter will export all tables in the database.
	 *
	 * [--porcelain]
	 * : Output filename for the exported database.
	 *
	 * ## EXAMPLES
	 *
	 *     # Export database with drop query included
	 *     $ wp db export --add-drop-table
	 *     Success: Exported to 'wordpress_dbase.sql'.
	 *
	 *     # Export certain tables
	 *     $ wp db export --tables=wp_options,wp_users
	 *     Success: Exported to 'wordpress_dbase.sql'.
	 *
	 *     # Export all tables matching a wildcard
	 *     $ wp db export --tables=$(wp db tables 'wp_user*' --format=csv)
	 *     Success: Exported to 'wordpress_dbase.sql'.
	 *
	 *     # Export all tables matching prefix
	 *     $ wp db export --tables=$(wp db tables --all-tables-with-prefix --format=csv)
	 *     Success: Exported to 'wordpress_dbase.sql'.
	 *
	 *     # Skip certain tables from the exported database
	 *     $ wp db export --exclude_tables=wp_options,wp_users
	 *     Success: Exported to 'wordpress_dbase.sql'.
	 *
	 *     # Skip all tables matching a wildcard from the exported database
	 *     $ wp db export --exclude_tables=$(wp db tables 'wp_user*' --format=csv)
	 *     Success: Exported to 'wordpress_dbase.sql'.
	 *
	 *     # Skip all tables matching prefix from the exported database
	 *     $ wp db export --exclude_tables=$(wp db tables --all-tables-with-prefix --format=csv)
	 *     Success: Exported to 'wordpress_dbase.sql'.
	 *
	 * @alias dump
	 */
	public function export( $args, $assoc_args ) {
		if ( ! empty( $args[0] ) ) {
			$result_file = $args[0];
		} else {
			$hash = substr( md5( mt_rand() ), 0, 7 );
			$result_file = sprintf( '%s-%s.sql', DB_NAME, $hash );;
		}
		$stdout = ( '-' === $result_file );
		$porcelain = \WP_CLI\Utils\get_flag_value( $assoc_args, 'porcelain' );

		// Bail if both porcelain and STDOUT are set.
		if ( $stdout && $porcelain ) {
			WP_CLI::error( 'Porcelain is not allowed when output mode is STDOUT.' );
		}

		if ( ! $stdout ) {
			$assoc_args['result-file'] = $result_file;
		}

		$command = '/usr/bin/env mysqldump --no-defaults %s';
		$command_esc_args = array( DB_NAME );

		if ( isset( $assoc_args['tables'] ) ) {
			$tables = explode( ',', trim( $assoc_args['tables'], ',' ) );
			unset( $assoc_args['tables'] );
			$command .= ' --tables';
			foreach ( $tables as $table ) {
				$command .= ' %s';
				$command_esc_args[] = trim( $table );
			}
		}

		$exclude_tables = WP_CLI\Utils\get_flag_value( $assoc_args, 'exclude_tables' );
		if ( isset( $exclude_tables ) ) {
			$tables = explode( ',', trim( $assoc_args['exclude_tables'], ',' ) );
			unset( $assoc_args['exclude_tables'] );
			foreach ( $tables as $table ) {
				$command .= ' --ignore-table';
				$command .= ' %s';
				$command_esc_args[] = trim( DB_NAME . '.' . $table );
			}
		}

		$escaped_command = call_user_func_array( '\WP_CLI\Utils\esc_cmd', array_merge( array( $command ), $command_esc_args ) );

		// Remove parameters not needed for SQL run.
		unset( $assoc_args['porcelain'] );

		self::run( $escaped_command, $assoc_args );

		if ( $porcelain ) {
			WP_CLI::line( $result_file );
		}
		else if ( ! $stdout ) {
			WP_CLI::success( sprintf( "Exported to '%s'.", $result_file ) );
		}
	}

	/**
	 * Import a database from a file or from STDIN.
	 *
	 * Runs SQL queries using `DB_HOST`, `DB_NAME`, `DB_USER` and
	 * `DB_PASSWORD` database credentials specified in wp-config.php. This
	 * does not create database by itself and only performs whatever tasks are
	 * defined in the SQL.
	 *
	 * ## OPTIONS
	 *
	 * [<file>]
	 * : The name of the SQL file to import. If '-', then reads from STDIN. If omitted, it will look for '{dbname}.sql'.
	 *
	 * [--skip-optimization]
	 * : When using an SQL file, do not include speed optimization such as disabling auto-commit and key checks.
	 *
	 * ## EXAMPLES
	 *
	 *     # Import MySQL from a file.
	 *     $ wp db import wordpress_dbase.sql
	 *     Success: Imported from 'wordpress_dbase.sql'.
	 */
	public function import( $args, $assoc_args ) {
		if ( ! empty( $args[0] ) ) {
			$result_file = $args[0];
		} else {
			$result_file = sprintf( '%s.sql', DB_NAME );
		}

		$mysql_args = array(
			'database' => DB_NAME,
		);

		if ( '-' !== $result_file ) {
			if ( ! is_readable( $result_file ) ) {
				WP_CLI::error( sprintf( 'Import file missing or not readable: %s', $result_file ) );
			}

			$query = \WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-optimization' )
				? 'SOURCE %s;'
				: 'SET autocommit = 0; SET unique_checks = 0; SET foreign_key_checks = 0; SOURCE %s; COMMIT;';

			$mysql_args['execute'] = sprintf( $query, $result_file );
		}

		self::run( '/usr/bin/env mysql --no-defaults --no-auto-rehash', $mysql_args );

		WP_CLI::success( sprintf( "Imported from '%s'.", $result_file ) );
	}

	/**
	 * List the database tables.
	 *
	 * Defaults to all tables registered to the $wpdb database handler.
	 *
	 * ## OPTIONS
	 *
	 * [<table>...]
	 * : List tables based on wildcard search, e.g. 'wp_*_options' or 'wp_post?'.
	 *
	 * [--scope=<scope>]
	 * : Can be all, global, ms_global, blog, or old tables. Defaults to all.
	 *
	 * [--network]
	 * : List all the tables in a multisite install. Overrides --scope=<scope>.
	 *
	 * [--all-tables-with-prefix]
	 * : List all tables that match the table prefix even if not registered on $wpdb. Overrides --network.
	 *
	 * [--all-tables]
	 * : List all tables in the database, regardless of the prefix, and even if not registered on $wpdb. Overrides --all-tables-with-prefix.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: list
	 * options:
	 *   - list
	 *   - csv
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # List tables for a single site, without shared tables like 'wp_users'
	 *     $ wp db tables --scope=blog --url=sub.example.com
	 *     wp_3_posts
	 *     wp_3_comments
	 *     wp_3_options
	 *     wp_3_postmeta
	 *     wp_3_terms
	 *     wp_3_term_taxonomy
	 *     wp_3_term_relationships
	 *     wp_3_termmeta
	 *     wp_3_commentmeta
	 *
	 *     # Export only tables for a single site
	 *     $ wp db export --tables=$(wp db tables --url=sub.example.com --format=csv)
	 *     Success: Exported to wordpress_dbase.sql
	 */
	public function tables( $args, $assoc_args ) {

		$format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format' );
		unset( $assoc_args['format'] );

		if ( empty( $args ) && empty( $assoc_args ) ) {
			$assoc_args['scope'] = 'all';
		}

		$tables = WP_CLI\Utils\wp_get_table_names( $args, $assoc_args );

		if ( 'csv' === $format ) {
			WP_CLI::line( implode( ',', $tables ) );
		} else {
			foreach ( $tables as $table ) {
				WP_CLI::line( $table );
			}
		}
	}

	/**
	 * Display the database name and size.
	 *
	 * Display the database name and size for `DB_NAME` specified in wp-config.php.
	 * The size defaults to a human-readable number.
	 *
	 * ## OPTIONS
	 *
	 * [--size_format]
	 * : Display the database size only, as a bare number.
	 * ---
	 * default: b
	 * options:
	 *  - b (bytes)
	 *  - kb (kilobytes)
	 *  - mb (megabytes)
	 *  ---
	 *
	 * [--tables]
	 * : Display each table name and size instead of the database size.
	 *
	 * [--format]
	 * : table, csv, json
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 * ---
	 *
	 * [--scope=<scope>]
	 * : Can be all, global, ms_global, blog, or old tables. Defaults to all.
	 *
	 * [--network]
	 * : List all the tables in a multisite install. Overrides --scope=<scope>.
	 *
	 * [--all-tables-with-prefix]
	 * : List all tables that match the table prefix even if not registered on $wpdb. Overrides --network.
	 *
	 * [--all-tables]
	 * : List all tables in the database, regardless of the prefix, and even if not registered on $wpdb. Overrides --all-tables-with-prefix.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp db size
	 *     +-------------------+------+
	 *     | Name              | Size |
	 *     +-------------------+------+
	 *     | wordpress_default | 6 MB |
	 *     +-------------------+------+
	 *
	 *     $ wp db size --tables
	 *     +-----------------------+-------+
	 *     | Name                  | Size  |
	 *     +-----------------------+-------+
	 *     | wp_users              | 64 KB |
	 *     | wp_usermeta           | 48 KB |
	 *     | wp_posts              | 80 KB |
	 *     | wp_comments           | 96 KB |
	 *     | wp_links              | 32 KB |
	 *     | wp_options            | 32 KB |
	 *     | wp_postmeta           | 48 KB |
	 *     | wp_terms              | 48 KB |
	 *     | wp_term_taxonomy      | 48 KB |
	 *     | wp_term_relationships | 32 KB |
	 *     | wp_termmeta           | 48 KB |
	 *     | wp_commentmeta        | 48 KB |
	 *     +-----------------------+-------+
	 *
	 *     $ wp db size --size_format=b
	 *     5865472
	 *
	 *     $ wp db size --size_format=kb
	 *     5728
	 *
	 *     $ wp db size --size_format=mb
	 *     6
	 */
	public function size( $args, $assoc_args ) {

		@WP_CLI::get_runner()-> load_wordpress();

		global $wpdb;

		$format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format' );
		$size_format = WP_CLI\Utils\get_flag_value( $assoc_args, 'size_format' );
		$tables = WP_CLI\Utils\get_flag_value( $assoc_args, 'tables' );
		$tables = ! empty( $tables );

		unset( $assoc_args['format'] );
		unset( $assoc_args['size_format'] );
		unset( $assoc_args['tables'] );

		if ( empty( $args ) && empty( $assoc_args ) ) {
			$assoc_args['scope'] = 'all';
		}

		// Build rows for the formatter.
		$rows = array();
		$fields = array( 'Name', 'Size' );

		if ( $tables ) {

			// Add all of the table sizes
			foreach( WP_CLI\Utils\wp_get_table_names( $args, $assoc_args ) as $table_name ) {

				// Get the table size.
				$table_bytes = $wpdb->get_var( $wpdb->prepare(
					"SELECT SUM(data_length + index_length) FROM information_schema.TABLES where table_schema = '%s' and Table_Name = '%s' GROUP BY Table_Name LIMIT 1",
					DB_NAME,
					$table_name
					)
				);

				// Add the table size to the list.
				$rows[] = array(
					'Name'  => $table_name,
					'Size'  => strtoupper( size_format( $table_bytes ) ),
				);
			}
		} else {

			// Get the database size.
			$db_bytes = $wpdb->get_var( $wpdb->prepare(
				"SELECT SUM(data_length + index_length) FROM information_schema.TABLES where table_schema = '%s' GROUP BY table_schema;",
				DB_NAME
				)
			);

			// Add the database size to the list.
			$rows[] = array(
				'Name'  => DB_NAME,
				'Size'  => strtoupper( size_format( $db_bytes ) ),
				);
		}

		if ( ! empty( $size_format ) && isset( $db_bytes ) && ! $tables ) {

			// Display the database size as a number.
			switch( $size_format ) {
				case 'mb':
					$divisor = MB_IN_BYTES;
					break;

				case 'kb':
					$divisor = KB_IN_BYTES;
					break;

				case 'b':
				default:
					$divisor = 1;
					break;
			}

			WP_CLI::Line( ceil( $db_bytes / $divisor ) );
		} else {

			// Display the rows.
			$args = array(
				'format' => $format,
			);

			$formatter = new \WP_CLI\Formatter( $args, $fields );
			$formatter->display_items( $rows );
		}
	}

	/**
	 * Display the database table prefix.
	 *
	 * Display the database table prefix, as defined by the database handler's interpretation of the current site.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp db prefix
	 *     wp_
	 */
	public function prefix() {
		@WP_CLI::get_runner()->load_wordpress();

		global $wpdb;

		WP_CLI::log( $wpdb->prefix );
	}

	/**
	 * Display the IDs of all entries without existing reference object.
	 *
	 * For metadata, display all entries referencing an object that does not exist anymore. For comments and posts, display all entries referencing a post (parent) that does not exist anymore.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : Object type.
	 * ---
	 * default: post
	 * options:
	 *  - comment
	 *  - commentmeta
	 *  - post
	 *  - postmeta
	 *  - termmeta
	 *  - usermeta
	 * ---
	 *
	 * [--count]
	 * : Print the number of found entries instead of the according IDs.
	 *
	 * [--delete]
	 * : Delete all found entries.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp db orphan
	 *     4
	 *     8
	 *     15
	 *     16
	 *     23
	 *     42
	 *
	 *     $ wp db orphan --count
	 *     Entries found: 6
	 *
	 *     $ wp db orphan --delete
	 *     4
	 *     8
	 *     15
	 *     16
	 *     23
	 *     42
	 *     Entries deleted: 6
	 *
	 *     $ wp db orphan --type=usermeta
	 *     1234
	 */
	public function orphan( array $args, array $assoc_args ) {

		@WP_CLI::get_runner()->load_wordpress();

		global $wpdb;

		$valid_types = array(
			'comment',
			'commentmeta',
			'post',
			'postmeta',
			'termmeta',
			'usermeta',
		);

		$type = Utils\get_flag_value( $assoc_args, 'type', 'post' );
		if ( ! in_array( $type, $valid_types, true ) ) {
			return;
		}

		$query_values = $this->get_orphan_query_values( $type );
		if ( ! $query_values ) {
			return;
		}

		$count = Utils\get_flag_value( $assoc_args, 'count' );

		$results = $wpdb->get_col( $wpdb->prepare(
			'SELECT `%2$s` FROM `%1$s` WHERE `%3$s` != 0 AND `%3$s` NOT IN ( SELECT `%5$s` FROM `%4$s` )',
			$query_values
		) );
		if ( ! $results ) {
			if ( $count ) {
				echo 'No entries found.' . PHP_EOL;
			}

			return;
		}

		if ( $count ) {
			echo 'Entries found: ' . count( $results ) . PHP_EOL;
		} else {
			echo implode( PHP_EOL, $results ) . PHP_EOL;
		}

		if ( Utils\get_flag_value( $assoc_args, 'delete' ) ) {
			$deleted = (int) $wpdb->query( $wpdb->prepare(
				'DELETE FROM `%1$s` WHERE `%2$s` IN ( ' . implode( ',', $results ) . ' )',
				$query_values
			) );
			if ( $deleted ) {
				WP_CLI::success( "Entries deleted: {$deleted}" );
			} else {
				WP_CLI::error( 'No entries deleted.' );
			}
		}
	}

	/**
	 * Return the values needed by orphan() for the given type.
	 *
	 * @param string $type Object type.
	 *
	 * @return string[] Values needed by orphan_meta().
	 */
	private function get_orphan_query_values( $type ) {

		@WP_CLI::get_runner()->load_wordpress();

		global $wpdb;

		switch ( $type ) {
			case 'comment':
				return array(
					$wpdb->comments,
					'comment_id',
					'comment_post_ID',
					$wpdb->posts,
					'ID',
				);

			case 'commentmeta':
				return array(
					$wpdb->commentmeta,
					'meta_id',
					'comment_id',
					$wpdb->comments,
					'comment_ID',
				);

			case 'post':
				return array(
					$wpdb->posts,
					'ID',
					'post_parent',
					$wpdb->posts,
					'ID',
				);

			case 'postmeta':
				return array(
					$wpdb->postmeta,
					'meta_id',
					'post_id',
					$wpdb->posts,
					'ID',
				);

			case 'termmeta':
				return array(
					$wpdb->termmeta,
					'meta_id',
					'term_id',
					$wpdb->terms,
					'term_id',
				);

			case 'usermeta':
				return array(
					$wpdb->usermeta,
					'umeta_id',
					'user_id',
					$wpdb->users,
					'ID',
				);
		}

		return array();
	}

	private static function get_create_query() {

		$create_query = sprintf( 'CREATE DATABASE `%s`', DB_NAME );
		if ( defined( 'DB_CHARSET' ) && constant( 'DB_CHARSET' ) ) {
			$create_query .= sprintf( ' DEFAULT CHARSET `%s`', constant( 'DB_CHARSET' ) );
		}
		if ( defined( 'DB_COLLATE' ) && constant( 'DB_COLLATE' ) ) {
			$create_query .= sprintf( ' DEFAULT COLLATE `%s`', constant( 'DB_COLLATE' ) );
		}
		return $create_query;
	}

	private static function run_query( $query ) {
		self::run( '/usr/bin/env mysql --no-defaults --no-auto-rehash', array( 'execute' => $query ) );
	}

	private static function run( $cmd, $assoc_args = array(), $descriptors = null ) {
		$required = array(
			'host' => DB_HOST,
			'user' => DB_USER,
			'pass' => DB_PASSWORD,
		);

		if ( ! isset( $assoc_args['default-character-set'] )
			&& defined( 'DB_CHARSET' ) && constant( 'DB_CHARSET' ) ) {
			$required['default-character-set'] = constant( 'DB_CHARSET' );
		}

		$final_args = array_merge( $assoc_args, $required );
		Utils\run_mysql_command( $cmd, $final_args, $descriptors );
	}
}
