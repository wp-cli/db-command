Feature: Optimize the database

  @require-mysql-or-mariadb
  Scenario: Run db optimize to optimize the database
    Given a WP install

    When I run `wp db optimize`
    Then STDOUT should contain:
      """
      Success: Database optimized.
      """

  @require-sqlite
  Scenario: SQLite commands that show warnings for optimize
    Given a WP install

    When I try `wp db optimize`
    Then STDERR should contain:
      """
      Warning: Database optimization is not supported for SQLite databases
      """
