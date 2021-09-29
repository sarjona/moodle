@tool @tool_admin_presets
Feature: I should see pre-installed presets in my presets list

  Background:
    Given I log in as "admin"
    And I navigate to "Admin presets" in site administration

  @javascript
  Scenario: Lite Moodle preset is pre-installed
    Given "Lite Moodle" "table_row" should exist
    When I open the action menu in "Lite Moodle" "table_row"
    And I choose "Show" in the open action menu
    Then I should see "Load settings: select settings"
    And "Lite Moodle" "table_row" should exist
    And I should see "Users" in the "#settings_tree_div" "css_element"
    And I should see "Plugins" in the "#settings_tree_div" "css_element"
    And I should see "Appearance" in the "#settings_tree_div" "css_element"
    And I should see "Advanced features" in the "#settings_tree_div" "css_element"
    And I should not see "Courses" in the "#settings_tree_div" "css_element"
    And I should not see "Grades" in the "#settings_tree_div" "css_element"
    And I should not see "Analytics" in the "#settings_tree_div" "css_element"
    And I should not see "Competencies" in the "#settings_tree_div" "css_element"
    And I should not see "Badges" in the "#settings_tree_div" "css_element"
    And I should not see "H5P" in the "#settings_tree_div" "css_element"
    And I should not see "License" in the "#settings_tree_div" "css_element"
    And I should not see "Location" in the "#settings_tree_div" "css_element"
    And I should not see "Language" in the "#settings_tree_div" "css_element"
    And I should not see "Messaging" in the "#settings_tree_div" "css_element"
    And I should not see "Security" in the "#settings_tree_div" "css_element"
    And I should not see "Appearence" in the "#settings_tree_div" "css_element"
    And I should not see "Front page" in the "#settings_tree_div" "css_element"
    And I should not see "Server" in the "#settings_tree_div" "css_element"
    And I should not see "Mobile app" in the "#settings_tree_div" "css_element"
    And I should not see "Development" in the "#settings_tree_div" "css_element"
    And I should not see "Moodle services" in the "#settings_tree_div" "css_element"
    And I should not see "Feedback settings" in the "#settings_tree_div" "css_element"
    And I click on "Apply" "button"
    And I should see "Load settings: applied changes"
    And "Enable comments" "table_row" should exist
    And "Enable tags functionality" "table_row" should exist
    And "Enable notes" "table_row" should exist
    And "Enable blogs" "table_row" should exist
    And "Enable badges" "table_row" should exist
    And "Analytics" "table_row" should exist
    And "Enable competencies" "table_row" should exist
    And "Show data retention summary" "table_row" should exist
    And "Maximum number of attachments" "table_row" should exist
    And I should see "3" in the "Maximum number of attachments" "table_row"
    And "User menu items" "table_row" should exist

  @javascript
  Scenario: Default Moodle preset is pre-installed
    Given "Default Moodle" "table_row" should exist
    When I open the action menu in "Default Moodle" "table_row"
    And I choose "Show" in the open action menu
    Then I should see "Load settings: select settings"
    And "Default Moodle" "table_row" should exist
    And I should see "Users" in the "#settings_tree_div" "css_element"
    And I should see "Plugins" in the "#settings_tree_div" "css_element"
    And I should see "Appearance" in the "#settings_tree_div" "css_element"
    And I should see "Advanced features" in the "#settings_tree_div" "css_element"
    And I should not see "Courses" in the "#settings_tree_div" "css_element"
    And I should not see "Grades" in the "#settings_tree_div" "css_element"
    And I should not see "Analytics" in the "#settings_tree_div" "css_element"
    And I should not see "Competencies" in the "#settings_tree_div" "css_element"
    And I should not see "Badges" in the "#settings_tree_div" "css_element"
    And I should not see "H5P" in the "#settings_tree_div" "css_element"
    And I should not see "License" in the "#settings_tree_div" "css_element"
    And I should not see "Location" in the "#settings_tree_div" "css_element"
    And I should not see "Language" in the "#settings_tree_div" "css_element"
    And I should not see "Messaging" in the "#settings_tree_div" "css_element"
    And I should not see "Security" in the "#settings_tree_div" "css_element"
    And I should not see "Appearence" in the "#settings_tree_div" "css_element"
    And I should not see "Front page" in the "#settings_tree_div" "css_element"
    And I should not see "Server" in the "#settings_tree_div" "css_element"
    And I should not see "Mobile app" in the "#settings_tree_div" "css_element"
    And I should not see "Development" in the "#settings_tree_div" "css_element"
    And I should not see "Moodle services" in the "#settings_tree_div" "css_element"
    And I should not see "Feedback settings" in the "#settings_tree_div" "css_element"
    And I click on "Apply" "button"
    And I should see "Load settings: applied changes"
    And I should see "All preset settings skipped, they are already loaded"
    And "Enable comments" "table_row" should exist
    And "Enable tags functionality" "table_row" should exist
    And "Enable notes" "table_row" should exist
    And "Enable blogs" "table_row" should exist
    And "Enable badges" "table_row" should exist
    And "Analytics" "table_row" should exist
    And "Enable competencies" "table_row" should exist
    And "Show data retention summary" "table_row" should exist
    And "Maximum number of attachments" "table_row" should exist
    And I should see "9" in the "Maximum number of attachments" "table_row"
    And "User menu items" "table_row" should exist
