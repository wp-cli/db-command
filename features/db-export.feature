Feature: Export a WordPress database

  Scenario: Database exports with random hash applied
    Given a WP install

    When I run `wp db export --porcelain`
    Then STDOUT should contain:
      """
      wp_cli_test-
      """
    And the wp_cli_test.sql file should not exist

  Scenario: Database export to a specified file path
    Given a WP install

    When I run `wp db export wp_cli_test.sql --porcelain`
    Then STDOUT should contain:
      """
      wp_cli_test.sql
      """
    And the wp_cli_test.sql file should exist

  @require-mysql-or-mariadb
  Scenario: Exclude tables when exporting the database
    Given a WP install

    When I try `wp db export wp_cli_test.sql --exclude_tables=wp_users --porcelain`
    Then the wp_cli_test.sql file should exist
    And the contents of the wp_cli_test.sql file should not match /CREATE TABLE ["`]?wp_users["`]?/
    And the contents of the wp_cli_test.sql file should match /CREATE TABLE ["`]?wp_options["`]?/

  @require-mysql-or-mariadb
  Scenario: Include only specific tables when exporting the database
    Given a WP install

    When I try `wp db export wp_cli_test.sql --tables=wp_users --porcelain`
    Then the wp_cli_test.sql file should exist
    And the contents of the wp_cli_test.sql file should match /CREATE TABLE ["`]?wp_users["`]?/
    And the contents of the wp_cli_test.sql file should not match /CREATE TABLE ["`]?wp_posts["`]?/
    And the contents of the wp_cli_test.sql file should not match /CREATE TABLE ["`]?wp_options["`]?/

  @require-sqlite
  Scenario: Exclude tables when exporting the database
    Given a WP install

    When I try `wp db export wp_cli_test.sql --exclude_tables=wp_users --porcelain`
    Then the wp_cli_test.sql file should exist
    And the contents of the wp_cli_test.sql file should not match /_mysql_data_types_cache/
    And the contents of the wp_cli_test.sql file should not match /CREATE TABLE IF NOT EXISTS ["`]?wp_users["`]?/
    And the contents of the wp_cli_test.sql file should match /CREATE TABLE IF NOT EXISTS ["`]?wp_options["`]?/

  @require-sqlite
  Scenario: Include only specific tables when exporting the database
    Given a WP install

    When I try `wp db export wp_cli_test.sql --tables=wp_users --porcelain`
    Then the wp_cli_test.sql file should exist
    And the contents of the wp_cli_test.sql file should not match /_mysql_data_types_cache/
    And the contents of the wp_cli_test.sql file should match /CREATE TABLE IF NOT EXISTS ["`]?wp_users["`]?/
    And the contents of the wp_cli_test.sql file should not match /CREATE TABLE IF NOT EXISTS ["`]?wp_posts["`]?/
    And the contents of the wp_cli_test.sql file should not match /CREATE TABLE IF NOT EXISTS ["`]?wp_options["`]?/

  @require-sqlite
  Scenario: Export database to STDOUT
    Given a WP install

    When I run `wp db export -`
    Then STDOUT should contain:
      """
      PRAGMA foreign_keys=OFF
      """

  @require-mysql-or-mariadb
  Scenario: Export database to STDOUT
    Given a WP install

    When I run `wp db export -`
    Then STDOUT should contain:
      """
      -- Dump completed on
      """
  @require-mysql-or-mariadb
  Scenario: Export database with mysql defaults to STDOUT
    Given a WP install

    When I run `wp db export --defaults -`
    Then STDOUT should contain:
      """
      -- Dump completed on
      """

  @require-mysql-or-mariadb
  Scenario: Export database with mysql --no-defaults to STDOUT
    Given a WP install

    When I run `wp db export --no-defaults -`
    Then STDOUT should contain:
      """
      -- Dump completed on
      """

  @require-mysql-or-mariadb
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

  @require-mysql-or-mariadb
  Scenario: MySQL defaults are available as appropriate with --defaults flag
    Given a WP install

    When I try `wp db export --defaults --debug`
    Then STDERR should match #Debug \(db\): Running initial shell command: /usr/bin/env (mysqldump|mariadb-dump)#

    When I try `wp db export --debug`
    Then STDERR should match #Debug \(db\): Running initial shell command: /usr/bin/env (mysqldump|mariadb-dump) --no-defaults#

    When I try `wp db export --no-defaults --debug`
    Then STDERR should match #Debug \(db\): Running initial shell command: /usr/bin/env (mysqldump|mariadb-dump) --no-defaults#
