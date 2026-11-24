Feature: Export a WordPress database

  Scenario: Database exports with random hash applied
    Given a WP install

    When I run `wp db export --porcelain`
    Then STDOUT should contain:
      """
      {DB_NAME}-
      """
    And the {DB_NAME}.sql file should not exist

  Scenario: Database export to a specified file path
    Given a WP install

    When I run `wp db export {DB_NAME}.sql --porcelain`
    Then STDOUT should contain:
      """
      {DB_NAME}.sql
      """
    And the {DB_NAME}.sql file should exist

  @skip-sqlite
  Scenario: Exclude tables when exporting the database
    Given a WP install

    When I try `wp db export {DB_NAME}.sql --exclude_tables=wp_users --porcelain`
    Then the {DB_NAME}.sql file should exist
    And the contents of the {DB_NAME}.sql file should not match /CREATE TABLE ["`]?wp_users["`]?/
    And the contents of the {DB_NAME}.sql file should match /CREATE TABLE ["`]?wp_options["`]?/

  @skip-sqlite
  Scenario: Include only specific tables when exporting the database
    Given a WP install

    When I try `wp db export {DB_NAME}.sql --tables=wp_users --porcelain`
    Then the {DB_NAME}.sql file should exist
    And the contents of the {DB_NAME}.sql file should match /CREATE TABLE ["`]?wp_users["`]?/
    And the contents of the {DB_NAME}.sql file should not match /CREATE TABLE ["`]?wp_posts["`]?/
    And the contents of the {DB_NAME}.sql file should not match /CREATE TABLE ["`]?wp_options["`]?/

  @require-sqlite
  Scenario: Exclude tables when exporting the database
    Given a WP install

    When I try `wp db export {DB_NAME}.sql --exclude_tables=wp_users --porcelain`
    Then the {DB_NAME}.sql file should exist
    And the contents of the {DB_NAME}.sql file should not match /_mysql_data_types_cache/
    And the contents of the {DB_NAME}.sql file should not match /CREATE TABLE ["`]?wp_users["`]?/
    And the contents of the {DB_NAME}.sql file should match /CREATE TABLE ["`]?wp_options["`]?/

  @require-sqlite
  Scenario: Include only specific tables when exporting the database
    Given a WP install

    When I try `wp db export {DB_NAME}.sql --tables=wp_users --porcelain`
    Then the {DB_NAME}.sql file should exist
    And the contents of the {DB_NAME}.sql file should not match /_mysql_data_types_cache/
    And the contents of the {DB_NAME}.sql file should match /CREATE TABLE ["`]?wp_users["`]?/
    And the contents of the {DB_NAME}.sql file should not match /CREATE TABLE ["`]?wp_posts["`]?/
    And the contents of the {DB_NAME}.sql file should not match /CREATE TABLE ["`]?wp_options["`]?/

  @require-sqlite
  Scenario: Export database to STDOUT
    Given a WP install

    When I run `wp db export -`
    Then STDOUT should contain:
      """
      PRAGMA foreign_keys=OFF
      """

  @skip-sqlite
  Scenario: Export database to STDOUT
    Given a WP install

    When I run `wp db export -`
    Then STDOUT should contain:
      """
      -- Dump completed on
      """
  @skip-sqlite
  Scenario: Export database with mysql defaults to STDOUT
    Given a WP install

    When I run `wp db export --defaults -`
    Then STDOUT should contain:
      """
      -- Dump completed on
      """

  @skip-sqlite
  Scenario: Export database with mysql --no-defaults to STDOUT
    Given a WP install

    When I run `wp db export --no-defaults -`
    Then STDOUT should contain:
      """
      -- Dump completed on
      """

  @skip-sqlite
  Scenario: Export database with passed-in options
    Given a WP install

    When I run `wp db export - --dbpass=password1 --skip-comments`
    Then STDOUT should not contain:
      """
      -- Table structure
      """

    When I try `wp db export - --dbpass=no_such_pass`
    Then the return code should not be 0
    And STDERR should contain:
      """
      Access denied
      """
    And STDOUT should be empty

  @require-sqlite
  Scenario: Export database with passed-in options
    Given a WP install

    When I run `wp db export - --skip-comments`
    Then STDOUT should not contain:
      """
      -- Table structure
      """

    # dbpass has no effect on SQLite
    When I try `wp db export - --dbpass=no_such_pass`
    Then the return code should be 0
    And STDERR should not contain:
      """
      Access denied
      """

  @skip-sqlite
  Scenario: MySQL defaults are available as appropriate with --defaults flag
    Given a WP install

    When I try `wp db export --defaults --debug`
    Then STDERR should match #Debug \(db\): Running initial shell command: /([^/]+/)+(mysqldump|mariadb-dump)#

    When I try `wp db export --debug`
    Then STDERR should match #Debug \(db\): Running initial shell command: /([^/]+/)+(mysqldump|mariadb-dump) --no-defaults#

    When I try `wp db export --no-defaults --debug`
    Then STDERR should match #Debug \(db\): Running initial shell command: /([^/]+/)+(mysqldump|mariadb-dump) --no-defaults#

  @skip-sqlite
  @skip-windows
  Scenario: Export database when PHP exec() is disabled
    Given a WP install

    When I try `{INVOKE_WP_CLI_WITH_PHP_ARGS--ddisable_functions=exec} db export {DB_NAME}.sql --porcelain`
    Then the return code should be 0
    And STDOUT should contain:
      """
      {DB_NAME}.sql
      """
    And the {DB_NAME}.sql file should exist
