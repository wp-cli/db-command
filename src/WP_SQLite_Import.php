<?php

class WP_SQLite_Import extends WP_SQLite_Base {

	protected $unsupported_arguments = [
		'skip-optimization',
		'defaults',
		'fields',
		'dbuser',
		'dbpass',
	];

	/**
	 * Execute the import command for SQLite.
	 *
	 * @throws Exception
	 */
	public function run( $sql_file_path, $args ) {
		$this->check_arguments( $args );
		$this->load_dependencies();
		$translator = new WP_SQLite_Translator();
		foreach ( $this->parse_statements( $sql_file_path ) as $statement ) {
			$result = $translator->query($statement);
			if ( $result === false ) {
				WP_CLI::warning( "Could not execute statement: " . $statement );
			}
		}
	}

	/**
	 * Parse SQL statements from an SQL dump file.
	 * @param string $sql_file_path The path to the SQL dump file.
	 *
	 * @return Generator A generator that yields SQL statements.
	 * @throws Exception
	 */
	public function parse_statements( $sql_file_path ) {
		$handle = fopen($sql_file_path, 'r');
		if (!$handle) {
			throw new Exception("Unable to open file: $sql_file_path");
		}

		$single_quotes = 0;
		$double_quotes = 0;
		$in_comment = false;
		$buffer = '';

		while (($line = fgets($handle)) !== false) {
			$line = trim($line);

			// Skip empty lines and comments
			if (empty($line) || strpos($line, '--') === 0 || strpos($line, '#') === 0) {
				continue;
			}

			// Handle multi-line comments
			if (! $in_comment && strpos($line, '/*') === 0) {
				$in_comment = true;
			}
			if ($in_comment) {
				if (strpos($line, '*/') !== false) {
					$in_comment = false;
				}
				continue;
			}

			for ($i = 0; $i < strlen($line); $i++) {
				$ch = $line[$i];

				// Handle escaped characters
				if ($i > 0 && $line[$i - 1] == '\\') {
					$buffer .= $ch;
					continue;
				}

				// Handle quotes
				if ($ch == "'" && $double_quotes == 0) {
					$single_quotes = 1 - $single_quotes;
				}
				if ($ch == '"' && $single_quotes == 0) {
					$double_quotes = 1 - $double_quotes;
				}

				// Process statement end
				if ($ch == ';' && $single_quotes == 0 && $double_quotes == 0) {
					yield trim($buffer);
					$buffer = '';
				} else {
					$buffer .= $ch;
				}
			}
		}

		// Handle any remaining buffer content
		if (!empty($buffer)) {
			yield trim($buffer);
		}

		fclose($handle);
	}
}
