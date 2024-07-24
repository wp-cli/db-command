<?php
namespace WP_CLI\DB;

use Exception;
use PDO;
use WP_CLI;
use WP_SQLite_Translator;

class WP_SQLite_Export extends WP_SQLite_Base {

	protected $unsupported_arguments = [
		'fields',
		'include-tablespaces',
		'defaults',
		'dbuser',
		'dbpass',
	];

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

		$this->check_arguments( $args );
		$this->load_dependencies();

		$exclude_tables = isset( $args['exclude_tables'] ) ? explode( ',', $args['exclude_tables'] ) : [];
		$exclude_tables = array_merge(
			$exclude_tables,
			[
				'_mysql_data_types_cache',
				'sqlite_master',
				'sqlite_sequence',
			]
		);

		$include_tables = isset( $args['tables'] ) ? explode( ',', $args['tables'] ) : [];

		$translator = new WP_SQLite_Translator();
		$handle     = fopen( $result_file, 'w' );

		foreach ( $translator->query( 'SHOW TABLES' ) as $table ) {

			// Skip tables that are not in the include_tables list if the list is defined
			if ( ! empty( $include_tables ) && ! in_array( $table->name, $include_tables, true ) ) {
				continue;
			}

			// Skip tables that are in the exclude_tables list
			if ( in_array( $table->name, $exclude_tables, true ) ) {
				continue;
			}

			fwrite( $handle, 'DROP TABLE IF EXISTS `' . $table->name . "`;\n" );
			fwrite( $handle, $this->get_create_statement( $table, $translator ) . "\n" );

			foreach ( $this->get_insert_statements( $table, $translator->get_pdo() ) as $insert_statement ) {
				fwrite( $handle, $insert_statement . "\n" );
			}
		}

		if ( isset( $args['porcelain'] ) ) {
			WP_CLI::line( $result_file );
		} else {
			WP_CLI::line( 'Export complete. File written to ' . $result_file );
		}

		fclose( $handle );
	}

	protected function get_create_statement( $table, $translator ) {
		$create = $translator->query( 'SHOW CREATE TABLE ' . $table->name );
		return $create[0]->{'Create Table'};
	}

	protected function get_insert_statements( $table, $pdo ) {
		$stmt = $pdo->prepare( 'SELECT * FROM ' . $table->name );
		$stmt->execute();
		// phpcs:ignore
		while ( $row = $stmt->fetch( PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT ) ) {
			yield sprintf( 'INSERT INTO `%1s` VALUES (%2s);', $table->name, $this->escape_values( $pdo, $row ) );
		}
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
