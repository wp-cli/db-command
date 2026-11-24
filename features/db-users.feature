Feature: Manage database users

  # The cleanup uses `I try` with a plain DROP USER, as MySQL 5.6 has no DROP USER IF EXISTS.
  @require-mysql-or-mariadb
  Scenario: Create a database user without privileges
    Given a WP install
    And I try `wp db query "DROP USER 'wpcli_dbusers_1'@'%'" --dbuser={DB_ROOT_USER} --dbpass={DB_ROOT_PASSWORD}`

    When I run `wp db users create wpcli_dbusers_1 '%' --password=first-Pa55 --dbuser={DB_ROOT_USER} --dbpass={DB_ROOT_PASSWORD}`
    Then STDOUT should be:
      """
      Success: Created database user 'wpcli_dbusers_1'@'%'.
      """

    When I run `wp db query "SELECT User FROM mysql.user WHERE User = 'wpcli_dbusers_1'" --skip-column-names --dbuser={DB_ROOT_USER} --dbpass={DB_ROOT_PASSWORD}`
    Then STDOUT should be:
      """
      wpcli_dbusers_1
      """

    When I try `wp db query "SELECT 1" --dbuser=wpcli_dbusers_1 --dbpass=first-Pa55`
    Then the return code should be 1
    And STDERR should contain:
      """
      denied
      """

    When I try `wp db users create wpcli_dbusers_1 '%' --password=first-Pa55 --dbuser={DB_ROOT_USER} --dbpass={DB_ROOT_PASSWORD}`
    Then the return code should be 1
    And STDERR should contain:
      """
      Error:
      """

    When I run `wp db query "DROP USER 'wpcli_dbusers_1'@'%'" --dbuser={DB_ROOT_USER} --dbpass={DB_ROOT_PASSWORD}`

  @require-mysql-or-mariadb
  Scenario: Create a database user with privileges on the WordPress database
    Given a WP install
    And I try `wp db query "DROP USER 'wpcli_dbusers_2'@'%'" --dbuser={DB_ROOT_USER} --dbpass={DB_ROOT_PASSWORD}`

    When I run `echo "it's a \"tricky\" pass\\word;" | wp db users create wpcli_dbusers_2 '%' --grant-privileges --dbuser={DB_ROOT_USER} --dbpass={DB_ROOT_PASSWORD}`
    Then STDOUT should be:
      """
      Success: Created database user 'wpcli_dbusers_2'@'%' with all privileges on '{DB_NAME}'.
      """

    When I run `wp db query "SELECT option_value FROM wp_options WHERE option_name = 'siteurl'" --skip-column-names --dbuser=wpcli_dbusers_2 --dbpass="it's a \"tricky\" pass\\word;"`
    Then STDOUT should be:
      """
      https://example.com
      """

    When I run `wp db query "DROP USER 'wpcli_dbusers_2'@'%'" --dbuser={DB_ROOT_USER} --dbpass={DB_ROOT_PASSWORD}`

  @require-mysql-or-mariadb
  Scenario: The new user's password is not leaked into the debug output
    Given a WP install
    And I try `wp db query "DROP USER 'wpcli_dbusers_3'@'%'" --dbuser={DB_ROOT_USER} --dbpass={DB_ROOT_PASSWORD}`

    When I try `wp db users create wpcli_dbusers_3 '%' --password=leak-Ch3ck-Value --dbuser={DB_ROOT_USER} --dbpass={DB_ROOT_PASSWORD} --debug=db`
    Then the return code should be 0
    And STDERR should contain:
      """
      Creating database user 'wpcli_dbusers_3'@'%'.
      """
    And STDERR should not contain:
      """
      leak-Ch3ck-Value
      """

    When I run `wp db query "DROP USER 'wpcli_dbusers_3'@'%'" --dbuser={DB_ROOT_USER} --dbpass={DB_ROOT_PASSWORD}`

  @require-mysql-or-mariadb
  Scenario: A password is required
    Given a WP install

    When I try `wp db users create wpcli_dbusers_4 '%' --dbuser={DB_ROOT_USER} --dbpass={DB_ROOT_PASSWORD} < /dev/null`
    Then the return code should be 1
    And STDERR should contain:
      """
      Error: A password is required.
      """

  @require-mysql-or-mariadb
  Scenario: Only the create subcommand is available
    Given a WP install

    When I try `wp db users drop --yes`
    Then the return code should be 1
    And STDERR should contain:
      """
      is not a registered subcommand of 'db users'
      """

    When I run `wp db check`
    Then STDOUT should contain:
      """
      Success: Database checked.
      """

  @require-sqlite
  Scenario: Database users are not supported with SQLite
    Given a WP install

    When I try `wp db users create someone --password=some-Pa55`
    Then the return code should be 1
    And STDERR should be:
      """
      Error: Database users are not supported with SQLite.
      """
