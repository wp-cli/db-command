Feature: Display database status overview

  @require-mysql-or-mariadb
  Scenario: Display database status for a WordPress install
    Given a WP install

    When I run `wp db status`
    Then STDOUT should contain:
      """
      Database Name:     {DB_NAME}
      """
    And STDOUT should match /^Tables:\s+\d+$/m
    And STDOUT should match /^Total Size:\s+[\d.]+ [KM]?B$/m
    And STDOUT should contain:
      """
      Prefix:            wp_
      """
    And STDOUT should match /^Engine:\s+\w+$/m
    And STDOUT should match /^Charset:\s+\w+$/m
    And STDOUT should match /^Collation:\s+\w+$/m
    And STDOUT should contain:
      """
      Check Status:      OK
      """

    When I run `wp db status --skip-check --format=json`
    Then STDOUT should be JSON containing:
      """
      {"database":"{DB_NAME}","prefix":"wp_","check":"Skipped"}
      """
    And STDOUT should match /"tables":\d+,"size":\d+,/

  @require-mysql-or-mariadb
  Scenario: Display database status before WordPress is installed
    Given an empty directory
    And WP files
    And wp-config.php
    And a database

    When I run `wp db status`
    Then STDOUT should contain:
      """
      Database Name:     {DB_NAME}
      Tables:            0
      Total Size:        0 B
      Prefix:            wp_
      Engine:            N/A
      Charset:           N/A
      Collation:         N/A
      Check Status:      N/A
      """

  @require-mysql-or-mariadb
  Scenario: Report mixed table engines
    Given a WP install

    When I run `wp db query "CREATE TABLE wp_status_test (id INT) ENGINE=MyISAM"`
    And I run `wp db status --skip-check`
    Then STDOUT should contain:
      """
      Engine:            Mixed
      """

  @require-mysql-or-mariadb
  Scenario: Run db status with passed-in options
    Given a WP install

    When I run `wp db status --defaults`
    Then STDOUT should contain:
      """
      Check Status:      OK
      """

    When I run `wp db status --no-defaults`
    Then STDOUT should contain:
      """
      Check Status:      OK
      """

    When I run `wp db status --dbuser={DB_USER} --dbpass=password1`
    Then STDOUT should contain:
      """
      Check Status:      OK
      """

    When I try `wp db status --dbuser=no_such_user`
    Then the return code should be 1
    And STDERR should contain:
      """
      Error: Could not query the database:
      """

    When I try `wp db status --dbpass=no_such_pass`
    Then the return code should be 1
    And STDERR should contain:
      """
      Error: Could not query the database:
      """

  @require-sqlite
  Scenario: Display database status for a SQLite install
    Given a WP install

    When I run `wp db status`
    Then STDOUT should match /^Database Name:\s+\S+$/m
    And STDOUT should match /^Tables:\s+[1-9]\d*$/m
    And STDOUT should match /^Total Size:\s+[\d.]+ [KM]?B$/m
    And STDOUT should contain:
      """
      Prefix:            wp_
      Engine:            SQLite
      Charset:           N/A
      Collation:         N/A
      Check Status:      N/A
      """
