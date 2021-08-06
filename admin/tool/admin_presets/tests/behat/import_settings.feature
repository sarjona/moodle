@tool @tool_admin_presets @tool_admin_presets_import
Feature: I can export and import site settings

  Background:
    Given I log in as "admin"
    And I am on site homepage
    And I navigate to "Admin presets > Export settings" in site administration
    And I set the following fields to these values:
      | Name | My preset |
    And I press "Save changes"

  @javascript
  Scenario: Preset settings are applied
    And I navigate to "Advanced features" in site administration
    And I set the field "Enable portfolios" to "1"
    And I set the field "Enable badges" to "0"
    And I press "Save changes"
    And I navigate to "Plugins > Activity modules > Assignment > Assignment settings" in site administration
    And I set the field "Feedback plugin" to "File feedback"
    And I press "Save changes"
    And I navigate to "Plugins > Blocks > Course overview" in site administration
    And I set the field "Custom field" to "1"
    And I press "Save changes"
    When I am on site homepage
    And I navigate to "Admin presets > List presets" in site administration
    And I click on "load" "link" in the "My preset" "table_row"
    And I press "Load selected settings"
    Then I should not see "All preset settings skipped, they are already loaded"
    And I should see "Settings applied"
    And I should see "Enable portfolios" in the ".admin_presets_applied" "css_element"
    And I should see "Enable badges" in the ".admin_presets_applied" "css_element"
    And I should see "Feedback plugin" in the ".admin_presets_applied" "css_element"
    And I should see "Custom field" in the ".admin_presets_applied" "css_element"
    And I navigate to "Advanced features" in site administration
    And the field "Enable portfolios" matches value "0"
    And the field "Enable badges" matches value "1"
    And I navigate to "Plugins > Activity modules > Assignment > Assignment settings" in site administration
    And the field "Feedback plugin" matches value "Feedback comments"
    And I navigate to "Plugins > Blocks > Course overview" in site administration
    And the field "Custom field" matches value "0"

  @javascript
  Scenario: Settings don't change if you import what you just exported
    When I click on "load" "link" in the "My preset" "table_row"
    And I press "Load selected settings"
    Then I should see "All preset settings skipped, they are already loaded"
    And I should not see "Settings applied"
