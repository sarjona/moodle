@tool @tool_admin_presets @tool_admin_presets_preview
Feature: I can preview a preset

  Background: Create a preset to preview
    Given I log in as "admin"
    And I am on site homepage
    And I navigate to "Admin presets" in site administration
    And I click on "Export settings" "link_or_button"
    And I set the following fields to these values:
      | Name | My preset preview |
    And I press "Save changes"

  @javascript
  Scenario: Preset settings are previewed
    And I click on "Actions" "link_or_button" in the "My preset preview" "table_row"
    And I click on "Load" "link" in the "My preset preview" "table_row"
    And I should see "Load settings: select settings"
    And I should see "My preset preview" in the ".generaltable tbody" "css_element"
