Feature: Ack through the database

  Scenario: Search on a single site install
    Given a WP install
    And I run `wp db query "CREATE TABLE wp_not ( id int(11) unsigned NOT NULL AUTO_INCREMENT, awesome_stuff TEXT, PRIMARY KEY (id) );"`
    And I run `wp db query "INSERT INTO wp_not (awesome_stuff) VALUES ('example.com'), ('e_ample.c%m'), ('example.comm'), ('example.com example.com');"`
    And I run `wp db query "CREATE TABLE pw_options ( id int(11) unsigned NOT NULL AUTO_INCREMENT, awesome_stuff TEXT, PRIMARY KEY (id) );"`
    And I run `wp db query "INSERT INTO pw_options (awesome_stuff) VALUES ('example.com'), ('e_ample.c%m'), ('example.comm'), ('example.com example.com');"`

    When I run `wp db query "SELECT CONCAT( id, ':', awesome_stuff) FROM wp_not ORDER BY id;" --skip-column-names`
    Then STDOUT should be:
      """
      1:example.com
      2:e_ample.c%m
      3:example.comm
      4:example.com example.com
      """
    When I run `wp db query "SELECT CONCAT( id, ':', awesome_stuff) FROM pw_options ORDER BY id;" --skip-column-names`
    Then STDOUT should be:
      """
      1:example.com
      2:e_ample.c%m
      3:example.comm
      4:example.com example.com
      """

    When I run `wp db ack example.com`
    Then STDOUT should contain:
      """
      wp_options:option_value
      1:http://example.com
      """
    And STDOUT should not contain:
      """
      wp_not
      """
    And STDOUT should not contain:
      """
      pw_options
      """

    When I run `wp db ack example.com wp_options`
    Then STDOUT should contain:
      """
      wp_options:option_value
      1:http://example.com
      """
    And STDOUT should not contain:
      """
      wp_not
      """
    And STDOUT should not contain:
      """
      pw_options
      """

    When I run `wp db ack example.com wp_options wp_not --before_context=0 --after_context=0`
    Then STDOUT should contain:
      """
      wp_options:option_value
      1:example.com
      """
    And STDOUT should contain:
      """
      wp_not:awesome_stuff
      1:example.com
      """
    And STDOUT should contain:
      """
      wp_not:awesome_stuff
      4:example.com [...] example.com
      """
    And STDOUT should not contain:
      """
      pw_options
      """
    And STDOUT should not contain:
      """
      e_ample.c%m
      """

    When I run `wp db ack EXAMPLE.COM --before_context=0 --after_context=0`
    Then STDOUT should contain:
      """
      wp_options:option_value
      1:example.com
      """
    And STDOUT should not contain:
      """
      wp_not
      """
    And STDOUT should not contain:
      """
      pw_options
      """

    When I run `wp db ack nothing_matches`
    Then STDOUT should be empty

    When I run `wp db prefix`
    Then STDOUT should be:
      """
      wp_
      """

    When I run `wp db ack example.com --all-tables-with-prefix --before_context=0 --after_context=0`
    Then STDOUT should contain:
      """
      wp_options:option_value
      1:example.com
      """
    And STDOUT should contain:
      """
      wp_not:awesome_stuff
      1:example.com
      """
    And STDOUT should contain:
      """
      wp_not:awesome_stuff
      3:example.com
      """
    And STDOUT should not contain:
      """
      e_ample.c%m
      """
    And STDOUT should not contain:
      """
      pw_options
      """

    When I run `wp db ack example.com --all-tables --before_context=0 --after_context=0`
    Then STDOUT should contain:
      """
      wp_options:option_value
      1:example.com
      """
    And STDOUT should contain:
      """
      wp_not:awesome_stuff
      1:example.com
      """
    And STDOUT should contain:
      """
      wp_not:awesome_stuff
      3:example.com
      """
    And STDOUT should contain:
      """
      pw_options:awesome_stuff
      1:example.com
      """
    And STDOUT should contain:
      """
      pw_options:awesome_stuff
      3:example.com
      """
    And STDOUT should not contain:
      """
      e_ample.c%m
      """

    When I run `wp db ack e_ample.c%m`
    Then STDOUT should be empty

    When I run `wp db ack e_ample.c%m --all-tables --before_context=0 --after_context=0`
    Then STDOUT should not contain:
      """
      wp_options
      """
    And STDOUT should contain:
      """
      wp_not:awesome_stuff
      2:e_ample.c%m
      """
    And STDOUT should contain:
      """
      pw_options:awesome_stuff
      2:e_ample.c%m
      """
    And STDOUT should not contain:
      """
      example.com
      """

    When I run `wp db ack example.comm --all-tables --before_context=0 --after_context=0`
    Then STDOUT should not contain:
      """
      wp_options
      """
    And STDOUT should contain:
      """
      wp_not:awesome_stuff
      3:example.comm
      """
    And STDOUT should contain:
      """
      pw_options:awesome_stuff
      3:example.comm
      """
    And STDOUT should not contain:
      """
      e_ample.c%m
      """
    And STDOUT should not contain:
      """
      1:example.com
      """

  Scenario: Search on a multisite install
    Given a WP multisite install
    And I run `wp site create --slug=foo`
    And I run `wp db query "CREATE TABLE wp_not ( id int(11) unsigned NOT NULL AUTO_INCREMENT, awesome_stuff TEXT, PRIMARY KEY (id) );"`
    And I run `wp db query "INSERT INTO wp_not (awesome_stuff) VALUES ('example.com'), ('e_ample.c%m');"`
    And I run `wp db query "CREATE TABLE wp_2_not ( id int(11) unsigned NOT NULL AUTO_INCREMENT, awesome_stuff TEXT, PRIMARY KEY (id) );"`
    And I run `wp db query "INSERT INTO wp_2_not (awesome_stuff) VALUES ('example.com'), ('e_ample.c%m');"`
    And I run `wp db query "CREATE TABLE pw_options ( id int(11) unsigned NOT NULL AUTO_INCREMENT, awesome_stuff TEXT, PRIMARY KEY (id) );"`
    And I run `wp db query "INSERT INTO pw_options (awesome_stuff) VALUES ('example.com'), ('e_ample.c%m');"`

    When I run `wp db query "SELECT CONCAT( id, ':', awesome_stuff) FROM wp_not ORDER BY id;" --skip-column-names`
    Then STDOUT should be:
      """
      1:example.com
      2:e_ample.c%m
      """
    When I run `wp db query "SELECT CONCAT( id, ':', awesome_stuff) FROM wp_2_not ORDER BY id;" --skip-column-names`
    Then STDOUT should be:
      """
      1:example.com
      2:e_ample.c%m
      """
    When I run `wp db query "SELECT CONCAT( id, ':', awesome_stuff) FROM pw_options ORDER BY id;" --skip-column-names`
    Then STDOUT should be:
      """
      1:example.com
      2:e_ample.c%m
      """

    When I run `wp db ack example.com`
    Then STDOUT should contain:
      """
      wp_options:option_value
      1:http://example.com
      """
    And STDOUT should not contain:
      """
      wp_2_options
      """
    And STDOUT should not contain:
      """
      wp_not
      """
    And STDOUT should not contain:
      """
      wp_2_not
      """
    And STDOUT should not contain:
      """
      pw_options
      """

    When I run `wp db ack example.com wp_options`
    Then STDOUT should contain:
      """
      wp_options:option_value
      1:http://example.com
      """
    And STDOUT should not contain:
      """
      wp_2_options
      """
    And STDOUT should not contain:
      """
      wp_not
      """
    And STDOUT should not contain:
      """
      wp_2_not
      """
    And STDOUT should not contain:
      """
      pw_options
      """

    When I run `wp db ack example.com --url=example.com/foo`
    Then STDOUT should not contain:
      """
      wp_options
      """
    And STDOUT should contain:
      """
      wp_2_options:option_value
      1:http://example.com/foo
      """
    And STDOUT should not contain:
      """
      wp_not
      """
    And STDOUT should not contain:
      """
      wp_2_not
      """
    And STDOUT should not contain:
      """
      pw_options
      """

    When I run `wp db ack example.com --network`
    Then STDOUT should contain:
      """
      wp_options:option_value
      1:http://example.com
      """
    And STDOUT should contain:
      """
      wp_2_options:option_value
      1:http://example.com/foo
      """
    And STDOUT should not contain:
      """
      wp_not
      """
    And STDOUT should not contain:
      """
      wp_2_not
      """
    And STDOUT should not contain:
      """
      pw_options
      """

    When I run `wp db ack example.com --no-network`
    Then STDOUT should contain:
      """
      wp_options:option_value
      1:http://example.com
      """
    And STDOUT should not contain:
      """
      wp_2_options
      """
    And STDOUT should not contain:
      """
      wp_not
      """
    And STDOUT should not contain:
      """
      wp_2_not
      """
    And STDOUT should not contain:
      """
      pw_options
      """

    When I run `wp db ack example.com --all-tables-with-prefix`
    Then STDOUT should contain:
      """
      wp_options:option_value
      1:http://example.com
      """
    And STDOUT should contain:
      """
      wp_2_options:option_value
      1:http://example.com/foo
      """
    And STDOUT should contain:
      """
      wp_not:awesome_stuff
      1:example.com
      """
    And STDOUT should contain:
      """
      wp_2_not:awesome_stuff
      1:example.com
      """
    And STDOUT should not contain:
      """
      pw_options
      """

    When I run `wp db ack example.com --no-all-tables-with-prefix`
    Then STDOUT should contain:
      """
      wp_options:option_value
      1:http://example.com
      """
    And STDOUT should not contain:
      """
      wp_2_options
      """
    And STDOUT should not contain:
      """
      wp_not
      """
    And STDOUT should not contain:
      """
      wp_2_not
      """
    And STDOUT should not contain:
      """
      pw_options
      """

    When I run `wp db ack example.com --all-tables-with-prefix --url=example.com/foo`
    Then STDOUT should not contain:
      """
      wp_options
      """
    And STDOUT should contain:
      """
      wp_2_options:option_value
      1:http://example.com/foo
      """
    And STDOUT should not contain:
      """
      wp_not
      """
    And STDOUT should contain:
      """
      wp_2_not:awesome_stuff
      1:example.com
      """
    And STDOUT should not contain:
      """
      pw_options
      """

    When I run `wp db ack example.com --all-tables`
    Then STDOUT should contain:
      """
      wp_options:option_value
      1:http://example.com
      """
    And STDOUT should contain:
      """
      wp_2_options:option_value
      1:http://example.com/foo
      """
    And STDOUT should contain:
      """
      wp_not:awesome_stuff
      1:example.com
      """
    And STDOUT should contain:
      """
      wp_2_not:awesome_stuff
      1:example.com
      """
    And STDOUT should contain:
      """
      pw_options:awesome_stuff
      1:example.com
      """

    When I run `wp db ack example.com --all-tables --url=example.com/foo`
    Then STDOUT should contain:
      """
      wp_options:option_value
      1:http://example.com
      """
    And STDOUT should contain:
      """
      wp_2_options:option_value
      1:http://example.com/foo
      """
    And STDOUT should contain:
      """
      wp_not:awesome_stuff
      1:example.com
      """
    And STDOUT should contain:
      """
      wp_2_not:awesome_stuff
      1:example.com
      """
    And STDOUT should contain:
      """
      pw_options:awesome_stuff
      1:example.com
      """

    When I run `wp db ack e_ample.c%m`
    Then STDOUT should be empty

    When I run `wp db ack e_ample.c%m --all-tables`
    Then STDOUT should not contain:
      """
      wp_options
      """
    And STDOUT should not contain:
      """
      wp_2_options
      """
    And STDOUT should contain:
      """
      wp_not:awesome_stuff
      2:e_ample.c%m
      """
    And STDOUT should contain:
      """
      wp_2_not:awesome_stuff
      2:e_ample.c%m
      """
    And STDOUT should contain:
      """
      pw_options:awesome_stuff
      2:e_ample.c%m
      """

  Scenario: Long result strings are truncated
    Given a WP install
    And I run `wp option update searchtest '11111111searchstring11111111'`

    When I run `wp db ack searchstring --before_context=0 --after_context=0`
    Then STDOUT should contain:
      """
      :searchstring
      """
    And STDOUT should not contain:
      """
      searchstring1
      """

    When I run `wp db ack searchstring --before_context=3 --after_context=3`
    Then STDOUT should contain:
      """
      :111searchstring111
      """
    And STDOUT should not contain:
      """
      searchstring1111
      """

    When I run `wp db ack searchstring --before_context=2 --after_context=1`
    Then STDOUT should contain:
      """
      :11searchstring1
      """
    And STDOUT should not contain:
      """
      searchstring11
      """

    When I run `wp db ack searchstring`
    Then STDOUT should contain:
      """
      :11111111searchstring11111111
      """

  Scenario: Multibyte strings are truncated
    Given a WP install
    And I run `wp option update multibytetest 'あいうえおかきくけこさしすせとたちつてと'`

    When I run `wp db ack "かきくけこ" --before_context=0 --after_context=0`
    Then STDOUT should contain:
      """
      :かきくけこ
      """
    And STDOUT should not contain:
      """
      かきくけこさ
      """

    When I run `wp db ack "かきくけこ" --before_context=3 --after_context=3`
    Then STDOUT should contain:
      """
      :うえおかきくけこさしす
      """


    When I run `wp db ack "かきくけこ" --before_context=2 --after_context=1`
    Then STDOUT should contain:
      """
      :えおかきくけこさし
      """
    And STDOUT should not contain:
      """
      えおかきくけこさしす
      """

    When I run `wp db ack "かきくけこ"`
    Then STDOUT should contain:
      """
      :あいうえおかきくけこさしすせとたちつてと
      """
