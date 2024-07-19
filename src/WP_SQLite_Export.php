<?php

class WP_SQLite_Export extends WP_SQLite_Base {


	private $unsupported_arguments = [
		'fields',
		'include-tablespaces',
		'defaults',
		'db_user',
		'db_pass',
		'tables',
		'exclude-tables'
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

		if ( array_intersect_key( $args, array_flip( $this->unsupported_arguments ) ) ) {
			WP_CLI::error(
				sprintf(
					'The following arguments are not supported by SQLite exports: %s',
					implode( ', ', $this->unsupported_arguments )
				)
			);
			return;
		}

		$this->load_dependencies();
		$translator = new WP_SQLite_Translator();
		$handle     = fopen( 'export.sql', 'w' );

		foreach ( $translator->query('SHOW TABLES') as $table ) {

			$ignore_tables = [
				'_mysql_data_types_cache',
				'sqlite_master',
				'sqlite_sequence',
			];

			if ( in_array( $table->name, $ignore_tables ) ) {
				continue;
			}

			fwrite($handle, "DROP TABLE IF EXISTS `" . $table->name ."`;\n");
			fwrite( $handle, $this->get_create_statement( $table, $translator ) . "\n" );

			foreach( $this->get_insert_statements( $table, $translator->get_pdo() ) as $insert_statement ) {
				fwrite( $handle, $insert_statement . "\n" );
			}
		}

		if ( $args['porcelain'] ) {
			WP_CLI::line( $result_file );
		} else {
			WP_CLI::line( 'Export complete. File written to ' . $result_file );
		}

		fclose( $handle );
	}

	protected function get_create_statement( $table, $translator ) {
		$create = $translator->query('SHOW CREATE TABLE ' . $table->name );
		return $create[0]->{'Create Table'};
	}

	protected function get_insert_statements( $table, $pdo ) {
		$stmt = $pdo->prepare('SELECT * FROM ' . $table->name );
		$stmt->execute();
		while ( $row = $stmt->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT) ) {
			yield sprintf("INSERT INTO `%1s` VALUES (%2s);", $table->name, $this->escape_values( $pdo, $row ));
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
		foreach( $values as $value ) {
			if( is_null( $value ) ) {
				$escaped_values[] = 'NULL';
			} elseif ( is_numeric( $value ) ) {
				$escaped_values[] = $value;
			} else {
				$escaped_values[] = $pdo->quote( $value );
			}
		}
		return implode(",", $escaped_values );
	}

}
