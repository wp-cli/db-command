<?php

use WP_CLI\Formatter;
use \WP_CLI\Utils;

/**
 * Performs basic database operations using credentials stored in wp-config.php.
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
 *
 * @when after_wp_config_load
 */
class DB_Command extends WP_CLI_Command {

	/**
	 * Creates a new database.
	 *
	 * Runs `CREATE_DATABASE` SQL statement using `DB_HOST`, `DB_NAME`,
	 * `DB_USER` and `DB_PASSWORD` database credentials specified in
	 * wp-config.php.
	 *
	 * ## OPTIONS
	 *
	 * [--dbuser=<value>]
	 * : Username to pass to mysql. Defaults to DB_USER.
	 *
	 * [--dbpass=<value>]
	 * : Password to pass to mysql. Defaults to DB_PASSWORD.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp db create
	 *     Success: Database created.
	 */
	public function create( $_, $assoc_args ) {

		self::run_query( self::get_create_query(), self::get_dbuser_dbpass_args( $assoc_args ) );

		WP_CLI::success( "Database created." );
	}

	/**
	 * Deletes the existing database.
	 *
	 * Runs `DROP_DATABASE` SQL statement using `DB_HOST`, `DB_NAME`,
	 * `DB_USER` and `DB_PASSWORD` database credentials specified in
	 * wp-config.php.
	 *
	 * ## OPTIONS
	 *
	 * [--dbuser=<value>]
	 * : Username to pass to mysql. Defaults to DB_USER.
	 *
	 * [--dbpass=<value>]
	 * : Password to pass to mysql. Defaults to DB_PASSWORD.
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

		self::run_query( sprintf( 'DROP DATABASE `%s`', DB_NAME ), self::get_dbuser_dbpass_args( $assoc_args ) );

		WP_CLI::success( "Database dropped." );
	}

	/**
	 * Removes all tables from the database.
	 *
	 * Runs `DROP_DATABASE` and `CREATE_DATABASE` SQL statements using
	 * `DB_HOST`, `DB_NAME`, `DB_USER` and `DB_PASSWORD` database credentials
	 * specified in wp-config.php.
	 *
	 * ## OPTIONS
	 *
	 * [--dbuser=<value>]
	 * : Username to pass to mysql. Defaults to DB_USER.
	 *
	 * [--dbpass=<value>]
	 * : Password to pass to mysql. Defaults to DB_PASSWORD.
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

		$mysql_args = self::get_dbuser_dbpass_args( $assoc_args );

		self::run_query( sprintf( 'DROP DATABASE IF EXISTS `%s`', DB_NAME ), $mysql_args );
		self::run_query( self::get_create_query(), $mysql_args );

		WP_CLI::success( "Database reset." );
	}

	/**
	 * Removes all tables with `$table_prefix` from the database.
	 *
	 * Runs `DROP_TABLE` for each table that has a `$table_prefix` as specified
	 * in wp-config.php.
	 *
	 * ## OPTIONS
	 *
	 * [--dbuser=<value>]
	 * : Username to pass to mysql. Defaults to DB_USER.
	 *
	 * [--dbpass=<value>]
	 * : Password to pass to mysql. Defaults to DB_PASSWORD.
	 *
	 * [--yes]
	 * : Answer yes to the confirmation message.
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete all tables that match the current site prefix.
	 *     $ wp db clean --yes
	 *     Success: Tables dropped.
	 *
	 * @when after_wp_load
	 */
	public function clean( $_, $assoc_args ) {
		global $wpdb;

		WP_CLI::confirm(
			sprintf(
				"Are you sure you want to drop all the tables on '%s' that use the current site's database prefix ('%s')?",
				DB_NAME,
				$wpdb->get_blog_prefix()
			),
			$assoc_args
		);

		$mysql_args = self::get_dbuser_dbpass_args( $assoc_args );

		$tables = WP_CLI\Utils\wp_get_table_names(
			array(),
			array( 'all-tables-with-prefix' )
		);

		foreach ( $tables as $table ) {
			self::run_query(
				sprintf(
					'DROP TABLE IF EXISTS `%s`.`%s`',
					DB_NAME,
					$table
				),
				$mysql_args
			);
		}

		WP_CLI::success( 'Tables dropped.' );
	}

	/**
	 * Checks the current status of the database.
	 *
	 * Runs `mysqlcheck` utility with `--check` using `DB_HOST`,
	 * `DB_NAME`, `DB_USER` and `DB_PASSWORD` database credentials
	 * specified in wp-config.php.
	 *
	 * [See docs](http://dev.mysql.com/doc/refman/5.7/en/check-table.html)
	 * for more details on the `CHECK TABLE` statement.
	 *
	 * ## OPTIONS
	 *
	 * [--dbuser=<value>]
	 * : Username to pass to mysqlcheck. Defaults to DB_USER.
	 *
	 * [--dbpass=<value>]
	 * : Password to pass to mysqlcheck. Defaults to DB_PASSWORD.
	 *
	 * [--<field>=<value>]
	 * : Extra arguments to pass to mysqlcheck.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp db check
	 *     Success: Database checked.
	 */
	public function check( $_, $assoc_args ) {
		self::run( Utils\esc_cmd( '/usr/bin/env mysqlcheck --no-defaults %s', DB_NAME ), array_merge( $assoc_args, array(
			'check' => true,
		) ) );

		WP_CLI::success( "Database checked." );
	}

	/**
	 * Optimizes the database.
	 *
	 * Runs `mysqlcheck` utility with `--optimize=true` using `DB_HOST`,
	 * `DB_NAME`, `DB_USER` and `DB_PASSWORD` database credentials
	 * specified in wp-config.php.
	 *
	 * [See docs](http://dev.mysql.com/doc/refman/5.7/en/optimize-table.html)
	 * for more details on the `OPTIMIZE TABLE` statement.
	 *
	 * ## OPTIONS
	 *
	 * [--dbuser=<value>]
	 * : Username to pass to mysqlcheck. Defaults to DB_USER.
	 *
	 * [--dbpass=<value>]
	 * : Password to pass to mysqlcheck. Defaults to DB_PASSWORD.
	 *
	 * [--<field>=<value>]
	 * : Extra arguments to pass to mysqlcheck.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp db optimize
	 *     Success: Database optimized.
	 */
	public function optimize( $_, $assoc_args ) {
		self::run( Utils\esc_cmd( '/usr/bin/env mysqlcheck --no-defaults %s', DB_NAME ), array_merge( $assoc_args, array(
			'optimize' => true,
		) ) );

		WP_CLI::success( "Database optimized." );
	}

	/**
	 * Repairs the database.
	 *
	 * Runs `mysqlcheck` utility with `--repair=true` using `DB_HOST`,
	 * `DB_NAME`, `DB_USER` and `DB_PASSWORD` database credentials
	 * specified in wp-config.php.
	 *
	 * [See docs](http://dev.mysql.com/doc/refman/5.7/en/repair-table.html) for
	 * more details on the `REPAIR TABLE` statement.
	 *
	 * ## OPTIONS
	 *
	 * [--dbuser=<value>]
	 * : Username to pass to mysqlcheck. Defaults to DB_USER.
	 *
	 * [--dbpass=<value>]
	 * : Password to pass to mysqlcheck. Defaults to DB_PASSWORD.
	 *
	 * [--<field>=<value>]
	 * : Extra arguments to pass to mysqlcheck.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp db repair
	 *     Success: Database repaired.
	 */
	public function repair( $_, $assoc_args ) {
		self::run( Utils\esc_cmd( '/usr/bin/env mysqlcheck --no-defaults %s', DB_NAME ), array_merge( $assoc_args, array(
			'repair' => true,
		) ) );

		WP_CLI::success( "Database repaired." );
	}

	/**
	 * Opens a MySQL console using credentials from wp-config.php
	 *
	 * ## OPTIONS
	 *
	 * [--database=<database>]
	 * : Use a specific database. Defaults to DB_NAME.
	 *
	 * [--default-character-set=<character-set>]
	 * : Use a specific character set. Defaults to DB_CHARSET when defined.
	 *
	 * [--dbuser=<value>]
	 * : Username to pass to mysql. Defaults to DB_USER.
	 *
	 * [--dbpass=<value>]
	 * : Password to pass to mysql. Defaults to DB_PASSWORD.
	 *
	 * [--<field>=<value>]
	 * : Extra arguments to pass to mysql.
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
	 * Executes a SQL query against the database.
	 *
	 * Executes an arbitrary SQL query using `DB_HOST`, `DB_NAME`, `DB_USER`
	 *  and `DB_PASSWORD` database credentials specified in wp-config.php.
	 *
	 * ## OPTIONS
	 *
	 * [<sql>]
	 * : A SQL query. If not passed, will try to read from STDIN.
	 *
	 * [--dbuser=<value>]
	 * : Username to pass to mysql. Defaults to DB_USER.
	 *
	 * [--dbpass=<value>]
	 * : Password to pass to mysql. Defaults to DB_PASSWORD.
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
	 *     $ wp db query "CHECK TABLE $(wp db tables | paste -s -d, -);"
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
	 * : The name of the SQL file to export. If '-', then outputs to STDOUT. If
	 * omitted, it will be '{dbname}-{Y-m-d}-{random-hash}.sql'.
	 *
	 * [--dbuser=<value>]
	 * : Username to pass to mysqldump. Defaults to DB_USER.
	 *
	 * [--dbpass=<value>]
	 * : Password to pass to mysqldump. Defaults to DB_PASSWORD.
	 *
	 * [--<field>=<value>]
	 * : Extra arguments to pass to mysqldump.
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
	 *     Success: Exported to 'wordpress_dbase-db72bb5.sql'.
	 *
	 *     # Export certain tables
	 *     $ wp db export --tables=wp_options,wp_users
	 *     Success: Exported to 'wordpress_dbase-db72bb5.sql'.
	 *
	 *     # Export all tables matching a wildcard
	 *     $ wp db export --tables=$(wp db tables 'wp_user*' --format=csv)
	 *     Success: Exported to 'wordpress_dbase-db72bb5.sql'.
	 *
	 *     # Export all tables matching prefix
	 *     $ wp db export --tables=$(wp db tables --all-tables-with-prefix --format=csv)
	 *     Success: Exported to 'wordpress_dbase-db72bb5.sql'.
	 *
	 *     # Export certain posts without create table statements
	 *     $ wp db export --no-create-info=true --tables=wp_posts --where="ID in (100,101,102)"
	 *     Success: Exported to 'wordpress_dbase-db72bb5.sql'.
	 *
	 *     # Export relating meta for certain posts without create table statements
	 *     $ wp db export --no-create-info=true --tables=wp_postmeta --where="post_id in (100,101,102)"
	 *     Success: Exported to 'wordpress_dbase-db72bb5.sql'.
	 *
	 *     # Skip certain tables from the exported database
	 *     $ wp db export --exclude_tables=wp_options,wp_users
	 *     Success: Exported to 'wordpress_dbase-db72bb5.sql'.
	 *
	 *     # Skip all tables matching a wildcard from the exported database
	 *     $ wp db export --exclude_tables=$(wp db tables 'wp_user*' --format=csv)
	 *     Success: Exported to 'wordpress_dbase-db72bb5.sql'.
	 *
	 *     # Skip all tables matching prefix from the exported database
	 *     $ wp db export --exclude_tables=$(wp db tables --all-tables-with-prefix --format=csv)
	 *     Success: Exported to 'wordpress_dbase-db72bb5.sql'.
	 *
	 *     # Export database to STDOUT.
	 *     $ wp db export -
	 *     -- MySQL dump 10.13  Distrib 5.7.19, for osx10.12 (x86_64)
	 *     --
	 *     -- Host: localhost    Database: wpdev
	 *     -- ------------------------------------------------------
	 *     -- Server version	5.7.19
	 *     ...
	 *
	 * @alias dump
	 */
	public function export( $args, $assoc_args ) {
		if ( ! empty( $args[0] ) ) {
			$result_file = $args[0];
		} else {
			$hash = substr( md5( mt_rand() ), 0, 7 );
			$result_file = sprintf( '%s-%s-%s.sql', DB_NAME, date( 'Y-m-d' ), $hash );;
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

		$support_column_statistics = exec( 'mysqldump --help | grep "column-statistics"' );

		if ( $support_column_statistics ) {
			$command = '/usr/bin/env mysqldump --no-defaults --skip-column-statistics %s';
		} else {
			$command = '/usr/bin/env mysqldump --no-defaults %s';
		}

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
	 * Imports a database from a file or from STDIN.
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
	 * [--dbuser=<value>]
	 * : Username to pass to mysql. Defaults to DB_USER.
	 *
	 * [--dbpass=<value>]
	 * : Password to pass to mysql. Defaults to DB_PASSWORD.
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
		$mysql_args = array_merge( self::get_dbuser_dbpass_args( $assoc_args ), $mysql_args );

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
	 * Lists the database tables.
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
	 * : List all the tables in a multisite install.
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
	 *
	 * @when after_wp_load
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
	 * Displays the database name and size.
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
	 *  - gb (gigabytes)
	 *  - tb (terabytes)
	 *  - B   (ISO Byte setting, with no conversion)
	 *  - KB  (ISO Kilobyte setting, with 1 KB  = 1,000 B)
	 *  - KiB (ISO Kibibyte setting, with 1 KiB = 1,024 B)
	 *  - MB  (ISO Megabyte setting, with 1 MB  = 1,000 KB)
	 *  - MiB (ISO Mebibyte setting, with 1 MiB = 1,024 KiB)
	 *  - GB  (ISO Gigabyte setting, with 1 GB  = 1,000 MB)
	 *  - GiB (ISO Gibibyte setting, with 1 GiB = 1,024 MiB)
	 *  - TB  (ISO Terabyte setting, with 1 TB  = 1,000 GB)
	 *  - TiB (ISO Tebibyte setting, with 1 TiB = 1,024 GiB)
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
	 * : List all the tables in a multisite install.
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
	 *
	 * @when after_wp_load
	 */
	public function size( $args, $assoc_args ) {

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

		$default_unit = ( empty( $size_format ) ) ? ' B' : '';

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
					'Size'  => strtoupper( $table_bytes ) . $default_unit,
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
				'Size'  => strtoupper( $db_bytes ) . $default_unit,
				);
		}

		if ( ! empty( $size_format ) ) {
			foreach( $rows as $index => $row ) {
					// These added WP 4.4.0.
					if ( ! defined( 'KB_IN_BYTES' ) ) {
						define( 'KB_IN_BYTES', 1024 );
					}
					if ( ! defined( 'MB_IN_BYTES' ) ) {
						define( 'MB_IN_BYTES', 1024 * KB_IN_BYTES );
					}
				        if ( ! defined( 'GB_IN_BYTES' ) ) {
						define( 'GB_IN_BYTES', 1024 * MB_IN_BYTES );
					}
				        if ( ! defined( 'TB_IN_BYTES' ) ) {
						define( 'TB_IN_BYTES', 1024 * GB_IN_BYTES );
					}

					// Display the database size as a number.
					switch( $size_format ) {
						case 'TB':
						         $divisor = pow( 1000, 4 );
							 break;

						case 'GB':
						         $divisor = pow( 1000, 3 );
							 break;

						case 'MB':
						         $divisor = pow( 1000, 2 );
							 break;

						case 'KB':
						         $divisor = 1000;
							 break;

						case 'tb':
						case 'TiB':
						         $divisor = TB_IN_BYTES;
							 break;

						case 'gb':
						case 'GiB':
						         $divisor = GB_IN_BYTES;
							 break;

						case 'mb':
						case 'MiB':
							$divisor = MB_IN_BYTES;
							break;

						case 'kb':
						case 'KiB':
							$divisor = KB_IN_BYTES;
							break;

						case 'b':
						case 'B':
						default:
							$divisor = 1;
							break;
					}
					$size_format_display = preg_replace( '/IB$/u', 'iB', strtoupper( $size_format ) );

					$rows[ $index ]['Size'] = ceil( $row['Size'] / $divisor ) . " " . $size_format_display;
			}
		}

		if ( ! empty( $size_format) && ! $tables ) {
			WP_CLI::Line( filter_var( $rows[0]['Size'], FILTER_SANITIZE_NUMBER_INT ) );
		} else {
			// Display the rows.
			$args = array(
				'format' => $format,
			);

			$formatter = new Formatter( $args, $fields );
			$formatter->display_items( $rows );
		}
	}

	/**
	 * Displays the database table prefix.
	 *
	 * Display the database table prefix, as defined by the database handler's interpretation of the current site.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp db prefix
	 *     wp_
	 *
	 * @when after_wp_load
	 */
	public function prefix() {
		global $wpdb;

		WP_CLI::log( $wpdb->prefix );
	}

	/**
	 * Finds a string in the database.
	 *
	 * Searches through all or a selection of database tables for a given string, Outputs colorized references to the string.
	 *
	 * Defaults to searching through all tables registered to $wpdb. On multisite, this default is limited to the tables for the current site.
	 *
	 * ## OPTIONS
	 *
	 * <search>
	 * : String to search for. The search is case-insensitive by default.
	 *
	 * [<tables>...]
	 * : One or more tables to search through for the string.
	 *
	 * [--network]
	 * : Search through all the tables registered to $wpdb in a multisite install.
	 *
	 * [--all-tables-with-prefix]
	 * : Search through all tables that match the registered table prefix, even if not registered on $wpdb. On one hand, sometimes plugins use tables without registering them to $wpdb. On another hand, this could return tables you don't expect. Overrides --network.
	 *
	 * [--all-tables]
	 * : Search through ALL tables in the database, regardless of the prefix, and even if not registered on $wpdb. Overrides --network and --all-tables-with-prefix.
	 *
	 * [--before_context=<num>]
	 * : Number of characters to display before the match.
	 * ---
	 * default: 40
	 * ---
	 *
	 * [--after_context=<num>]
	 * : Number of characters to display after the match.
	 * ---
	 * default: 40
	 * ---
	 *
	 * [--regex]
	 * : Runs the search as a regular expression (without delimiters). The search becomes case-sensitive (i.e. no PCRE flags are added). Delimiters must be escaped if they occur in the expression.
	 *
	 * [--regex-flags=<regex-flags>]
	 * : Pass PCRE modifiers to the regex search (e.g. 'i' for case-insensitivity).
	 *
	 * [--regex-delimiter=<regex-delimiter>]
	 * : The delimiter to use for the regex. It must be escaped if it appears in the search string. The default value is the result of `chr(1)`.
	 *
	 * [--table_column_once]
	 * : Output the 'table:column' line once before all matching row lines in the table column rather than before each matching row.
	 *
	 * [--one_line]
	 * : Place the 'table:column' output on the same line as the row id and match ('table:column:id:match'). Overrides --table_column_once.
	 *
	 * [--matches_only]
	 * : Only output the string matches (including context). No 'table:column's or row ids are outputted.
	 *
	 * [--stats]
	 * : Output stats on the number of matches found, time taken, tables/columns/rows searched, tables skipped.
	 *
	 * [--table_column_color=<color_code>]
	 * : Percent color code to use for the 'table:column' output. For a list of available percent color codes, see below. Default '%G' (bright green).
	 *
	 * [--id_color=<color_code>]
	 * : Percent color code to use for the row id output. For a list of available percent color codes, see below. Default '%Y' (bright yellow).
	 *
	 * [--match_color=<color_code>]
	 * : Percent color code to use for the match (unless both before and after context are 0, when no color code is used). For a list of available percent color codes, see below. Default '%3%k' (black on a mustard background).
	 *
	 * The percent color codes available are:
	 *
	 * | Code | Color
	 * | ---- | -----
	 * |  %y  | Yellow (dark) (mustard)
	 * |  %g  | Green (dark)
	 * |  %b  | Blue (dark)
	 * |  %r  | Red (dark)
	 * |  %m  | Magenta (dark)
	 * |  %c  | Cyan (dark)
	 * |  %w  | White (dark) (light gray)
	 * |  %k  | Black
	 * |  %Y  | Yellow (bright)
	 * |  %G  | Green (bright)
	 * |  %B  | Blue (bright)
	 * |  %R  | Red (bright)
	 * |  %M  | Magenta (bright)
	 * |  %C  | Cyan (bright)
	 * |  %W  | White
	 * |  %K  | Black (bright) (dark gray)
	 * |  %3  | Yellow background (dark) (mustard)
	 * |  %2  | Green background (dark)
	 * |  %4  | Blue background (dark)
	 * |  %1  | Red background (dark)
	 * |  %5  | Magenta background (dark)
	 * |  %6  | Cyan background (dark)
	 * |  %7  | White background (dark) (light gray)
	 * |  %0  | Black background
	 * |  %8  | Reverse
	 * |  %U  | Underline
	 * |  %F  | Blink (unlikely to work)
	 *
	 * They can be concatenated. For instance, the default match color of black on a mustard (dark yellow) background `%3%k` can be made black on a bright yellow background with `%Y%0%8`.
	 *
	 * ## EXAMPLES
	 *
	 *     # Search through the database for the 'wordpress-develop' string
	 *     $ wp db search wordpress-develop
	 *     wp_options:option_value
	 *     1:http://wordpress-develop.dev
	 *     wp_options:option_value
	 *     1:http://example.com/foo
	 *         ...
	 *
	 *     # Search through a multisite database on the subsite 'foo' for the 'example.com' string
	 *     $ wp db search example.com --url=example.com/foo
	 *     wp_2_comments:comment_author_url
	 *     1:http://example.com/
	 *     wp_2_options:option_value
	 *         ...
	 *
	 *     # Search through the database for the 'https?://' regular expression, printing stats.
	 *     $ wp db search 'https?://' --regex --stats
	 *     wp_comments:comment_author_url
	 *     1:https://wordpress.org/
	 *         ...
	 *     Success: Found 99146 matches in 10.752s (10.559s searching). Searched 12 tables, 53 columns, 1358907 rows. 1 table skipped: wp_term_relationships.
	 *
	 * @when after_wp_load
	 */
	public function search( $args, $assoc_args ) {
		global $wpdb;

		$start_run_time = microtime( true );

		$search = array_shift( $args );

		$before_context = \WP_CLI\Utils\get_flag_value( $assoc_args, 'before_context', 40 );
		$before_context = '' === $before_context ? $before_context : (int) $before_context;

		$after_context = \WP_CLI\Utils\get_flag_value( $assoc_args, 'after_context', 40 );
		$after_context = '' === $after_context ? $after_context : (int) $after_context;

		if ( ( $regex = \WP_CLI\Utils\get_flag_value( $assoc_args, 'regex', false ) ) ) {
			$regex_flags = \WP_CLI\Utils\get_flag_value( $assoc_args, 'regex-flags', false );
			$default_regex_delimiter = false;
			$regex_delimiter = \WP_CLI\Utils\get_flag_value( $assoc_args, 'regex-delimiter', '' );
			if ( '' === $regex_delimiter ) {
				$regex_delimiter = chr( 1 );
				$default_regex_delimiter = true;
			}
		}

		$colors = self::get_colors( $assoc_args, array( 'table_column' => '%G', 'id' => '%Y', 'match' => $before_context || $after_context ? '%3%k' : '' ) );

		$table_column_once = \WP_CLI\Utils\get_flag_value( $assoc_args, 'table_column_once', false );
		$one_line = \WP_CLI\Utils\get_flag_value( $assoc_args, 'one_line', false );
		$matches_only = \WP_CLI\Utils\get_flag_value( $assoc_args, 'matches_only', false );
		$stats = \WP_CLI\Utils\get_flag_value( $assoc_args, 'stats', false );

		$column_count = $row_count = $match_count = 0;
		$skipped = array();

		if ( $regex ) {
			// Note the user must escape the delimiter in the search.
			$search_regex = $regex_delimiter . $search . $regex_delimiter;
			if ( $regex_flags ) {
				$search_regex .= $regex_flags;
			}
			if ( false === @preg_match( $search_regex, '' ) ) {
				if ( $default_regex_delimiter ) {
					$flags_msg = $regex_flags ? "flags '$regex_flags'" : "no flags";
					$msg = "The regex pattern '$search' with default delimiter 'chr(1)' and {$flags_msg} fails.";
				} else {
					$msg = "The regex '$search_regex' fails.";
				}
				WP_CLI::error( $msg );
			}
		} else {
			$search_regex = '#' . preg_quote( $search, '#' ) . '#i';
			$esc_like_search = '%' . Utils\esc_like( $search ) . '%';
		}

		$encoding = null;
		if ( 0 === strpos( $wpdb->charset, 'utf8' ) ) {
			$encoding = 'UTF-8';
		}

		$tables = WP_CLI\Utils\wp_get_table_names( $args, $assoc_args );

		$start_search_time = microtime( true );

		foreach ( $tables as $table ) {
			list( $primary_keys, $text_columns, $all_columns ) = self::get_columns( $table );
			if ( ! $all_columns ) {
				WP_CLI::error( "No such table '$table'." );
			}
			if ( ! $text_columns ) {
				if ( $stats ) {
					$skipped[] = $table;
				} else {
					// Don't bother warning for term relationships (which is just 3 int columns).
					if ( ! preg_match( '/_term_relationships$/', $table ) ) {
						WP_CLI::warning( $primary_keys ? "No text columns for table '$table' - skipped." : "No primary key or text columns for table '$table' - skipped." );
					}
				}
				continue;
			}
			$table_sql = self::esc_sql_ident( $table );
			$column_count += count( $text_columns );
			if ( ! $primary_keys ) {
				WP_CLI::warning( "No primary key for table '$table'. No row ids will be outputted." );
				$primary_key = $primary_key_sql = '';
			} else {
				$primary_key = array_shift( $primary_keys );
				$primary_key_sql = self::esc_sql_ident( $primary_key ) . ', ';
			}

			foreach ( $text_columns as $column ) {
				$column_sql = self::esc_sql_ident( $column );
				if ( $regex ) {
					$results = $wpdb->get_results( "SELECT {$primary_key_sql}{$column_sql} FROM {$table_sql}" );
				} else {
					$results = $wpdb->get_results( $wpdb->prepare( "SELECT {$primary_key_sql}{$column_sql} FROM {$table_sql} WHERE {$column_sql} LIKE %s;", $esc_like_search ) );
				}
				if ( $results ) {
					$row_count += count( $results );
					$table_column_val = $colors['table_column'][0] . "{$table}:{$column}" . $colors['table_column'][1];
					$outputted_table_column_once = false;
					foreach ( $results as $result ) {
						$col_val = $result->$column;
						if ( preg_match_all( $search_regex, $col_val, $matches, PREG_OFFSET_CAPTURE ) ) {
							if ( ! $matches_only && ( ! $table_column_once || ! $outputted_table_column_once ) && ! $one_line ) {
								WP_CLI::log( $table_column_val );
								$outputted_table_column_once = true;
							}
							$pk_val = $primary_key ? ( $colors['id'][0] . $result->$primary_key . $colors['id'][1] . ':' ) : '';

							$bits = array();
							$col_encoding = $encoding;
							if ( ! $col_encoding && ( $before_context || $after_context ) && function_exists( 'mb_detect_encoding' ) ) {
								$col_encoding = mb_detect_encoding( $col_val, null, true /*strict*/ );
							}
							$append_next = false;
							$last_offset = 0;
							$match_cnt = count( $matches[0] );
							for ( $i = 0; $i < $match_cnt; $i++ ) {
								$match = $matches[0][ $i ][0];
								$offset = $matches[0][ $i ][1];
								$log = $colors['match'][0] . $match . $colors['match'][1];
								$before = $after = '';
								$after_shortened = false;

								// Offsets are in bytes, so need to use `strlen()` and `substr()` before using `safe_substr()`.
								if ( $before_context && $offset && ! $append_next ) {
									$before = \cli\safe_substr( substr( $col_val, $last_offset, $offset - $last_offset ), -$before_context, null /*length*/, false /*is_width*/, $col_encoding );
								}
								if ( $after_context ) {
									$end_offset = $offset + strlen( $match );
									$after = \cli\safe_substr( substr( $col_val, $end_offset ), 0, $after_context, false /*is_width*/, $col_encoding );
									// To lessen context duplication in output, shorten the after context if it overlaps with the next match.
									if ( $i + 1 < $match_cnt && $end_offset + strlen( $after ) > $matches[0][ $i + 1 ][1] ) {
										$after = substr( $after, 0, $matches[0][ $i + 1 ][1] - $end_offset );
										$after_shortened = true;
										// On the next iteration, will append with no before context.
									}
								}
								if ( $append_next ) {
									$cnt = count( $bits );
									$bits[ $cnt - 1 ] .= $log . $after;
								} else {
									$bits[] = $before . $log . $after;
								}
								$append_next = $after_shortened;
								$last_offset = $offset;
							}
							$match_count += $match_cnt;
							$col_val = implode( ' [...] ', $bits );

							WP_CLI::log( $matches_only ? $col_val : ( $one_line ? "{$table_column_val}:{$pk_val}{$col_val}" : "{$pk_val}{$col_val}" ) );
						}
					}
				}
			}
		}

		if ( $stats ) {
			$table_count = count( $tables );
			$skipped_count = count( $skipped );
			$match_str = 1 === $match_count ? 'match' : 'matches';
			$table_str = 1 === $table_count ? 'table' : 'tables';
			$column_str = 1 === $column_count ? 'column' : 'columns';
			$row_str = 1 === $row_count ? 'row' : 'rows';
			$skipped_str = 1 === $skipped_count ? 'table skipped' : 'tables skipped';
			if ( 0 !== $skipped_count ) {
				$skipped_str .= ': ' . implode( ', ', $skipped );
			}
			$end_time = microtime( true );
			$run_time = $end_time - $start_run_time;
			$search_time = $end_time - $start_search_time;
			$stats_msg = sprintf(
				"Found %d %s in %.3fs (%.3fs searching). Searched %d %s, %d %s, %d %s. %d %s.",
				$match_count, $match_str, $run_time, $search_time, $table_count, $table_str, $column_count, $column_str, $row_count, $row_str, $skipped_count, $skipped_str
			);
			WP_ClI::success( $stats_msg );
		}
	}

	/**
	 * Displays information about a given table.
	 *
	 * ## OPTIONS
	 *
	 * [<table>]
	 * : Name of the database table.
	 *
	 * [--format]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp db columns wp_posts
	 *     +-----------------------+---------------------+------+-----+---------------------+----------------+
	 *     |         Field         |        Type         | Null | Key |       Default       |     Extra      |
	 *     +-----------------------+---------------------+------+-----+---------------------+----------------+
	 *     | ID                    | bigint(20) unsigned | NO   | PRI |                     | auto_increment |
	 *     | post_author           | bigint(20) unsigned | NO   | MUL | 0                   |                |
	 *     | post_date             | datetime            | NO   |     | 0000-00-00 00:00:00 |                |
	 *     | post_date_gmt         | datetime            | NO   |     | 0000-00-00 00:00:00 |                |
	 *     | post_content          | longtext            | NO   |     |                     |                |
	 *     | post_title            | text                | NO   |     |                     |                |
	 *     | post_excerpt          | text                | NO   |     |                     |                |
	 *     | post_status           | varchar(20)         | NO   |     | publish             |                |
	 *     | comment_status        | varchar(20)         | NO   |     | open                |                |
	 *     | ping_status           | varchar(20)         | NO   |     | open                |                |
	 *     | post_password         | varchar(255)        | NO   |     |                     |                |
	 *     | post_name             | varchar(200)        | NO   | MUL |                     |                |
	 *     | to_ping               | text                | NO   |     |                     |                |
	 *     | pinged                | text                | NO   |     |                     |                |
	 *     | post_modified         | datetime            | NO   |     | 0000-00-00 00:00:00 |                |
	 *     | post_modified_gmt     | datetime            | NO   |     | 0000-00-00 00:00:00 |                |
	 *     | post_content_filtered | longtext            | NO   |     |                     |                |
	 *     | post_parent           | bigint(20) unsigned | NO   | MUL | 0                   |                |
	 *     | guid                  | varchar(255)        | NO   |     |                     |                |
	 *     | menu_order            | int(11)             | NO   |     | 0                   |                |
	 *     | post_type             | varchar(20)         | NO   | MUL | post                |                |
	 *     | post_mime_type        | varchar(100)        | NO   |     |                     |                |
	 *     | comment_count         | bigint(20)          | NO   |     | 0                   |                |
	 *     +-----------------------+---------------------+------+-----+---------------------+----------------+
	 *
	 * @when after_wp_load
	 */
	public function columns( $args, $assoc_args ) {
		global $wpdb;

		$format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format' );

		WP_CLI\Utils\wp_get_table_names( array( $args[0] ), array() );

		$columns = $wpdb->get_results(
			'SHOW COLUMNS FROM ' . $args[0]
		);

		$formatter_fields = array( 'Field', 'Type', 'Null', 'Key', 'Default', 'Extra' );
		$formatter_args   = array(
			'format' => $format,
		);

		$formatter = new Formatter( $formatter_args, $formatter_fields );
		$formatter->display_items( $columns );
	}

	private static function get_create_query() {

		$create_query = sprintf( 'CREATE DATABASE %s', self::esc_sql_ident( DB_NAME ) );
		if ( defined( 'DB_CHARSET' ) && constant( 'DB_CHARSET' ) ) {
			$create_query .= sprintf( ' DEFAULT CHARSET %s', self::esc_sql_ident( DB_CHARSET ) );
		}
		if ( defined( 'DB_COLLATE' ) && constant( 'DB_COLLATE' ) ) {
			$create_query .= sprintf( ' DEFAULT COLLATE %s', self::esc_sql_ident( DB_COLLATE ) );
		}
		return $create_query;
	}

	private static function run_query( $query, $assoc_args = array() ) {
		self::run( '/usr/bin/env mysql --no-defaults --no-auto-rehash', array_merge( $assoc_args, array( 'execute' => $query ) ) );
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

		// Using 'dbuser' as option name to workaround clash with WP-CLI's global WP 'user' parameter, with 'dbpass' also available for tidyness.
		if ( isset( $assoc_args['dbuser'] ) ) {
			$required['user'] = $assoc_args['dbuser'];
			unset( $assoc_args['dbuser'] );
		}
		if ( isset( $assoc_args['dbpass'] ) ) {
			$required['pass'] = $assoc_args['dbpass'];
			unset( $assoc_args['dbpass'], $assoc_args['password'] );
		}

		$final_args = array_merge( $assoc_args, $required );
		Utils\run_mysql_command( $cmd, $final_args, $descriptors );
	}

	/**
	 * Helper to pluck 'dbuser' and 'dbpass' from associative args array.
	 *
	 * @param array $assoc_args Associative args array.
	 * @return array Array with `dbuser' and 'dbpass' set if in passed-in associative args array.
	 */
	private static function get_dbuser_dbpass_args( $assoc_args ) {
		$mysql_args = array();
		if ( null !== ( $dbuser = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dbuser' ) ) ) {
			$mysql_args['dbuser'] = $dbuser;
		}
		if ( null !== ( $dbpass = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dbpass' ) ) ) {
			$mysql_args['dbpass'] = $dbpass;
		}
		return $mysql_args;
	}

	/**
	 * Gets the column names of a db table differentiated into key columns and text columns and all columns.
	 *
	 * @param string $table The table name.
	 * @return array A 3 element array consisting of an array of primary key column names, an array of text column names, and an array containing all column names.
	 */
	private static function get_columns( $table ) {
		global $wpdb;

		$table_sql = self::esc_sql_ident( $table );
		$primary_keys = $text_columns = $all_columns = array();
		$suppress_errors = $wpdb->suppress_errors();
		if ( ( $results = $wpdb->get_results( "DESCRIBE $table_sql" ) ) ) {
			foreach ( $results as $col ) {
				if ( 'PRI' === $col->Key ) {
					$primary_keys[] = $col->Field;
				}
				if ( self::is_text_col( $col->Type ) ) {
					$text_columns[] = $col->Field;
				}
				$all_columns[] = $col->Field;
			}
		}
		$wpdb->suppress_errors( $suppress_errors );
		return array( $primary_keys, $text_columns, $all_columns );
	}

	/**
	 * Determines whether a column is considered text or not.
	 *
	 * @param string Column type.
	 * @bool True if text column, false otherwise.
	 */
	private static function is_text_col( $type ) {
		foreach ( array( 'text', 'varchar' ) as $token ) {
			if ( false !== strpos( $type, $token ) )
				return true;
		}

		return false;
	}

	/**
	 * Escapes (backticks) MySQL identifiers (aka schema object names) - i.e. column names, table names, and database/index/alias/view etc names.
	 * See https://dev.mysql.com/doc/refman/5.5/en/identifiers.html
	 *
	 * @param string|array $idents A single identifier or an array of identifiers.
	 * @return string|array An escaped string if given a string, or an array of escaped strings if given an array of strings.
	 */
	private static function esc_sql_ident( $idents ) {
		$backtick = function ( $v ) {
			// Escape any backticks in the identifier by doubling.
			return '`' . str_replace( '`', '``', $v ) . '`';
		};
		if ( is_string( $idents ) ) {
			return $backtick( $idents );
		}
		return array_map( $backtick, $idents );
	}

	/**
	 * Gets the color codes from the options if any, and returns the passed in array colorized with 2 elements per entry, a color code (or '') and a reset (or '').
	 *
	 * @param array $assoc_args The associative argument array passed to the command.
	 * @param array $colors Array of default percent color code strings keyed by the 3 color contexts 'table_column', 'id', 'match'.
	 * @return array Array containing 3 2-element arrays.
	 */
	private function get_colors( $assoc_args, $colors ) {
		$color_reset = WP_CLI::colorize( '%n' );

		$color_codes = implode( '', array_map( function ( $v ) {
			return substr( $v, 1 );
		}, array_keys( \cli\Colors::getColors() ) ) );

		$color_codes_regex = '/^(?:%[' . $color_codes . '])*$/';

		foreach ( array_keys( $colors ) as $color_col ) {
			if ( false !== ( $col_color_flag = \WP_CLI\Utils\get_flag_value( $assoc_args, $color_col . '_color', false ) ) ) {
				if ( ! preg_match( $color_codes_regex, $col_color_flag, $matches ) ) {
					WP_CLI::warning( "Unrecognized percent color code '$col_color_flag' for '{$color_col}_color'." );
				} else {
					$colors[ $color_col ] = $matches[0];
				}
			}
			$colors[ $color_col ] = $colors[ $color_col ] ? array( WP_CLI::colorize( $colors[ $color_col ] ), $color_reset ) : array( '', '' );
		}

		return $colors;
	}
}
