Feature: Query the database with WordPress' MySQL config

  Scenario: Database querying shouldn't load any plugins
    Given a WP install
    And a wp-content/mu-plugins/error.php file:
      """
      <?php
      WP_CLI::error( "Plugin loaded." );
      """

    When I try `wp option get home`
    Then STDERR should be:
      """
      Error: Plugin loaded.
      """

    When I run `wp db query "SELECT COUNT(ID) FROM wp_users;"`
    Then STDOUT should be:
      """
      COUNT(ID)
      1
      """

  # SQLite doesn't support the --html option nor different dbuser.
  @require-mysql-or-mariadb
  Scenario: Database querying with passed-in options
    Given a WP install

    When I run `wp db query "SELECT COUNT(ID) FROM wp_posts;" --dbuser=wp_cli_test --html`
    Then STDOUT should contain:
      """
      <TABLE
      """

    When I try `wp db query "SELECT COUNT(ID) FROM wp_posts;" --dbuser=no_such_user`
    Then the return code should not be 0
    And STDERR should contain:
      """
      Access denied
      """
    And STDOUT should be empty

  # SQLite doesn't support the --html option nor different dbuser.
  @require-mysql-or-mariadb
  Scenario: Database querying with MySQL defaults and passed-in options
    Given a WP install

    When I run `wp db query --defaults "SELECT COUNT(ID) FROM wp_posts;" --dbuser=wp_cli_test --html`
    Then STDOUT should contain:
      """
      <TABLE
      """

    When I try `wp db query --defaults "SELECT COUNT(ID) FROM wp_posts;" --dbuser=no_such_user`
    Then the return code should not be 0
    And STDERR should contain:
      """
      Access denied
      """
    And STDOUT should be empty

  # SQLite doesn't support the --html option nor different dbuser.
  @require-mysql-or-mariadb
  Scenario: Database querying with --nodefaults and passed-in options
    Given a WP install

    When I run `wp db query --no-defaults "SELECT COUNT(ID) FROM wp_posts;" --dbuser=wp_cli_test --html`
    Then STDOUT should contain:
      """
      <TABLE
      """

    When I try `wp db query --no-defaults "SELECT COUNT(ID) FROM wp_posts;" --dbuser=no_such_user`
    Then the return code should not be 0
    And STDERR should contain:
      """
      Access denied
      """
    And STDOUT should be empty

  @require-mysql-or-mariadb
  Scenario: MySQL defaults are available as appropriate with --defaults flag
    Given a WP install

    When I try `wp db query --defaults --debug`
    Then STDERR should match #Debug \(db\): Running shell command: /([^/]+/)+(mysql|mariadb) --no-auto-rehash#

    When I try `wp db query --debug`
    Then STDERR should match #Debug \(db\): Running shell command: /([^/]+/)+(mysql|mariadb) --no-defaults --no-auto-rehash#

    When I try `wp db query --no-defaults --debug`
    Then STDERR should match #Debug \(db\): Running shell command: /([^/]+/)+(mysql|mariadb) --no-defaults --no-auto-rehash#

  Scenario: `wp db query` runs under the server's own SQL modes without a separate mode probe
    Given a WP install

    # The previous implementation always opened a second `SELECT @@SESSION.sql_mode`
    # connection to decide whether to rewrite the query, then prepended a
    # `SET SESSION sql_mode=...` statement. Both are gone: `wp db query` now behaves
    # like the `mysql` client and runs under the server's own SQL modes.
    When I try `wp db query 'SELECT 1;' --debug`
    Then the return code should be 0
    And STDERR should not contain:
      """
      @@SESSION.sql_mode
      """
    And STDERR should not contain:
      """
      SET SESSION sql_mode
      """

  # Regression test for https://github.com/wp-cli/db-command/issues/311
  # Passing connection options alongside an inline query used to fail, because the
  # SQL-mode probe opened a *second* connection that ignored those very options
  # (custom --host, --defaults, SSL/TLS, sockets, ...) and then aborted the whole
  # command with "Failed to get current SQL modes". With the probe removed, the
  # inline query runs directly under the given options.
  Scenario: `wp db query` with an inline query and connection options does not trigger a failing mode probe
    Given a WP install

    When I try `wp db query 'SELECT 1;' --defaults --debug`
    Then STDERR should not contain:
      """
      Failed to get current SQL modes
      """
    And STDERR should not contain:
      """
      @@SESSION.sql_mode
      """

  # Regression test for https://github.com/wp-cli/db-command/issues/309
  # MariaDB 11.4+ verifies the server certificate by default and prints a warning to
  # STDERR (or fails) against the auto-generated self-signed certificate, which broke
  # the tests. `run()` now opts out via `--skip-ssl-verify-server-cert` on MariaDB, and
  # both `ssl-verify-server-cert` and its `skip-` variant are allowed so the behaviour
  # can be overridden. Assert the flag is forwarded to the MySQL client (visible in the
  # debug output before the connection is attempted). SQLite does not use the MySQL
  # client, hence the tag.
  @require-mysql-or-mariadb
  Scenario: Query forwards the ssl-verify-server-cert flags to the MySQL client
    Given a WP install

    When I try `wp db query "SELECT 1" --skip-ssl-verify-server-cert --debug`
    Then STDERR should contain:
      """
      skip-ssl-verify-server-cert
      """
