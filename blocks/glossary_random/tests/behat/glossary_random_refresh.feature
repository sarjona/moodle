@block @block_glossary_random
Feature: Random glossary entry block does periodically refresh
  In order to reload an entry from the glossary
  As a user
  I can see the refresh button in a random glossary block

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | student1 | Sam1      | Student1 | student1@example.com |
      | teacher1 | Terry1    | Teacher1 | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name               | intro                     | displayformat  | course | idnumber |
      | glossary | Test glossary name | Test glossary description | fullwithauthor | C1     | g1       |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test glossary name"
    And I add a glossary entry with the following data:
      | Concept | Eggplant |
      | Definition | Sour eggplants |
    And I log out

  Scenario: Admin configuration value shows up as default in block setup.
    Given I log in as "admin"
    And I navigate to "Plugins > Blocks > Random glossary entry" in site administration
    And I set the field "Show refresh button" to "No"
    When I press "Save changes"
    Then I should see "Changes saved"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Random glossary entry" block
    And I configure the "Random glossary entry" block
    Then the field "id_config_showrefreshbutton" matches value "No"