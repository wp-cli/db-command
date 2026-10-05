@require-sqlite
Feature: Select the configured SQLite database for file operations

  Scenario Outline: Use the effective database path without changing another database
    Given a WP install
    When I run `wp eval 'copy( defined( "DB_PATH" ) ? DB_PATH : FQDB, "selected.sqlite" ); copy( "selected.sqlite", "other.sqlite" );'`
    And I run `sqlite3 selected.sqlite "CREATE TABLE path_marker (value TEXT); INSERT INTO path_marker VALUES ('selected');"`
    And I run `sqlite3 other.sqlite "CREATE TABLE path_marker (value TEXT); INSERT INTO path_marker VALUES ('other');"`

    Given a paths.php file:
      """
      <?php
      if ( '' !== '<db_path>' ) {
          define( 'DB_PATH', __DIR__ . '/<db_path>' );
      }
      if ( '' !== '<fqdb>' ) {
          define( 'FQDB', __DIR__ . '/<fqdb>' );
      }
      if ( '' !== '<db_dir>' ) {
          define( 'DB_DIR', __DIR__ . '/<db_dir>' );
      }
      if ( '' !== '<db_file>' ) {
          define( 'DB_FILE', '<db_file>' );
      }
      if ( '' !== '<fqdbdir>' ) {
          define( 'FQDBDIR', __DIR__ . '/<fqdbdir>' );
      }
      """
    When I run `wp config set path_constants "require __DIR__ . '/paths.php'" --type=variable --raw`
    And I try `wp eval 'echo "ready";'`
    Then the return code should be 0

    When I run `php -r "echo md5_file( 'other.sqlite' );"`
    Then save STDOUT as {OTHER_HASH}

    When I try `wp db export selected.sql`
    Then the return code should be 0
    And the selected.sql file should contain:
      """
      INSERT INTO path_marker VALUES('selected');
      """
    And the selected.sql file should not contain:
      """
      INSERT INTO path_marker VALUES('other');
      """

    When I try `wp db drop --yes`
    Then the return code should be 0
    And the selected.sqlite file should not exist

    When I run `php -r "echo md5_file( 'other.sqlite' );"`
    Then STDOUT should be:
      """
      {OTHER_HASH}
      """

    Examples:
      | db_path         | fqdb            | db_dir | db_file         | fqdbdir |
      | selected.sqlite | other.sqlite    | other  | other.sqlite    | other/  |
      | selected.sqlite | selected.sqlite | /      | selected.sqlite | /       |
      |                 | selected.sqlite |        |                 |         |
      |                 |                 |        | selected.sqlite | /       |
