@core @core_course @core_courseformat @format_topics @format_weeks @format_singleactivity
Feature: Display the course linear navigation
  In order to quickly navigate through the course activities in a linear way
  As a user
  I want to see the course linear navigation when the course format supports it and it is enabled in the course settings

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | s1       | Student   | 1        |
      | t1       | Teacher   | 1        |

  @javascript
  Scenario Outline: As a user I should see the course linear navigation when format allows it and it is enabled
    Given the following "courses" exist:
      | fullname | shortname | format    | enablelinearnav |
      | Course1  | C1        | <format>  | <linearnav>     |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | s1      | C1     | student        |
      | t1      | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name   | course |
      | page     | Page1  | C1     |
    When I am on the "Page1" "page activity" page logged in as "<user>"
    Then ".course-linear-navigation" "css" <shouldbevisible>

    Examples:
      | format         | linearnav | user | shouldbevisible       |
      | topics         | 1         | s1   | should be visible     |
      | topics         | 1         | t1   | should be visible     |
      | topics         | 0         | s1   | should not be visible |
      | topics         | 0         | t1   | should not be visible |
      | weeks          | 1         | s1   | should be visible     |
      | weeks          | 1         | t1   | should be visible     |
      | weeks          | 0         | s1   | should not be visible |
      | weeks          | 0         | t1   | should not be visible |
      | singleactivity | 1         | s1   | should not be visible |
      | singleactivity | 1         | t1   | should not be visible |

  @javascript
  Scenario Outline: As a learner I should see Previous and Next instead of Back to course
    Given the following "courses" exist:
      | fullname | shortname | format    | enablelinearnav |
      | Course1  | C1        | <format>  | 1               |
    And the following "course enrolments" exist:
      | user    | course | role    |
      | s1      | C1     | student |
    And the following "activities" exist:
      | activity | name   | course |
      | page     | Page1  | C1     |
    When I am on the "Page1" "page activity" page logged in as "s1"
    Then "#prev-activity-link" "css_element" should exist
    And "#next-activity-link" "css_element" should exist
    And I should see "Previous" in the "#prev-activity-link" "css_element"
    And I should see "Next" in the "#next-activity-link" "css_element"
    And I should not see "Back" in the "#sticky-footer" "css_element"

    Examples:
      | format |
      | topics |
      | weeks  |

  @javascript
  Scenario Outline: As a learner I can move to adjacent activities using linear footer controls
    Given the following "courses" exist:
      | fullname | shortname | format    | enablelinearnav |
      | Course1  | C1        | <format>  | 1               |
    And the following "course enrolments" exist:
      | user    | course | role    |
      | s1      | C1     | student |
    And the following "activities" exist:
      | activity | name   | intro              | course |
      | page     | Page1  | Intro for page one | C1     |
      | page     | Page2  | Intro for page two | C1     |
    When I am on the "Page1" "page activity" page logged in as "s1"
    And I click on "Next" "link" in the "#next-activity-link" "css_element"
    Then I should see "Intro for page two"
    When I click on "Previous" "link" in the "#prev-activity-link" "css_element"
    Then I should see "Intro for page one"

    Examples:
      | format |
      | topics |
      | weeks  |

  @javascript
  Scenario Outline: As a learner when I am at the end of linear navigation, Next leaves the activity view
    Given the following "courses" exist:
      | fullname | shortname | format    | enablelinearnav |
      | Course1  | C1        | <format>  | 1               |
    And the following "course enrolments" exist:
      | user    | course | role    |
      | s1      | C1     | student |
    And the following "activities" exist:
      | activity | name   | course |
      | page     | Page1  | C1     |
    When I am on the "Page1" "page activity" page logged in as "s1"
    And I click on "Next" "link" in the "#next-activity-link" "css_element"
    Then ".course-linear-navigation" "css" should not exist

    Examples:
      | format |
      | topics |
      | weeks  |
