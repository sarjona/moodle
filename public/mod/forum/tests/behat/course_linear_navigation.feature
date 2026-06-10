@mod @mod_forum
Feature: Display the course linear navigation in the forum pages
  In order to quickly access the next and previous activities in a course
  As a user
  I want to see the course linear navigation in forum pages

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher  | Teacher   | 1        |
      | student  | Student   | 1        |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | student | C1     | student        |
      | teacher | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name   | course | idnumber |
      | forum    | Forum1 | C1     | forum1   |
    And the following "mod_forum > discussions" exist:
      | user    | forum  | name     | message               |
      | teacher | forum1 | Post one | Test post message one |
      | student | forum1 | Post two | Test post message two |
    And the following "mod_forum > posts" exist:
      | user    | parentsubject | subject                 | message                               |
      | student | Post one      | Reply 1 to discussion 1 | Discussion contents 1, second message |

  @javascript
  Scenario: As a student I should see the course linear navigation in forum pages that allow it
    Given I am on the "Forum1" "forum activity" page logged in as "student"
    Then the course linear navigation should be visible
    But I click on "Add discussion topic" "link"
    And the course linear navigation should be visible
    And I click on "Advanced" "button"
    And the course linear navigation should not be visible
    And I set the field "Subject" to "New message subject."
    And I set the field "Message" to "My new message content."
    And I press "Post to forum"
    And the course linear navigation should be visible
    And I follow "Post two"
    And the course linear navigation should be visible
    And I follow "Reply"
    And the course linear navigation should be visible
    And I click on "Advanced" "button"
    And the course linear navigation should not be visible
    And I set the field "Message" to "This is the reply text."
    And I press "Post to forum"
    And the course linear navigation should be visible

  @javascript
  Scenario: As a teacher I should see the course linear navigation in forum pages that allow it
    Given I am on the "Forum1" "forum activity editing" page logged in as "teacher"
    And I expand all fieldsets
    And I set the following fields to these values:
      | Whole forum grading > Type            | Point       |
    And I press "Save and display"
    Then the course linear navigation should be visible
    But I click on "Grade users" "button"
    And the course linear navigation should not be visible
    And I press "Close grader"
    And I follow "Post two"
    And the course linear navigation should be visible
    And I follow "Edit"
    And the course linear navigation should not be visible
    And I press "Cancel"
    And I follow "Delete"
    And the course linear navigation should not be visible
    And I press "Cancel"
    And I follow "Post one"
    And I follow "Split"
    And the course linear navigation should not be visible
    And I press "Cancel"
    And I navigate to "Subscriptions" in current page administration
    And the course linear navigation should not be visible
    And I select "Manage subscribers" from the "jump" singleselect
    And the course linear navigation should not be visible
    And I navigate to "Reports" in current page administration
    And the course linear navigation should not be visible
    And I navigate to "Export" in current page administration
    And the course linear navigation should not be visible
