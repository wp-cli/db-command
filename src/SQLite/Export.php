<?php
namespace WP_CLI\DB\SQLite;

use Exception;
use PDO;
use WP_CLI;
use WP_SQLite_Translator;

class Export extends Base {

	/**
	 * List of arguments that are not supported by the export command.
	 * @var string[]
	 */
	protected $unsupported_arguments = [
		'fields',
		'include-tablespaces',
		'defaults',
		'dbuser',
		'dbpass',
	];

	protected $translator;
	protected $args      = array();
	protected $is_stdout = false;

	public function __construct() {
		$this->load_dependencies();
		$this->translator = new WP_SQLite_Translator();
	}

	/**
	 * Run the export command.
	 *
	 * @param string $result_file The file to write the exported data to.
	 * @param array  $args        The arguments passed to the command.
	 *
	 * @return void
	 * @throws Exception
	 */
	public function run( $result_file, $args ) {
		$this->args = $args;
		$this->check_arguments( $args );

		$handle = $this->open_output_stream( $result_file );

		$this->write_sql_statements( $handle );
		$this->close_output_stream( $handle );

		$this->display_result_message( $result_file );
	}

	/**
	 * Get output stream for the export.
	 *
	 * @param $result_file
	 *
	 * @return false|resource
	 * @throws WP_CLI\ExitException
	 */
	protected function open_output_stream( $result_file ) {
		$this->is_stdout = '-' === $result_file;
		$handle          = $this->is_stdout ? fopen( 'php://stdout', 'w' ) : fopen( $result_file, 'w' );
		if ( ! $handle ) {
			WP_CLI::error( "Unable to open file: $result_file" );
		}
		return $handle;
	}

	/**
	 * Close the output stream.
	 *
	 * @param $handle
	 *
	 * @return void
	 * @throws WP_CLI\ExitException
	 */
	protected function close_output_stream( $handle ) {
		if ( ! fclose( $handle ) ) {
			WP_CLI::error( 'Error closing output stream.' );
		}
	}

	/**
	 * Write SQL statements to the output stream.
	 *
	 * @param resource $handle The output stream.
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function write_sql_statements( $handle ) {
		foreach ( $this->get_sql_statements() as $statement ) {
			fwrite( $handle, $statement . PHP_EOL );
		}

		fwrite( $handle, sprintf( '-- Dump completed on %s', gmdate( 'c' ) ) );
	}

	/**
	 * Get SQL statements for the dump.
	 *
	 * @return \Generator
	 * @throws Exception
	 */
	protected function get_sql_statements() {
		$include_tables = $this->get_include_tables();
		$exclude_tables = $this->get_exclude_tables();
		$translator     = $this->translator;
		foreach ( $translator->query( 'SHOW TABLES' ) as $table ) {

			// Skip tables that are not in the include_tables list if the list is defined
			if ( ! empty( $include_tables ) && ! in_array( $table->name, $include_tables, true ) ) {
				continue;
			}

			// Skip tables that are in the exclude_tables list
			if ( in_array( $table->name, $exclude_tables, true ) ) {
				continue;
			}

			yield sprintf( 'DROP TABLE IF EXISTS `%s`;', $table->name );
			yield $this->get_create_statement( $table, $translator );

			foreach ( $this->get_insert_statements( $table, $translator->get_pdo() ) as $insert_statement ) {
				yield $insert_statement;
			}
		}
	}

	/**
	 * Get the CREATE TABLE statement for a table.
	 *
	 * @param $table
	 * @param $translator
	 *
	 * @return mixed
	 */
	protected function get_create_statement( $table, $translator ) {
		$create = $translator->query( 'SHOW CREATE TABLE ' . $table->name );
		return $create[0]->{'Create Table'};
	}

	/**
	 * Get the INSERT statements for a table.
	 *
	 * @param $table
	 * @param $pdo
	 *
	 * @return \Generator
	 */
	protected function get_insert_statements( $table, $pdo ) {
		$stmt = $pdo->prepare( 'SELECT * FROM ' . $table->name );
		$stmt->execute();
		// phpcs:ignore
		while ( $row = $stmt->fetch( PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT ) ) {
			yield sprintf( 'INSERT INTO `%1s` VALUES (%2s);', $table->name, $this->escape_values( $pdo, $row ) );
		}
	}

	/**
	 * Get the tables to exclude from the export.
	 *
	 * @return array|false|string[]
	 */
	protected function get_exclude_tables() {
		$exclude_tables = isset( $this->args['exclude_tables'] ) ? explode( ',', $this->args['exclude_tables'] ) : [];
		return array_merge(
			$exclude_tables,
			[
				'_mysql_data_types_cache',
				'sqlite_master',
				'sqlite_sequence',
			]
		);
	}

	protected function display_result_message( $result_file ) {
		if ( $this->is_stdout ) {
			return;
		}

		if ( isset( $this->args['porcelain'] ) ) {
			WP_CLI::line( $result_file );
		} else {
			WP_CLI::success( 'Export complete. File written to ' . $result_file );
		}
	}

	/**
	 * Get the tables to include in the export.
	 *
	 * @return array|false|string[]
	 */
	protected function get_include_tables() {
		return isset( $this->args['tables'] ) ? explode( ',', $this->args['tables'] ) : [];
	}

	/**
	 * Escape values for insert statement
	 *
	 * @param PDO $pdo
	 * @param $values
	 *
	 * @return string
	 */
	protected function escape_values( PDO $pdo, $values ) {
		// Get a mysql PDO instance
		$escaped_values = [];
		foreach ( $values as $value ) {
			if ( is_null( $value ) ) {
				$escaped_values[] = 'NULL';
			} elseif ( is_numeric( $value ) ) {
				$escaped_values[] = $value;
			} else {
				$escaped_values[] = $pdo->quote( $value );
			}
		}
		return implode( ',', $escaped_values );
	}
}
