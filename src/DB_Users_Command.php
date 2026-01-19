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
class DB_Users_Command extends WP_CLI_Command {

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
		$username_escaped = $this->esc_sql_ident( $username );
		$host_escaped     = $this->esc_sql_ident( $host );
		$user_identifier  = "{$username_escaped}@{$host_escaped}";

		// Create user
		$create_query = "CREATE USER {$user_identifier}";
		if ( ! empty( $password ) ) {
			$password_escaped = $this->esc_sql_string( $password );
			$create_query    .= " IDENTIFIED BY {$password_escaped}";
		}
		$create_query .= ';';

		$this->run_query( $create_query, $assoc_args );

		// Grant privileges if requested
		if ( $grant_privileges ) {
			$database         = DB_NAME;
			$database_escaped = $this->esc_sql_ident( $database );
			$grant_query      = "GRANT ALL PRIVILEGES ON {$database_escaped}.* TO {$user_identifier};";
			$this->run_query( $grant_query, $assoc_args );

			// Flush privileges
			$this->run_query( 'FLUSH PRIVILEGES;', $assoc_args );

			WP_CLI::success( "Database user '{$username}'@'{$host}' created with privileges on database '{$database}'." );
		} else {
			WP_CLI::success( "Database user '{$username}'@'{$host}' created." );
		}
	}

	/**
	 * Run a single query via the 'mysql' binary.
	 *
	 * @param string $query      Query to execute.
	 * @param array  $assoc_args Optional. Associative array of arguments.
	 */
	private function run_query( $query, $assoc_args = [] ) {
		WP_CLI::debug( "Query: {$query}", 'db' );

		$mysql_args = array_merge(
			$this->get_dbuser_dbpass_args( $assoc_args ),
			$this->get_mysql_args( $assoc_args )
		);

		$this->run(
			sprintf(
				'mysql%s --no-auto-rehash',
				$this->get_defaults_flag_string( $assoc_args )
			),
			array_merge( [ 'execute' => $query ], $mysql_args )
		);
	}

	/**
	 * Run a MySQL command.
	 *
	 * @param string $cmd        Command to run.
	 * @param array  $assoc_args Optional. Associative array of arguments to use.
	 *
	 * @return array {
	 *     Associative array containing STDOUT and STDERR output.
	 *
	 *     @type string $stdout    Output that was sent to STDOUT.
	 *     @type string $stderr    Output that was sent to STDERR.
	 *     @type int    $exit_code Exit code of the process.
	 * }
	 */
	private function run( $cmd, $assoc_args = [] ) {
		$required = [
			'host' => DB_HOST,
			'user' => DB_USER,
			'pass' => DB_PASSWORD,
		];

		if ( ! isset( $assoc_args['default-character-set'] )
			&& defined( 'DB_CHARSET' ) && constant( 'DB_CHARSET' ) ) {
			$required['default-character-set'] = constant( 'DB_CHARSET' );
		}

		// Using 'dbuser' as option name to workaround clash with WP-CLI's global WP 'user' parameter.
		if ( isset( $assoc_args['dbuser'] ) ) {
			$required['user'] = $assoc_args['dbuser'];
			unset( $assoc_args['dbuser'] );
		}
		if ( isset( $assoc_args['dbpass'] ) ) {
			$required['pass'] = $assoc_args['dbpass'];
			unset( $assoc_args['dbpass'], $assoc_args['password'] );
		}

		$final_args = array_merge( $required, $assoc_args );

		return Utils\run_mysql_command( $cmd, $final_args, null, true, false );
	}

	/**
	 * Helper to pluck 'dbuser' and 'dbpass' from associative args array.
	 *
	 * @param array $assoc_args Associative args array.
	 * @return array Array with 'dbuser' and 'dbpass' set if in passed-in associative args array.
	 */
	private function get_dbuser_dbpass_args( $assoc_args ) {
		$mysql_args = [];
		$dbuser     = Utils\get_flag_value( $assoc_args, 'dbuser' );
		if ( null !== $dbuser ) {
			$mysql_args['dbuser'] = $dbuser;
		}
		$dbpass = Utils\get_flag_value( $assoc_args, 'dbpass' );
		if ( null !== $dbpass ) {
			$mysql_args['dbpass'] = $dbpass;
		}
		return $mysql_args;
	}

	/**
	 * Gets the MySQL args from the associative args array.
	 *
	 * @param array $assoc_args Associative args array.
	 * @return array MySQL args.
	 */
	private function get_mysql_args( $assoc_args ) {
		$mysql_args = [];

		if ( isset( $assoc_args['host'] ) ) {
			$mysql_args['host'] = $assoc_args['host'];
		}

		return $mysql_args;
	}

	/**
	 * Gets the defaults flag string.
	 *
	 * @param array $assoc_args Associative args array.
	 * @return string Defaults flag string.
	 */
	private function get_defaults_flag_string( $assoc_args ) {
		$defaults = Utils\get_flag_value( $assoc_args, 'defaults', false );
		return $defaults ? '' : ' --no-defaults';
	}

	/**
	 * Escapes a string for use in a SQL query.
	 *
	 * @param string $value String to escape.
	 * @return string Escaped string.
	 */
	private function esc_sql_string( $value ) {
		// Use single quotes and escape single quotes by doubling them.
		return "'" . str_replace( "'", "''", $value ) . "'";
	}

	/**
	 * Escapes (backticks) MySQL identifiers (aka schema object names).
	 *
	 * @param string $ident A single identifier.
	 * @return string An escaped string.
	 */
	private function esc_sql_ident( $ident ) {
		// Escape any backticks in the identifier by doubling.
		return '`' . str_replace( '`', '``', $ident ) . '`';
	}
}
