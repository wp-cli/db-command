Feature: Display database status overview

  Scenario: Display database status for a WordPress install
    Given a WP install

    When I run `wp db status`
    Then STDOUT should contain:
      """
      Database Name:
      """
    And STDOUT should contain:
      """
      Tables:
      """
    And STDOUT should contain:
      """
      Total Size:
      """
    And STDOUT should contain:
      """
      Prefix:            wp_
      """
    And STDOUT should contain:
      """
      Engine:
      """
    And STDOUT should contain:
      """
      Charset:
      """
    And STDOUT should contain:
      """
      Collation:
      """
    And STDOUT should contain:
      """
      Check Status:
      """

  Scenario: Verify database status shows correct database name
    Given a WP install

    When I run `wp db status`
    Then STDOUT should contain:
      """
      wp_cli_test
      """

  Scenario: Verify database status shows check status as OK
    Given a WP install

    When I run `wp db status`
    Then STDOUT should contain:
      """
      Check Status:      OK
      """
