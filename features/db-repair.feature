Feature: Repair the database

  @require-mysql-or-mariadb
  Scenario: Run db repair to repair the database
    Given a WP install

    When I run `wp db repair`
    Then STDOUT should contain:
      """
      Success: Database repaired.
      """

  @require-sqlite
  Scenario: SQLite commands that show warnings for repair
    Given a WP install

    When I run `wp db repair`
    Then STDOUT should contain:
      """
      Warning: Database repair is not supported for SQLite databases
      """
