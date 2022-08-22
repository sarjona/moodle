@mod @mod_data
Feature: Zero state page (no fields created)

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And the following "activities" exist:
      | activity | name               | intro | course | idnumber |
      | data     | Test database name | n     | C1     | data1    |

  @javascript
  Scenario: Teachers see buttons to manage database when there is no field created
    Given I am on the "Test database name" "data activity" page logged in as "teacher1"
    And "Import a preset" "button" should exist
    And I am on the "Test database name" "data activity" page
    And "Create a new field" "button" should exist
    And I click on "Create a new field" "button"
    And I should see "Manage fields"
    And I am on the "Test database name" "data activity" page
    And "Use preset" "button" should exist
    And I click on "Use preset" "button"
    And I should see "Presets"

  @javascript @_file_upload
  Scenario: Teachers can import preset from the zero state page
    Given I am on the "Test database name" "data activity" page logged in as "teacher1"
    And "Import a preset" "button" should exist
    When I click on "Import a preset" "button"
    And I wait until the page is ready
    And I upload "mod/data/tests/fixtures/image_gallery_preset.zip" file to "Preset file" filemanager
    Then I click on "Import preset and apply" "button" in the ".modal-dialog" "css_element"
    And I should see "Manage fields"
    Then I should see "The preset has been successfully applied."
