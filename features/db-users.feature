Feature: Manage database users

  Scenario: Create database user without privileges
    Given an empty directory
    And WP files
    And wp-config.php

    When I run `wp db create`
    Then STDOUT should be:
      """
      Success: Database created.
      """

    When I run `wp db users create testuser localhost --password=testpass123`
    Then STDOUT should contain:
      """
      Success: Database user 'testuser'@'localhost' created.
      """

    When I run `wp db query "SELECT User, Host FROM mysql.user WHERE User='testuser'"`
    Then STDOUT should contain:
      """
      testuser
      """

  Scenario: Create database user with privileges
    Given an empty directory
    And WP files
    And wp-config.php

    When I run `wp db create`
    Then STDOUT should be:
      """
      Success: Database created.
      """

    When I run `wp db users create appuser localhost --password=secret123 --grant-privileges`
    Then STDOUT should contain:
      """
      created with privileges on database
      """
    And STDOUT should contain:
      """
      appuser
      """

  Scenario: Create database user with custom host
    Given an empty directory
    And WP files
    And wp-config.php

    When I run `wp db create`
    Then STDOUT should be:
      """
      Success: Database created.
      """

    When I run `wp db users create remoteuser '%' --password=remote123`
    Then STDOUT should contain:
      """
      Success: Database user 'remoteuser'@'%' created.
      """

  Scenario: Create database user with no password
    Given an empty directory
    And WP files
    And wp-config.php

    When I run `wp db create`
    Then STDOUT should be:
      """
      Success: Database created.
      """

    When I run `wp db users create nopassuser localhost`
    Then STDOUT should contain:
      """
      Success: Database user 'nopassuser'@'localhost' created.
      """
