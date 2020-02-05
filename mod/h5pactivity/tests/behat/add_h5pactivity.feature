@mod @mod_h5pactivity @_file_upload @_switch_iframe
Feature: Add H5P activity
  In order to let students access a H5P package
  As a teacher
  I need to add H5P activity to a course

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |

  @javascript
  Scenario: Add a h5pactivity activity to a course without upload library capability
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "H5P activity" to section "1"
    And I set the following fields to these values:
      | Name | Awesome H5P package |
      | Description | Description |
    And I upload "h5p/tests/fixtures/ipsums.h5p" file to "Package file" filemanager
    And I click on "Save and display" "button"
    And I wait until the page is ready
    Then I switch to "h5p-player" class iframe
    And I should see "Missing required library"
    And I switch to the main frame

  @javascript
  Scenario: Add a h5pactivity activity to a course
    Given the following "permission overrides" exist:
      | capability                 | permission | role           | contextlevel | reference |
      | moodle/h5p:updatelibraries | Allow      | editingteacher | System       |           |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "H5P activity" to section "1"
    And I set the following fields to these values:
      | Name | Awesome H5P package |
      | Description | Description |
    And I upload "h5p/tests/fixtures/ipsums.h5p" file to "Package file" filemanager
    And I click on "Save and display" "button"
    And I wait until the page is ready
    Then I switch to "h5p-player" class iframe
    And I switch to "h5p-iframe" class iframe
    And I should see "Lorum ipsum"
    And I switch to the main frame
