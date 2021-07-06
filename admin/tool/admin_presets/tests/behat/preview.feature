@tool @tool_admin_presets
Feature: I can preview a preset

  @javascript
  Scenario: Preset settings are previewed
    Given I log in as "admin"
    And I navigate to "Site admin presets" in site administration
    And I open the action menu in "Lite" "table_row"
    When I choose "Show" in the open action menu
    Then I should see "Load settings: select settings"
    And "Lite" "table_row" should exist
