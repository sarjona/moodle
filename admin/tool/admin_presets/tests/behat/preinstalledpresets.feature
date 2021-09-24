@tool @tool_admin_presets @tool_admin_presets_preinstalledpresets
Feature: I should see pre-installed presets in my presets list

  Background: Login and go on my presets list
    Given I log in as "admin"
    And I am on site homepage
    And I navigate to "Admin presets" in site administration

  @javascript
  Scenario: I should see the pre-installed presets
    And I should see "Lite" in the ".generaltable tbody" "css_element"
    And I click on "Actions" "link_or_button" in the "Lite" "table_row"
    And I click on "Load" "link" in the "Lite" "table_row"
    And I should see "Load settings: select settings"
    And I should see "Lite" in the ".generaltable tbody" "css_element"
    Then I navigate to "Admin presets" in site administration
    And I should see "Standard" in the ".generaltable tbody" "css_element"
    And I click on "Actions" "link_or_button" in the "Standard" "table_row"
    And I click on "Load" "link" in the "Standard" "table_row"
    And I should see "Load settings: select settings"
    And I should see "Standard" in the ".generaltable tbody" "css_element"

    @javascript
    Scenario: I should be able to load one of these presets
      And I should see "Lite" in the ".generaltable tbody" "css_element"
      And I click on "Actions" "link_or_button" in the "Lite" "table_row"
      And I click on "Load" "link" in the "Lite" "table_row"
      And I should see "Load settings: select settings"
      And I should see "Lite" in the ".generaltable tbody" "css_element"
      And I press "Load selected settings"
      Then I should see "Load settings: applied changes"
