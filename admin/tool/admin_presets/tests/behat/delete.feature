@tool @tool_admin_presets
Feature: I can delete a preset

  Background: Create a preset to delete
    Given I log in as "admin"
    And the following config values are set as admin:
      | enablebadges | 1 |
    And I navigate to "Site admin presets" in site administration
    And I click on "Export settings" "link_or_button"
    And I set the following fields to these values:
      | Name | My preset to be deleted |
    And I press "Save changes"

  @javascript
  Scenario: Preset settings are delete
    Given I open the action menu in "My preset to be deleted" "table_row"
    And I choose "Delete" in the open action menu
    And I should see "Delete preset: confirm"
    And I click on "Cancel" "button"
    And I should see "Presets: list"
    And "My preset to be deleted" "table_row" should exist
    And I open the action menu in "My preset to be deleted" "table_row"
    When I choose "Delete" in the open action menu
    And I should not see "This preset has been previously applied, if you delete it you can not return to the previous state"
    And I click on "Continue" "button"
    And I should see "Presets: list"
    Then "My preset to be deleted" "table_row" should not exist

  @javascript
  Scenario: Delete preset that has been applied
    Given I open the action menu in "Lite Moodle" "table_row"
    And I choose "Show" in the open action menu
    And I click on "Apply" "button"
    And I navigate to "Site admin presets" in site administration
    When I open the action menu in "Lite Moodle" "table_row"
    And I choose "Delete" in the open action menu
    Then I should see "This preset has been previously applied, if you delete it you can not return to the previous state"
    And I click on "Continue" "button"
    And I should see "Presets: list"
    And "Lite Moodle" "table_row" should not exist

  @javascript
  Scenario: Delete all presets
    Given I open the action menu in "My preset to be deleted" "table_row"
    And I choose "Delete" in the open action menu
    And I click on "Continue" "button"
    And I open the action menu in "Lite Moodle" "table_row"
    And I choose "Delete" in the open action menu
    And I click on "Continue" "button"
    And I open the action menu in "Default Moodle" "table_row"
    And I choose "Delete" in the open action menu
    And I click on "Continue" "button"
    And I should see "You don't have presets"
