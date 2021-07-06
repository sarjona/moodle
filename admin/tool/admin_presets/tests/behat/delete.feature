@tool @tool_admin_presets
Feature: I can delete a preset

  Background: Create a preset to delete
    Given I log in as "admin"
    And the following config values are set as admin:
      | enablebadges | 1 |
    And I navigate to "Site admin presets" in site administration

  @javascript
  Scenario: Preset settings are delete
    Given I open the action menu in "Lite" "table_row"
    And I choose "Delete" in the open action menu
    And I should see "Delete preset: confirm"
    And I click on "Cancel" "button"
    And I should see "Presets allow you to easily switch between different site admin configurations."
    And I should see "Moodle with all of the most popular features"
    And I open the action menu in "Lite" "table_row"
    When I choose "Delete" in the open action menu
    And I should not see "This preset has been previously applied"
    And I click on "Continue" "button"
    And I should see "Presets allow you to easily switch between different site admin configurations."
    Then I should not see "Moodle with all of the most popular features"
    And "Full" "table_row" should exist

  @javascript
  Scenario: Delete preset that has been applied
    Given I open the action menu in "Lite" "table_row"
    And I choose "Show" in the open action menu
    And I click on "Apply" "button"
    And I navigate to "Site admin presets" in site administration
    When I open the action menu in "Lite" "table_row"
    And I choose "Delete" in the open action menu
    Then I should see "This preset has been previously applied"
    And I click on "Continue" "button"
    And I should see "Presets allow you to easily switch between different site admin configurations"
    And I should not see "Moodle with all of the most popular features"
    And "Full" "table_row" should exist

  @javascript
  Scenario: Delete all presets
    Given I open the action menu in "Lite" "table_row"
    And I choose "Delete" in the open action menu
    And I click on "Continue" "button"
    And I open the action menu in "Full" "table_row"
    And I choose "Delete" in the open action menu
    When I click on "Continue" "button"
    Then I should see "You don't have any site admin preset."
    And "Lite" "table_row" should not exist
    And "Full" "table_row" should not exist
