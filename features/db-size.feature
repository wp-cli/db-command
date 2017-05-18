Feature: Display database size

  Scenario: Display database size for a WordPress install, standard output
    Given a WP install

    When I run `wp db size`
    Then STDOUT should contain:
      """
      wp_cli_test
      """

    And STDOUT should contain:
      """
      640 KB
      """

    And STDOUT should contain:
      """
      655
      """

  Scenario: Display database size for a WordPress install, bytes only
    Given a WP install

    When I run `wp db size --format=bytes`
    Then STDOUT should contain:
      """
      655
      """
