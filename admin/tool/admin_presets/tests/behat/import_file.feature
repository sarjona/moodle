@tool @tool_admin_presets @tool_admin_presets_import_file
Feature: I can delete a preset

  Background: Create a preset to delete
    Given I log in as "admin"
    And I am on site homepage
    And I navigate to "Admin presets" in site administration
    And I click on "Import settings" "link"

  @javascript @_file_upload
  Scenario: Preset settings are delete
    And I upload "admin/tool/admin_presets/tests/files/TestBehatImport.xml" file to "Select file" filemanager
    And I click on "Save changes" "button"
    Then I should see "Load settings: select settings"
    And I navigate to "Admin presets" in site administration
    Then I should see "TestBehatImport" in the ".generaltable" "css_element"
