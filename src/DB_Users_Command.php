<?php

use WP_CLI\Utils;

/**
 * Manages MySQL database users.
 *
 * ## EXAMPLES
 *
 *     # Create a new database user with privileges.
 *     $ wp db users create myuser myhost --password=mypass --grant-privileges
 *     Success: Database user 'myuser'@'myhost' created with privileges.
 *
 * @when after_wp_config_load
 */
class DB_Users_Command extends DB_Command {

	/**
	 * Creates a new database user with optional privileges.
	 *
	 * Creates a MySQL database user account and optionally grants full privileges
	 * to the current database specified in wp-config.php.
	 *
	 * ## OPTIONS
	 *
	 * <username>
	 * : MySQL username for the new user account.
	 *
	 * [<host>]
	 * : MySQL host for the new user account.
	 * ---
	 * default: localhost
	 * ---
	 *
	 * [--password=<password>]
	 * : Password for the new user account. If not provided, MySQL will use no password.
	 *
	 * [--grant-privileges]
	 * : Grant full privileges on the current database to the new user.
	 *
	 * [--dbuser=<value>]
	 * : Username to connect as (privileged user). Defaults to DB_USER.
	 *
	 * [--dbpass=<value>]
	 * : Password to connect with (privileged user). Defaults to DB_PASSWORD.
	 *
	 * [--defaults]
	 * : Loads the environment's MySQL option files. Default behavior is to skip loading them to avoid failures due to misconfiguration.
	 *
	 * ## EXAMPLES
	 *
	 *     # Create a user without privileges.
	 *     $ wp db users create myuser localhost --password=mypass
	 *     Success: Database user 'myuser'@'localhost' created.
	 *
	 *     # Create a user with full privileges on the current database.
	 *     $ wp db users create appuser localhost --password=secret123 --grant-privileges
	 *     Success: Database user 'appuser'@'localhost' created with privileges on database 'wp_database'.
	 */
	public function create( $args, $assoc_args ) {
		list( $username, $host ) = array_pad( $args, 2, 'localhost' );

		$password         = Utils\get_flag_value( $assoc_args, 'password', '' );
		$grant_privileges = Utils\get_flag_value( $assoc_args, 'grant-privileges', false );

		// Escape identifiers for SQL
		$username_escaped = self::esc_sql_ident( $username );
		$host_escaped     = self::esc_sql_ident( $host );
		assert( is_string( $username_escaped ) );
		assert( is_string( $host_escaped ) );
		$user_identifier = "{$username_escaped}@{$host_escaped}";

		// Create user
		$create_query = "CREATE USER {$user_identifier}";
		if ( ! empty( $password ) ) {
			$password_escaped = $this->esc_sql_string( $password );
			$create_query    .= " IDENTIFIED BY {$password_escaped}";
		}
		$create_query .= ';';

		parent::run_query( $create_query, $assoc_args );

		// Grant privileges if requested
		if ( $grant_privileges ) {
			$database         = DB_NAME;
			$database_escaped = self::esc_sql_ident( $database );
			assert( is_string( $database_escaped ) );
			$grant_query = "GRANT ALL PRIVILEGES ON {$database_escaped}.* TO {$user_identifier};";
			parent::run_query( $grant_query, $assoc_args );

			// Flush privileges
			parent::run_query( 'FLUSH PRIVILEGES;', $assoc_args );

			WP_CLI::success( "Database user '{$username}'@'{$host}' created with privileges on database '{$database}'." );
		} else {
			WP_CLI::success( "Database user '{$username}'@'{$host}' created." );
		}
	}

	/**
	 * Escapes a string for use in a SQL query.
	 *
	 * @param string $value String to escape.
	 * @return string Escaped string.
	 */
	private function esc_sql_string( $value ) {
		// Escape backslashes first, then single quotes.
		$value = str_replace( '\\', '\\\\', $value );
		$value = str_replace( "'", "''", $value );
		return "'" . $value . "'";
	}
}
