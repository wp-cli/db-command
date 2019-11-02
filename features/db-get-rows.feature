Feature: Select rows from the database with WordPress' MySQL config

  Scenario: Get a list of post IDs in table format using a custom query
    Given a WP install

    When I run `wp db get-rows "SELECT ID as post_id FROM wp_posts;"`
    Then STDOUT should be a table containing rows:
      | post_id |
      | 1       |
      | 2       |
      | 3       |
