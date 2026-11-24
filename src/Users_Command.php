<?php

namespace WP_CLI\DB;

use DB_Command_SQLite;
use WP_CLI;
use WP_CLI\Utils;
use WP_CLI_Command;

/**
 * Manages MySQL and MariaDB user accounts.
 *
 * Creating users needs a database account with the `CREATE USER` privilege
 * (and `GRANT OPTION` for `--grant-privileges`), which the one in wp-config.php
 * usually doesn't have. Pass a privileged account with `--dbuser` and `--dbpass`.
 *
 * ## EXAMPLES
 *
 *     # Create a user with full privileges on the WordPress database, prompting for both passwords.
 *     $ wp db users create appuser localhost --grant-privileges --dbuser=root --prompt=dbpass
 *     1/1 [--dbpass=<value>]:
 *     Password:
 *     Success: Created database user 'appuser'@'localhost' with all privileges on 'wp_database'.
 *
 * @when after_wp_config_load
 */
class Users_Command extends WP_CLI_Command {

	use DB_Command_SQLite;

	/**
	 * Creates a database user.
	 *
	 * The statements are sent to the `mysql` client through STDIN, so the new
	 * user's password doesn't show up in the process list or in `--debug` output.
	 *
	 * ## OPTIONS
	 *
	 * <username>
	 * : Name of the user to create.
	 *
	 * [<host>]
	 * : Host the user is allowed to connect from. Use `%` for any host.
	 * ---
	 * default: localhost
	 * ---
	 *
	 * [--password=<password>]
	 * : Password for the new user. If omitted, it is read from STDIN when input is piped, and prompted for otherwise. Avoid passing it here on shared systems, as it ends up in the shell history.
	 *
	 * [--grant-privileges]
	 * : Grant the new user all privileges on the WordPress database (DB_NAME).
	 *
	 * [--dbuser=<value>]
	 * : Username to connect as. Needs the `CREATE USER` privilege. Defaults to DB_USER.
	 *
	 * [--dbpass=<value>]
	 * : Password to connect with. Defaults to DB_PASSWORD.
	 *
	 * [--defaults]
	 * : Loads the environment's MySQL option files. Default behavior is to skip loading them to avoid failures due to misconfiguration.
	 *
	 * ## EXAMPLES
	 *
	 *     # Create a user that can connect from any host, reading its password from a file.
	 *     # This connects as DB_USER, which then needs the CREATE USER privilege.
	 *     $ wp db users create appuser '%' < password.txt
	 *     Success: Created database user 'appuser'@'%'.
	 *
	 *     # Create a user with full privileges on the WordPress database.
	 *     $ wp db users create appuser localhost --grant-privileges --dbuser=root --prompt=dbpass
	 *     1/1 [--dbpass=<value>]:
	 *     Password:
	 *     Success: Created database user 'appuser'@'localhost' with all privileges on 'wp_database'.
	 */
	public function create( $args, $assoc_args ) {
		if ( $this->is_sqlite() ) {
			WP_CLI::error( 'Database users are not supported with SQLite.' );
		}

		$username = $args[0];
		$host     = isset( $args[1] ) ? $args[1] : 'localhost';

		if ( '' === $username ) {
			WP_CLI::error( 'The username cannot be empty.' );
		}

		$password = $this->get_password( $assoc_args );
		$account  = self::esc_sql_string( $username ) . '@' . self::esc_sql_string( $host );

		// Turn off NO_BACKSLASH_ESCAPES for this session, so the escaping of the
		// string literals below is correct regardless of the server's SQL mode.
		$statements = [
			"SET SESSION sql_mode = TRIM( BOTH ',' FROM REPLACE( CONCAT( ',', @@SESSION.sql_mode, ',' ), ',NO_BACKSLASH_ESCAPES,', ',' ) )",
			"CREATE USER {$account} IDENTIFIED BY " . self::esc_sql_string( $password ),
		];

		$grant = (bool) Utils\get_flag_value( $assoc_args, 'grant-privileges', false );
		if ( $grant ) {
			$statements[] = 'GRANT ALL PRIVILEGES ON ' . self::esc_grant_database( DB_NAME, $this->has_partial_revokes( $assoc_args ) ) . ".* TO {$account}";
		}

		WP_CLI::debug( "Creating database user '{$username}'@'{$host}'" . ( $grant ? ' with all privileges on ' . DB_NAME : '' ) . '.', 'db' );

		$this->run_statements( implode( ";\n", $statements ) . ";\n", $assoc_args, $password );

		if ( $grant ) {
			WP_CLI::success( "Created database user '{$username}'@'{$host}' with all privileges on '" . DB_NAME . "'." );
		} else {
			WP_CLI::success( "Created database user '{$username}'@'{$host}'." );
		}
	}

	/**
	 * Gets the new user's password from the arguments, STDIN or a prompt.
	 *
	 * @param array $assoc_args Associative arguments.
	 * @return string Password.
	 */
	private function get_password( $assoc_args ) {
		if ( isset( $assoc_args['password'] ) && true !== $assoc_args['password'] ) {
			$password = (string) $assoc_args['password'];
		} elseif ( function_exists( 'stream_isatty' ) && stream_isatty( STDIN ) ) {
			$password = (string) \cli\prompt( 'Password', false, ': ', true );
		} else {
			$password = (string) stream_get_contents( STDIN );
			// Strip the trailing newline of piped input, but nothing else.
			$password = (string) preg_replace( '/\r?\n\z/', '', $password );
		}

		if ( '' === $password ) {
			WP_CLI::error( 'A password is required. Pass it with --password, pipe it to STDIN, or enter it when prompted.' );
		}

		return $password;
	}

	/**
	 * Checks whether the server has MySQL's `partial_revokes` enabled.
	 *
	 * With it, `_` and `%` in GRANT database names are literal characters
	 * instead of wildcards. Only checked when DB_NAME contains them.
	 *
	 * @param array $assoc_args Associative arguments.
	 * @return bool
	 */
	private function has_partial_revokes( $assoc_args ) {
		if ( false === strpbrk( DB_NAME, '_%' ) ) {
			return false;
		}

		// MariaDB and MySQL before 8.0.16 have no such variable, which returns no rows.
		$output = $this->run_statements( "SHOW GLOBAL VARIABLES LIKE 'partial_revokes';\n", $assoc_args );

		return (bool) preg_match( '/^partial_revokes\s+(ON|1)\s*$/mi', $output );
	}

	/**
	 * Runs SQL statements with the `mysql` client, passing them through STDIN.
	 *
	 * @param string $sql        SQL statements to run.
	 * @param array  $assoc_args Associative arguments.
	 * @param string $secret     Optional value to keep out of error messages.
	 * @return string Output of the statements.
	 */
	private function run_statements( $sql, $assoc_args, $secret = '' ) {
		$mysql_args = [
			'user' => Utils\get_flag_value( $assoc_args, 'dbuser', DB_USER ),
		];

		// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysql_host_to_cli_args, PHPCompatibility.Extensions.RemovedExtensions.mysql_DeprecatedRemoved -- Misidentified as PHP native MySQL function.
		$mysql_args = array_merge( $mysql_args, Utils\mysql_host_to_cli_args( DB_HOST ) );

		if ( defined( 'DB_CHARSET' ) && constant( 'DB_CHARSET' ) ) {
			$mysql_args['default-character-set'] = constant( 'DB_CHARSET' );
		}

		// See DB_Command::run(): skip the certificate check like WordPress does.
		if ( 'mariadb' === Utils\get_db_type() ) {
			$mysql_args['skip-ssl-verify-server-cert'] = true;
		}

		$defaults = Utils\get_flag_value( $assoc_args, 'defaults', false ) ? '' : ' --no-defaults';
		// --binary-mode keeps the client from turning CRLF in the statements (and so in passwords) into LF.
		$command = Utils\force_env_on_nix_systems( Utils\get_mysql_binary_path() . $defaults . ' --no-auto-rehash --batch --binary-mode' )
			. Utils\assoc_args_to_str( $mysql_args );

		WP_CLI::debug( "Final MySQL command: {$command}", 'db' );

		// The connection password goes through the environment, the statements through STDIN.
		$old_password = getenv( 'MYSQL_PWD' );
		$dbpass       = Utils\get_flag_value( $assoc_args, 'dbpass', DB_PASSWORD );
		putenv( 'MYSQL_PWD=' . ( is_string( $dbpass ) ? $dbpass : '' ) );

		$pipes   = [];
		$process = Utils\proc_open_compat(
			$command,
			[
				0 => [ 'pipe', 'r' ],
				1 => [ 'pipe', 'w' ],
				2 => [ 'pipe', 'w' ],
			],
			$pipes
		);

		if ( false === $old_password ) {
			putenv( 'MYSQL_PWD' );
		} else {
			putenv( 'MYSQL_PWD=' . $old_password );
		}

		if ( ! is_resource( $process ) ) {
			WP_CLI::error( 'Could not run the mysql client.' );
		}

		fwrite( $pipes[0], $sql );
		fclose( $pipes[0] );

		$stdout = (string) stream_get_contents( $pipes[1] );
		$stderr = (string) stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );

		$exit_code = proc_close( $process );

		if ( 0 !== $exit_code ) {
			// The MariaDB client echoes a failed statement, including the new user's password,
			// before the error. Only keep the error lines, and redact the password just in case.
			$errors = preg_grep( '/^ERROR\b/', explode( "\n", str_replace( "\r\n", "\n", $stderr ) ) );
			$errors = trim( implode( "\n", $errors ? $errors : [] ) );
			if ( '' !== $secret ) {
				$errors = str_replace( [ $secret, substr( self::esc_sql_string( $secret ), 1, -1 ) ], '***', $errors );
			}
			WP_CLI::error( '' !== $errors ? $errors : "The mysql client exited with code {$exit_code}." );
		}

		return $stdout;
	}

	/**
	 * Quotes a value as an SQL string literal.
	 *
	 * Assumes backslash escapes are enabled, see create().
	 *
	 * @param string $value Value to quote.
	 * @return string Quoted value.
	 */
	private static function esc_sql_string( $value ) {
		return "'" . str_replace( [ '\\', "'" ], [ '\\\\', "''" ], $value ) . "'";
	}

	/**
	 * Quotes a database name for use in a GRANT statement.
	 *
	 * GRANT treats `_` and `%` in database names as wildcards, so they are
	 * escaped to only match this one database. With MySQL's `partial_revokes`
	 * enabled they are literal characters, and escaping them would make the
	 * name match no database.
	 *
	 * @param string $database        Database name.
	 * @param bool   $partial_revokes Whether `partial_revokes` is enabled.
	 * @return string Quoted database name.
	 */
	private static function esc_grant_database( $database, $partial_revokes = false ) {
		$database = str_replace( '`', '``', $database );
		if ( ! $partial_revokes ) {
			$database = str_replace( [ '_', '%' ], [ '\\_', '\\%' ], $database );
		}
		return '`' . $database . '`';
	}
}
