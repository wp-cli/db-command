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
		$include_tables = $this->get_include_tables();
		$exclude_tables = $this->get_exclude_tables();
		foreach ( $this->translator->query( 'SHOW TABLES' ) as $table ) {
			// Skip tables that are not in the include_tables list if the list is defined
			if ( ! empty( $include_tables ) && ! in_array( $table->name, $include_tables, true ) ) {
				continue;
			}

			// Skip tables that are in the exclude_tables list
			if ( in_array( $table->name, $exclude_tables, true ) ) {
				continue;
			}

			$this->write_create_table_statement( $handle, $table->name );
			$this->write_insert_statements( $handle, $table->name );
		}

		fwrite( $handle, sprintf( '-- Dump completed on %s', gmdate( 'c' ) ) );
	}

	/**
	 * Write the create statement for a table to the output stream.
	 *
	 * @param resource $handle
	 * @param string   $table_name
	 *
	 * @throws Exception
	 */
	protected function write_create_table_statement( $handle, $table_name ) {
		$comment = $this->get_dump_comment( sprintf( 'Table structure for table `%s`', $table_name ) );
		fwrite( $handle, $comment . PHP_EOL . PHP_EOL );
		fwrite( $handle, sprintf( 'DROP TABLE IF EXISTS `%s`;', $table_name ) . PHP_EOL );
		fwrite( $handle, $this->get_create_statement( $table_name ) . PHP_EOL );
	}

	/**
	 * Write the insert statements for a table to the output stream.
	 *
	 * @param $handle
	 * @param $table_name
	 *
	 * @return void
	 */
	protected function write_insert_statements( $handle, $table_name ) {

		if ( ! $this->table_has_records( $table_name ) ) {
			return;
		}

		$comment = $this->get_dump_comment( sprintf( 'Dumping data for table `%s`', $table_name ) );
		fwrite( $handle, $comment . PHP_EOL . PHP_EOL );
		foreach ( $this->get_insert_statements( $table_name ) as $insert_statement ) {
			fwrite( $handle, $insert_statement . PHP_EOL );
		}

		fwrite( $handle, PHP_EOL );
	}

	/**
	 * Get the CREATE TABLE statement for a table.
	 *
	 * @param string $table_name
	 *
	 * @return mixed
	 * @throws Exception
	 */
	protected function get_create_statement( $table_name ) {
		$create = $this->translator->query( 'SHOW CREATE TABLE ' . $table_name );
		return $create[0]->{'Create Table'} . "\n";
	}

	/**
	 * Get the INSERT statements for a table.
	 *
	 * @param string $table_name
	 *
	 * @return \Generator
	 */
	protected function get_insert_statements( $table_name ) {
		$pdo  = $this->translator->get_pdo();
		$stmt = $pdo->prepare( 'SELECT * FROM ' . $table_name );
		$stmt->execute();
		// phpcs:ignore
		while ( $row = $stmt->fetch( PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT ) ) {
			yield sprintf( 'INSERT INTO `%1s` VALUES (%2s);', $table_name, $this->escape_values( $pdo, $row ) );
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
				// Quote the values and escape encode the newlines so the insert statement appears on a single line.
				$escaped_values[] = str_replace( "\n", "\\n", $pdo->quote( $value ) );
			}
		}
		return implode( ',', $escaped_values );
	}

	/**
	 * Get a comment for the dump.
	 *
	 * @param $comment
	 *
	 * @return string
	 */
	protected function get_dump_comment( $comment ) {
		return implode(
			"\n",
			array( '--', sprintf( '-- %s', $comment ), '--' )
		);
	}

	/**
	 * Check if the given table has records.
	 *
	 * @param string $table_name
	 *
	 * @return bool
	 */
	protected function table_has_records( $table_name ) {
		$pdo  = $this->translator->get_pdo();
		$stmt = $pdo->prepare( 'SELECT COUNT(*) FROM ' . $table_name );
		$stmt->execute();
		return $stmt->fetchColumn() > 0;
	}
}
