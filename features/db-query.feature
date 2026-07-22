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

  # `wp db query` adapts the session SQL mode to be WordPress-compatible the same
  # way `wp db import` does: via --init-command on the query's own connection, with
  # no separate mode probe. This keeps statements against WordPress's zero-date
  # schema (e.g. `ALTER TABLE wp_blogs ...`, `CREATE TABLE ... AS SELECT` from a
  # WordPress table) working on servers whose default SQL mode is strict.
  @require-mysql-or-mariadb
  Scenario: `wp db query` adapts the SQL mode by default without a separate mode probe
    Given a WP install

    When I try `wp db query 'SELECT 1;' --debug`
    Then the return code should be 0
    And STDERR should contain:
      """
      SET SESSION sql_mode
      """
    And STDERR should not contain:
      """
      Failed to get current SQL modes
      """

  @require-mysql-or-mariadb
  Scenario: `wp db query --skip-sql-mode-compat` runs under the server's own SQL modes
    Given a WP install

    When I try `wp db query 'SELECT 1;' --skip-sql-mode-compat --debug`
    Then the return code should be 0
    And STDERR should not contain:
      """
      SET SESSION sql_mode
      """

  # Regression test for https://github.com/wp-cli/db-command/issues/311
  # Passing connection options alongside an inline query used to fail, because the
  # old SQL-mode probe opened a *second* connection that ignored those very options
  # (custom --host, --defaults, SSL/TLS, sockets, ...) and then aborted the whole
  # command with "Failed to get current SQL modes". The probe is gone -- the
  # compatibility mode is now applied via --init-command on the query's own
  # connection -- so the inline query runs directly under the given options.
  Scenario: `wp db query` with an inline query and connection options does not trigger a failing mode probe
    Given a WP install

    When I try `wp db query 'SELECT 1;' --defaults --debug`
    Then STDERR should not contain:
      """
      Failed to get current SQL modes
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

  @require-mysql-or-mariadb
  Scenario: Database querying falls back to wpdb when mysql binary is unavailable
    Given a WP install
    And a fake-bin/mysql file:
      """
      #!/bin/sh
      exit 127
      """
    And a fake-bin/mariadb file:
      """
      #!/bin/sh
      exit 127
      """

    When I run `chmod +x fake-bin/mysql fake-bin/mariadb`
    And I try `env PATH={RUN_DIR}/fake-bin:$PATH wp db query "SELECT COUNT(ID) FROM wp_users;" --debug`
    Then STDOUT should be:
      """
      COUNT(ID)
      1
      """
    And STDERR should contain:
      """
      MySQL/MariaDB binary not available, falling back to wpdb.
      """
