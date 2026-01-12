Feature: Quiet mode for database operations

  Scenario: db check with --quiet flag should only show errors
    Given a WP install

    When I run `wp db check --quiet`
    Then STDOUT should not contain:
      """
      wp_cli_test.wp_users
      """
    And STDOUT should be empty

  Scenario: db optimize with --quiet flag should only show errors
    Given a WP install

    When I run `wp db optimize --quiet`
    Then STDOUT should not contain:
      """
      wp_cli_test.wp_users
      """
    And STDOUT should be empty

  Scenario: db repair with --quiet flag should only show errors
    Given a WP install

    When I run `wp db repair --quiet`
    Then STDOUT should not contain:
      """
      wp_cli_test.wp_users
      """
    And STDOUT should be empty

  Scenario: db check without --quiet flag should show informational messages
    Given a WP install

    When I run `wp db check`
    Then STDOUT should contain:
      """
      wp_cli_test.wp_users
      """
    And STDOUT should contain:
      """
      Success: Database checked.
      """

  Scenario: db optimize without --quiet flag should show informational messages
    Given a WP install

    When I run `wp db optimize`
    Then STDOUT should contain:
      """
      wp_cli_test.wp_users
      """
    And STDOUT should contain:
      """
      Success: Database optimized.
      """

  Scenario: db repair without --quiet flag should show informational messages
    Given a WP install

    When I run `wp db repair`
    Then STDOUT should contain:
      """
      wp_cli_test.wp_users
      """
    And STDOUT should contain:
      """
      Success: Database repaired.
      """

  Scenario: db check can explicitly pass --silent to mysqlcheck
    Given a WP install

    When I run `wp db check --silent`
    Then STDOUT should not contain:
      """
      wp_cli_test.wp_users
      """
    And STDOUT should contain:
      """
      Success: Database checked.
      """

  Scenario: db optimize can explicitly pass --silent to mysqlcheck
    Given a WP install

    When I run `wp db optimize --silent`
    Then STDOUT should not contain:
      """
      wp_cli_test.wp_users
      """
    And STDOUT should contain:
      """
      Success: Database optimized.
      """

  Scenario: db repair can explicitly pass --silent to mysqlcheck
    Given a WP install

    When I run `wp db repair --silent`
    Then STDOUT should not contain:
      """
      wp_cli_test.wp_users
      """
    And STDOUT should contain:
      """
      Success: Database repaired.
      """
