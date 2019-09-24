@editor @editor_atto @atto @atto_h5p @_file_upload @_switch_iframe
Feature: Add h5ps to Atto
  To write rich text - I need to add h5ps.

  Background:
    Given the following "courses" exist:
      | shortname | fullname |
      | C1        | Course 1 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And the following "activities" exist:
      | activity | name       | intro      | introformat | course | content  | contentformat | idnumber |
      | page     | PageName1  | PageDesc1  | 1           | C1     | H5Ptest  | 1             | 1        |

  @javascript
  Scenario: Insert an embedded h5p
    Given I log in as "admin"
    And I am on "Course 1" course homepage
    And I follow "PageName1"
    And I navigate to "Edit settings" in current page administration
    And I click on "Insert H5P" "button" in the "#fitem_id_page" "css_element"
    And I set the field "Enter URL" to "https://h5p.org/h5p/embed/576651"
    And I click on "Save H5P" "button" in the "H5P properties" "dialogue"
    And I wait until the page is ready
    When I click on "Save and display" "button"
    And I switch to "h5pcontent" iframe
    Then ".h5p-iframe" "css_element" should exist

  @javascript
  Scenario: Insert an h5p file
    Given I log in as "admin"
    And I change window size to "large"
    And I follow "Manage private files..."
    And I upload "lib/editor/atto/tests/fixtures/ipsums.h5p" file to "Files" filemanager
    And I click on "Save changes" "button"
    And I am on "Course 1" course homepage
    And I follow "PageName1"
    And I navigate to "Edit settings" in current page administration
    And I click on "Insert H5P" "button" in the "#fitem_id_page" "css_element"
    And I click on "H5P file" "link" in the "H5P properties" "dialogue"
    And I click on "Browse repositories..." "button" in the "H5P properties" "dialogue"
    And I click on "Private files" "link" in the ".fp-repo-area" "css_element"
    And I click on "ipsums.h5p" "link"
    And I click on "Select this file" "button"
    And I click on "Save H5P" "button" in the "H5P properties" "dialogue"
    And I wait until the page is ready
    When I click on "Save and display" "button"
    Then ".h5p-file-placeholder" "css_element" should exist

  @javascript
  Scenario: Test an invalid url
    Given I log in as "admin"
    And I am on "Course 1" course homepage
    And I follow "PageName1"
    And I navigate to "Edit settings" in current page administration
    And I click on "Insert H5P" "button" in the "#fitem_id_page" "css_element"
    And I set the field "Enter URL" to "ftp://h5p.org/h5p/embed/576651"
    When I click on "Save H5P" "button" in the "H5P properties" "dialogue"
    And I wait until the page is ready
    Then I should see "Invalid URL" in the "H5P properties" "dialogue"

  @javascript
  Scenario: No h5p capabilities
    Given the following "permission overrides" exist:
    | capability | permission | role | contextlevel | reference |
    | atto/h5p:addembed | Prohibit | editingteacher | Course | C1 |
    | atto/h5p:upload | Prohibit | editingteacher | Course | C1 |
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "PageName1"
    When I navigate to "Edit settings" in current page administration
    Then "Insert H5P" "button" should not exist

  @javascript
  Scenario: No embed h5p capabilities
    Given the following "permission overrides" exist:
    | capability | permission | role | contextlevel | reference |
    | atto/h5p:addembed | Prohibit | editingteacher | Course | C1 |
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "PageName1"
    When I navigate to "Edit settings" in current page administration
    And I click on "Insert H5P" "button" in the "#fitem_id_page" "css_element"
    Then I should not see "H5P URL" in the "H5P properties" "dialogue"

  @javascript
  Scenario: No upload h5p capabilities
    Given the following "permission overrides" exist:
    | capability | permission | role | contextlevel | reference |
    | atto/h5p:upload | Prohibit | editingteacher | Course | C1 |
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "PageName1"
    When I navigate to "Edit settings" in current page administration
    And I click on "Insert H5P" "button" in the "#fitem_id_page" "css_element"
    Then I should not see "H5P file" in the "H5P properties" "dialogue"