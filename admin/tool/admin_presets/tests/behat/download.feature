@tool @tool_admin_presets
Feature: I can download a preset

  @javascript
  Scenario: Preset settings are download
    Given I log in as "admin"
    And I navigate to "Admin presets" in site administration
    When I open the action menu in "Lite Moodle" "table_row"
    Then following "Download" "link" in the "Lite Moodle" "table_row" should download between "0" and "1500000" bytes
