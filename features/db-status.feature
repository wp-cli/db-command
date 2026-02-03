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

  Scenario: Run db status with MySQL defaults to check the database
    Given a WP install

    When I run `wp db status --defaults`
    Then STDOUT should contain:
      """
      Database Name:
      """
    And STDOUT should contain:
      """
      Check Status:      OK
      """

  Scenario: Run db status with --no-defaults to check the database
    Given a WP install

    When I run `wp db status --no-defaults`
    Then STDOUT should contain:
      """
      Database Name:
      """
    And STDOUT should contain:
      """
      Check Status:      OK
      """

  Scenario: Run db status with passed-in options
    Given a WP install

    When I run `wp db status --dbuser=wp_cli_test`
    Then STDOUT should contain:
      """
      Database Name:
      """
    And STDOUT should contain:
      """
      Check Status:      OK
      """

    When I run `wp db status --dbpass=password1`
    Then STDOUT should contain:
      """
      Database Name:
      """
    And STDOUT should contain:
      """
      Check Status:      OK
      """

    When I run `wp db status --dbuser=wp_cli_test --dbpass=password1`
    Then STDOUT should contain:
      """
      Database Name:
      """
    And STDOUT should contain:
      """
      Check Status:      OK
      """

    When I try `wp db status --dbuser=no_such_user`
    Then the return code should not be 0

    When I try `wp db status --dbpass=no_such_pass`
    Then the return code should not be 0

