@tool @tool_admin_presets @tool_admin_presets_delete
Feature: I can delete a preset

  Background: Create a preset to delete
    Given I log in as "admin"
    And I am on site homepage
    And I navigate to "Admin presets" in site administration
    And I click on "Export settings" "link_or_button"
    And I set the following fields to these values:
      | Name | My preset delete |
    And I press "Save changes"

  @javascript
  Scenario: Preset settings are delete
    And I click on "Actions" "link_or_button" in the "My preset delete" "table_row"
    And I click on "Delete" "link" in the "My preset delete" "table_row"
    And I should see "Delete preset: confirm"
    And I click on "Continue" "button"
    And I should see "Presets: list presets"
    And I should see "You don't have presets" in the "#id_nopresets" "css_element"