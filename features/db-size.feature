# Assumes wp_cli_test has a database size of around 655,360 bytes.

Feature: Display database size

  Scenario: Display database and table sizes for a WordPress install
    Given a WP install

    When I run `wp db size`
    Then STDOUT should contain:
      """
      wp_cli_test	640 KB	655
      """

    And STDOUT should contain:
      """
      wp_terms	48 KB
      """

  Scenario: Display only database size for a WordPress install
    Given a WP install

    When I run `wp db size --db-only`
    Then STDOUT should contain:
      """
      wp_cli_test	640 KB	655
      """

    But STDOUT should not contain:
      """
      wp_terms
      """

  Scenario: Display only database size in bytes for a WordPress install
    Given a WP install

    When I run `wp db size --db-only --format=bytes`
    Then STDOUT should be a number
