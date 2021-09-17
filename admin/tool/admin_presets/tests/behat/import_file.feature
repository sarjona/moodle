@tool @tool_admin_presets @tool_admin_presets_import_file
Feature: I can upload a preset file

  Background: Go to the Import settings page
    Given I log in as "admin"
    And I am on site homepage
    And I navigate to "Admin presets" in site administration
    And I click on "Import settings" "link_or_button"

  @javascript @_file_upload
  Scenario: Preset file is upload
    And I upload "admin/tool/admin_presets/tests/fixtures/import_file_behat.xml" file to "Select file" filemanager
    And I click on "Save changes" "button"
    Then I should see "Load settings: select settings"
    And I navigate to "Admin presets" in site administration
    Then I should see "TestBehatImport" in the ".generaltable" "css_element"
