Feature: Import a WordPress database

  Scenario: Import from database name path by default
    Given a WP install

    When I run `wp db export wp_cli_test.sql`
    Then the wp_cli_test.sql file should exist

    When I run `wp db import`
    Then STDOUT should be:
      """
      Success: Imported from 'wp_cli_test.sql'.
      """

  Scenario: Import from database name path by default and skip speed optimization
    Given a WP install

    When I run `wp db export wp_cli_test.sql`
    Then the wp_cli_test.sql file should exist

    When I run `wp db import --skip-optimization`
    Then STDOUT should be:
      """
      Success: Imported from 'wp_cli_test.sql'.
      """

  Scenario: Import from database name path by default with passed-in dbuser/dbpass
    Given a WP install

    When I run `wp db export wp_cli_test.sql`
    Then the wp_cli_test.sql file should exist

    When I run `wp db import --dbuser=wp_cli_test --dbpass=password1`
    Then STDOUT should be:
      """
      Success: Imported from 'wp_cli_test.sql'.
      """

    When I try `wp db import --dbuser=wp_cli_test --dbpass=no_such_pass`
    Then the return code should not be 0
    And STDERR should contain:
      """
      Access denied
      """
    And STDOUT should be empty

  Scenario: Import database with passed-in options
    Given a WP install

    Given a debug.sql file:
      """
      INSERT INTO `wp_options` (`option_id`, `option_name`, `option_value`, `autoload`) VALUES (999, 'testoption',  'testval',  'yes'),(999, 'testoption',  'testval',  'yes');
      """

    When I try `wp db import debug.sql --force`
    Then STDOUT should be:
      """
      Success: Imported from 'debug.sql'.
      """

  Scenario: Help runs properly at various points of a functional WP install
    Given an empty directory

    When I run `wp help db import`
    Then STDOUT should contain:
      """
      wp db import
      """

    When I run `wp core download`
    Then STDOUT should not be empty
    And the wp-config-sample.php file should exist

    When I run `wp help db import`
    Then STDOUT should contain:
      """
      wp db import
      """

    When I run `wp core config {CORE_CONFIG_SETTINGS}`
    Then STDOUT should not be empty
    And the wp-config.php file should exist

    When I run `wp help db import`
    Then STDOUT should contain:
      """
      wp db import
      """

    When I run `wp db create`
    Then STDOUT should not be empty

    When I run `wp help db import`
    Then STDOUT should contain:
      """
      wp db import
      """
