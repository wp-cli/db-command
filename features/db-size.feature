# Assumes wp_cli_test has a database size of around 655,360 bytes.

Feature: Display database size

  Scenario: Display database and table sizes for a WordPress install
    Given a WP install

    When I run `wp db query "SELECT SUM(data_length + index_length) FROM information_schema.TABLES where table_schema = 'wp_cli_test' GROUP BY table_schema;"`
    Then save STDOUT '(\d+)' as {DBSIZE}

    When I run `wp db size`
    Then STDOUT should contain:
      """
      wp_cli_test
      """

    And STDOUT should contain:
      """
      KB	{DBSIZE}
      """

  Scenario: Display only database size for a WordPress install
    Given a WP install

    When I run `wp db query "SELECT SUM(data_length + index_length) FROM information_schema.TABLES where table_schema = 'wp_cli_test' GROUP BY table_schema;"`
    Then save STDOUT '(\d+)' as {DBSIZE}

    When I run `wp db size --db-only`
    Then STDOUT should contain:
      """
      wp_cli_test
      """

    And STDOUT should contain:
      """
      KB	{DBSIZE}
      """

    But STDOUT should not contain:
      """
      wp_users
      """

  Scenario: Display only database size in bytes for a WordPress install
    Given a WP install

    When I run `wp db query "SELECT SUM(data_length + index_length) FROM information_schema.TABLES where table_schema = 'wp_cli_test' GROUP BY table_schema;"`
    Then save STDOUT '(\d+)' as {DBSIZE}

    When I run `wp db size --db-only --format=bytes`
    Then STDOUT should be a number

    And STDOUT should contain:
    """
    {DBSIZE}
    """
