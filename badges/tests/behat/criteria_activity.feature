@mod @mod_quiz @core @core_badges @javascript
Feature: Award badges based on activity completion
  In order to ensure a student has learned the material before being marked complete
  As a teacher
  I need to configure an activity to grant a badge only when the student achieves a passing grade upon completion.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 1 | C1        | 0        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following config values are set as admin:
      | grade_item_advanced | hiddenuntil |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name           | questiontext              |
      | Test questions   | truefalse | First question | Answer the first question |
    And the following "activities" exist:
      | activity   | name           | course | idnumber | attempts | gradepass | completion | completionpassgrade | completionusegrade |
      | quiz       | Test quiz name | C1     | quiz1    | 2        | 5.00      | 2          | 1                   | 1                  |
    And quiz "Test quiz name" contains the following questions:
      | question       | page |
      | First question | 1    |
    And user "student1" has attempted "Test quiz name" with responses:
      | slot | response |
      |   1  | False    |
    And user "student2" has attempted "Test quiz name" with responses:
      | slot | response |
      |   1  | False    |
    And the following "core_badges > Badge" exists:
      | name        | Course Badge                 |
      | status      | 0                            |
      | type        | 2                            |
      | course      | C1                           |
      | description | Course badge description     |
      | image       | badges/tests/behat/badge.png |
    And I am on the "Course 1" course page logged in as teacher1
    And I navigate to "Badges" in current page administration
    And I press "Manage badges"
    And I follow "Course Badge"
    And I select "Criteria" from the "jump" singleselect
    And I set the field "type" to "Activity completion"
    And I set the field "Quiz - Test quiz name" to "1"
    And I press "Save"

  Scenario: Student does not earn a badge using activity completion when does not get passing grade
    Given I press "Enable access"
    And I press "Continue"
    And I should see "Recipients (0)"
    # Pass grade for student1.
    Given user "student1" has attempted "Test quiz name" with responses:
      | slot | response |
      | 1    | True     |
    # Fail grade for student2.
    And user "student2" has attempted "Test quiz name" with responses:
      | slot | response |
      | 1    | False    |
    And I navigate to "Badges > Manage badges" in current page administration
    And I follow "Course Badge"
    Then I should see "Recipients (1)"
    And I select "Recipients (1)" from the "jump" singleselect
    And I should see "Student 1"
    And I should not see "Student 2"

  Scenario: Previously graded pass/fail students should earn a badge after enabling a badge
    # Pass grade for student1.
    Given user "student1" has attempted "Test quiz name" with responses:
      | slot | response |
      | 1    | True     |
    # Fail grade for student2.
    And user "student2" has attempted "Test quiz name" with responses:
      | slot | response |
      | 1    | False    |
    # Enable badge access once students have completed the activity.
    When I press "Enable access"
    And I press "Continue"
    # Only student1 should earn the badge because student2 did not pass the quiz.
    Then I should see "Recipients (1)"
    And I select "Recipients (1)" from the "jump" singleselect
    And I should see "Student 1"
    And I should not see "Student 2"
