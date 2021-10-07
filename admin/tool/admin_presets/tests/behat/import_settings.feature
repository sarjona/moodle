@tool @tool_admin_presets
Feature: I can export and import site settings

  Background: Create a preset to load
    Given I log in as "admin"
    And I navigate to "Site admin presets" in site administration
    And I click on "Export settings" "link_or_button"
    And I set the following fields to these values:
      | Name | My preset |
    And I press "Save changes"

  @javascript
  Scenario: Preset settings are applied
    And the following config values are set as admin:
      | enableportfolios | 1 |
      | enablebadges | 0 |
    # TODO: Use generators to set this settings to save time.
    And I navigate to "Plugins > Activity modules > Assignment > Assignment settings" in site administration
    And I set the field "Feedback plugin" to "File feedback"
    And I press "Save changes"
    And I navigate to "Plugins > Blocks > Course overview" in site administration
    And I set the field "Custom field" to "1"
    And I press "Save changes"
    And I navigate to "Site admin presets" in site administration
    And I click on "Actions" "link_or_button" in the "My preset" "table_row"
    And I click on "Show" "link" in the "My preset" "table_row"
    And I press "Apply"
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
    And I navigate to "Site admin presets" in site administration
    And I click on "Actions" "link_or_button" in the "My preset" "table_row"
    And I click on "Show" "link" in the "My preset" "table_row"
    When I press "Apply"
    Then I should see "All preset settings skipped, they are already loaded"
    And I should not see "Settings applied"
