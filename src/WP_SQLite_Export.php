<?php

class WP_SQLite_Export extends WP_SQLite_Base {


	public function run() {
		$this->load_dependencies();
		WP_CLI::line( 'Exporting database...' );

		$translator = new WP_SQLite_Translator();

		$result = $translator->query('SHOW TABLES ');
		$pdo = $translator->get_pdo();

		// Stream into a file
		$handle = fopen( 'export.sql', 'w' );

		foreach ( $result as $table ) {

			$ignore_tables = [
				'_mysql_data_types_cache',
				'sqlite_master',
				'sqlite_sequence',
			];

			if ( in_array( $table->name, $ignore_tables ) ) {
				continue;
			}

			$create = $translator->query('SHOW CREATE TABLE ' . $table->name );
			var_dump($create);
			fwrite($handle, "DROP TABLE IF EXISTS `" . $table->name ."`;\n");
			fwrite( $handle, $create[0]->{'Create Table'} . "\n" );

			$stmt = $pdo->prepare('SELECT * FROM ' . $table->name );
			$stmt->execute();
			while ( $row = $stmt->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT) ) {
				// Process the row here
				// Rows are fetched in batches from the server
				$insert_statement = sprintf("INSERT INTO `%1s` VALUES (%2s);", $table->name, $this->escape_values( $pdo, $row ));

				fwrite( $handle, $insert_statement . "\n" );
			}
		}

		fclose( $handle );
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
