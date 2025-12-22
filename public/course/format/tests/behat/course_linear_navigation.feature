@core @core_course @core_courseformat @javascript
Feature: Display the course linear navigation in the activity pages
  In order to quickly access the next and previous activities in a course
  As a user
  I want to see the course linear navigation in activities pages

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | teacher  | Teacher   | 1        | teacher@example.com |
      | student  | Student   | 1        | student@example.com |
    And the following "courses" exist:
      | fullname | shortname | format         | startdate |
      | Course1  | CT        | topics         |           |
      | Course2  | CW        | weeks          |           |
      | Course2  | SA        | singleactivity |           |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | student | CT     | student        |
      | teacher | CT     | editingteacher |
      | student | CW     | student        |
      | teacher | CW     | editingteacher |
      | student | SA     | student        |
      | teacher | SA     | editingteacher |
    And the following "activities" exist:
      | activity | name   | course |
      | page     | PageCT | CT     |
      | page     | PageCW | CW     |
      | page     | PageSA | SA     |

  Scenario Template: As a user I should see the course linear navigation
  in activity pages for course format that would allow it.
    Given I am on the "<activity>" "page activity" page logged in as <user>
    Then ".course-linear-navigation" "css" <shouldbevisible>
    Examples:
      | activity | user    | shouldbevisible       |
      | PageCT   | student | should be visible     |
      | PageCW   | student | should be visible     |
      | PageSA   | student | should not be visible |
      | PageCT   | teacher | should be visible     |
      | PageCW   | teacher | should be visible     |
      | PageSA   | teacher | should not be visible |

  Scenario: If the course linear navigation is disabled globally then as a student
  I should not see the course linear navigation in an activity page.
    Given the following config values are set as admin:
      | enablelinearnav | 0 | format_topics |
    And  I am on the "PageCT" "page activity" page logged in as student
    Then ".course-linear-navigation" "css" should not be visible

  Scenario: The database module should not have a course navigation bar when
  the sticky footer is displayed.
    Given the following "activities" exist:
      | activity | name       | intro    | course | idnumber |
      | data     | DatabaseCT | Database | CT     | data1    |
    And the following "mod_data > fields" exist:
      | database | type | name            | description            |
      | data1    | text | Test field name | Test field description |
    And I am on the "data1" "data activity" page logged in as student
    And I click on "Add entry" "button"
    Then ".course-linear-navigation" "css" should not be visible
    And "#sticky-footer" "css" should be visible
