Feature: Create a new database

  @require-mysql-or-mariadb
  Scenario: Create a new database
    Given an empty directory
    And WP files
    And wp-config.php

    When I run `wp db create`
    Then STDOUT should contain:
      """
      Success: Database created.
      """

  @require-sqlite
  Scenario: SQLite DB create operation should fail if already existing
    Given a WP install

    When I try `wp db create`
    Then the return code should be 1
    And STDERR should contain:
      """
      Database already exists
      """
