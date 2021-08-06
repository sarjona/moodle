@tool @tool_admin_presets @tool_admin_presets_revert
Feature: I can revert changes after a load

  @javascript
  Scenario: Load changes and revert them
    Given I log in as "admin"
    And I am on site homepage
    And I navigate to "Admin presets > Export settings" in site administration
    And I set the following fields to these values:
      | Name | My preset |
    And I press "Save changes"
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
    And I navigate to "Admin presets > List presets" in site administration
    And I click on "load" "link" in the "My preset" "table_row"
    And I press "Load selected settings"
    And I navigate to "Admin presets > List presets" in site administration
    And I click on "revert" "link" in the "My preset" "table_row"
    And I follow "revert"
    Then I should see "Settings successfully restored"
    And I should see "Enable portfolios" in the ".admin_presets_applied" "css_element"
    And I should see "Enable badges" in the ".admin_presets_applied" "css_element"
    And I should see "Feedback plugin" in the ".admin_presets_applied" "css_element"
    And I should see "Custom field" in the ".admin_presets_applied" "css_element"
    And I navigate to "Advanced features" in site administration
    And the field "Enable portfolios" matches value "1"
    And the field "Enable badges" matches value "0"
    And I navigate to "Plugins > Activity modules > Assignment > Assignment settings" in site administration
    And the field "Feedback plugin" matches value "File feedback"
    And I navigate to "Plugins > Blocks > Course overview" in site administration
    And I set the field "Custom field" to "0"
