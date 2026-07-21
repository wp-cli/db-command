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

  Scenario: Import from database name path by default with mysql defaults
    Given a WP install

    When I run `wp db export wp_cli_test.sql`
    Then the wp_cli_test.sql file should exist

    When I run `wp db import --defaults`
    Then STDOUT should be:
      """
      Success: Imported from 'wp_cli_test.sql'.
      """

  Scenario: Import from database name path by default with --no-defaults
    Given a WP install

    When I run `wp db export wp_cli_test.sql`
    Then the wp_cli_test.sql file should exist

    When I run `wp db import --no-defaults`
    Then STDOUT should be:
      """
      Success: Imported from 'wp_cli_test.sql'.
      """

  @require-mysql-or-mariadb
  Scenario: Import from STDIN
    Given a WP install

    When I run `wp db import -`
    Then STDOUT should be:
      """
      Success: Imported from 'STDIN'.
      """

  # TODO: Debug the difference here.
  @require-sqlite
  Scenario: Import from STDIN
    Given a WP install

    When I run `echo "" | wp db import -`
    Then STDOUT should be:
      """
      Success: Imported from 'STDIN'.
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

  # SQLite doesn't support the --dbuser flag.
  @require-mysql-or-mariadb
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

  # SQLite doesn't support the --force flag.
  @require-mysql-or-mariadb
  Scenario: Import database with passed-in options
    Given a WP install
    And a debug.sql file:
      """
      INSERT INTO `wp_options` (`option_id`, `option_name`, `option_value`, `autoload`) VALUES (999, 'testoption',  'testval',  'yes'),(999, 'testoption',  'testval',  'yes');
      """

    When I try `wp db import debug.sql --force`
    Then STDOUT should be:
      """
      Success: Imported from 'debug.sql'.
      """

  # Regression test for https://github.com/wp-cli/db-command/issues/218
  # The `--ssl` flag used to be silently dropped by `get_mysql_args()` because it
  # was missing from the list of allowed MySQL client options, so `wp db import
  # --ssl` connected without SSL. Assert the flag is now forwarded to the MySQL
  # command (visible in the debug output before the connection is attempted).
  # SQLite does not use the MySQL client, hence the tag.
  @require-mysql-or-mariadb
  Scenario: Import forwards the --ssl flag to the MySQL client
    Given a WP install

    When I run `wp db export wp_cli_test.sql`
    Then the wp_cli_test.sql file should exist

    When I try `wp db import wp_cli_test.sql --ssl --debug`
    Then STDERR should contain:
      """
      --ssl
      """

  # For SQLite this would fail at the `wp db create` step
  # because of the missing plugin/drop-in.
  @require-mysql-or-mariadb
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

  @require-mysql-or-mariadb
  Scenario: MySQL defaults are available as appropriate with --defaults flag
    Given a WP install

    When I run `wp db export wp_cli_test.sql`
    Then the wp_cli_test.sql file should exist

    When I try `wp db import --defaults --debug`
    Then STDERR should match #Debug \(db\): Running shell command: /([^/]+/)+(mysql|mariadb) --no-auto-rehash#

    When I try `wp db import --debug`
    Then STDERR should match #Debug \(db\): Running shell command: /([^/]+/)+(mysql|mariadb) --no-defaults --no-auto-rehash#

    When I try `wp db import --no-defaults --debug`
    Then STDERR should match #Debug \(db\): Running shell command: /([^/]+/)+(mysql|mariadb) --no-defaults --no-auto-rehash#

  @require-mysql-or-mariadb
  Scenario: Import db that has emoji in post
    Given a WP install

    When I run `wp post create --post_title="🍣"`
    And I run `wp post list`
    Then the return code should be 0
    And STDOUT should contain:
      """
      🍣
      """

    When I try `wp db export wp_cli_test.sql --debug`
    Then the return code should be 0
    And the wp_cli_test.sql file should exist
    And STDERR should contain:
      """
      Detected character set of the posts table: utf8mb4
      """
    And STDERR should contain:
      """
      Setting missing default character set to utf8mb4
      """

    When I run `wp db import --dbuser=wp_cli_test --dbpass=password1`
    Then STDOUT should be:
      """
      Success: Imported from 'wp_cli_test.sql'.
      """

    When I run `wp post list`
    Then the return code should be 0
    And STDOUT should contain:
      """
      🍣
      """

  @require-sqlite
  Scenario: Import db that has emoji in post
    Given a WP install

    When I run `wp post create --post_title="🍣"`
    And I run `wp post list`
    Then the return code should be 0
    And STDOUT should contain:
      """
      🍣
      """

    When I try `wp db export wp_cli_test.sql --debug`
    Then the return code should be 0
    And the wp_cli_test.sql file should exist

    When I run `wp db import --dbuser=wp_cli_test --dbpass=password1`
    Then STDOUT should be:
      """
      Success: Imported from 'wp_cli_test.sql'.
      """

    When I run `wp post list`
    Then the return code should be 0
    And STDOUT should contain:
      """
      🍣
      """

  # SQLite does not use the MySQL client and has no concept of SQL modes.
  @require-mysql-or-mariadb
  Scenario: `wp db import` adapts the SQL mode via --init-command by default
    Given a WP install

    When I run `wp db export wp_cli_test.sql`
    Then the wp_cli_test.sql file should exist

    # The WordPress-compatibility mode adaptation runs on the same import
    # connection via --init-command, so it is visible in the debug output and no
    # separate mode probe runs (which is what used to break with custom connection
    # options).
    When I try `wp db import wp_cli_test.sql --debug`
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
  Scenario: `wp db import --skip-sql-mode-compat` imports under the server's own SQL modes
    Given a WP install

    When I run `wp db export wp_cli_test.sql`
    Then the wp_cli_test.sql file should exist

    When I try `wp db import wp_cli_test.sql --skip-sql-mode-compat --debug`
    Then the return code should be 0
    And STDERR should not contain:
      """
      SET SESSION sql_mode
      """

  # Regression test for the WordPress-compatibility behavior. WordPress schema
  # declares datetime columns as `DEFAULT '0000-00-00 00:00:00'`, so real dumps
  # carry zero-date values. On servers whose default SQL mode includes
  # NO_ZERO_DATE/STRICT_TRANS_TABLES (MySQL 5.7+/8.0), a raw dump without its own
  # SQL_MODE header would fail to import with "Invalid default value". `wp db
  # import` must strip those modes for the session so the import succeeds.
  @require-mysql-or-mariadb
  Scenario: `wp db import` loads a dump containing legacy zero-date values
    Given a WP install
    And a zerodate.sql file:
      """
      CREATE TABLE `wp_cli_zerodate` (
        `id` int NOT NULL,
        `d` datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
      );
      INSERT INTO `wp_cli_zerodate` (`id`, `d`) VALUES (1, '0000-00-00 00:00:00');
      """

    When I run `wp db import zerodate.sql`
    Then STDOUT should contain:
      """
      Success: Imported from 'zerodate.sql'.
      """

    When I run `wp db query 'SELECT COUNT(*) FROM wp_cli_zerodate;' --skip-column-names`
    Then STDOUT should contain:
      """
      1
      """

  # Regression test for https://github.com/wp-cli/db-command/issues/171
  # A dump streamed from STDIN must get the same WordPress SQL-mode compatibility
  # as a file import. This is now handled via --init-command, which applies on the
  # STDIN connection too (the previous prepend only covered file imports).
  @require-mysql-or-mariadb
  Scenario: `wp db import -` from STDIN loads a dump containing legacy zero-date values
    Given a WP install
    And a zerodate_stdin.sql file:
      """
      CREATE TABLE wp_cli_zerodate_stdin (id int NOT NULL, d datetime NOT NULL DEFAULT '0000-00-00 00:00:00');
      INSERT INTO wp_cli_zerodate_stdin (id, d) VALUES (1, '0000-00-00 00:00:00');
      """

    When I run `wp db import - < zerodate_stdin.sql`
    Then STDOUT should contain:
      """
      Success: Imported from 'STDIN'.
      """

    When I run `wp db query 'SELECT COUNT(*) FROM wp_cli_zerodate_stdin;' --skip-column-names`
    Then STDOUT should contain:
      """
      1
      """

  # The compatibility statement must compose with a caller-supplied --init-command
  # rather than replace it. Both are sent as a single multi-statement
  # --init-command (compatibility statement first, caller's second), so the
  # caller's own init command still runs and the zero-date import still succeeds.
  @require-mysql-or-mariadb
  Scenario: `wp db import` keeps SQL-mode compatibility when the caller sets --init-command
    Given a WP install
    And a zerodate_compose.sql file:
      """
      CREATE TABLE wp_cli_zd_compose (id int NOT NULL, d datetime NOT NULL DEFAULT '0000-00-00 00:00:00');
      INSERT INTO wp_cli_zd_compose (id, d) VALUES (1, '0000-00-00 00:00:00');
      """

    When I run `wp db import zerodate_compose.sql --init-command="SET @x = 1"`
    Then STDOUT should contain:
      """
      Success: Imported from 'zerodate_compose.sql'.
      """
