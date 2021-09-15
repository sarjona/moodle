@tool @tool_admin_presets @tool_admin_presets_download
Feature: I can delete a preset

  Background: Create a preset to delete
    Given I log in as "admin"
    And I am on site homepage
    And I navigate to "Admin presets" in site administration
    And I click on "Export settings" "link"
    And I set the following fields to these values:
      | Name | My preset download |
    And I press "Save changes"

  @javascript
  Scenario: Preset settings are delete
    Then I should see "My preset download" in the "My preset download" "table_row"
    And following "download" "link" in the "My preset download" "table_row" should download between "0" and "1500000" bytes