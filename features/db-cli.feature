Feature: Open a MySQL console

  @require-sqlite
  Scenario: SQLite commands that show warnings for cli
    Given a WP install

    When I run `wp db cli`
    Then STDOUT should contain:
      """
      Warning: Interactive console (cli) is not supported for SQLite databases
      """
