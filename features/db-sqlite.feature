Feature: Perform database operations with SQLite

  @require-sqlite
  Scenario: SQLite DB CRUD operations
    Given a WP install with SQLite
    And a session_yes file:
      """
      y
      """

    When I run `wp db create`
    Then the return code should be 1
    And STDERR should contain:
      """
      Database already exists
      """

    When I run `wp db size`
    Then STDOUT should contain:
      """
      .ht.sqlite
      """

    When I run `wp db tables`
    Then STDOUT should contain:
      """
      wp_posts
      """

  @require-sqlite
  Scenario: SQLite DB query
    Given a WP install with SQLite

    When I run `wp db query 'SELECT COUNT(*) as total FROM wp_posts'`
    Then STDOUT should contain:
      """
      total
      """

  @require-sqlite
  Scenario: SQLite DB export/import
    Given a WP install with SQLite

    When I run `wp post list --format=count`
    Then STDOUT should contain:
      """
      1
      """

    When I run `wp db export /tmp/wp-cli-sqlite-behat.sql`
    Then STDOUT should contain:
      """
      Success: Exported
      """

    When I run `wp db reset < session_yes`
    Then STDOUT should contain:
      """
      Success: Database reset
      """

    When I run `wp db import /tmp/wp-cli-sqlite-behat.sql`
    Then STDOUT should contain:
      """
      Success: Imported
      """

    When I run `wp post list --format=count`
    Then STDOUT should contain:
      """
      1
      """

  @require-sqlite
  Scenario: SQLite commands that show warnings
    Given a WP install with SQLite

    When I run `wp db check`
    Then STDOUT should contain:
      """
      Warning: Database check is not supported for SQLite databases
      """

    When I run `wp db optimize`
    Then STDOUT should contain:
      """
      Warning: Database optimization is not supported for SQLite databases
      """

    When I run `wp db repair`
    Then STDOUT should contain:
      """
      Warning: Database repair is not supported for SQLite databases
      """

    When I run `wp db cli`
    Then STDOUT should contain:
      """
      Warning: Interactive console (cli) is not supported for SQLite databases
      """
