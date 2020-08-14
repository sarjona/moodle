@core @core_contentbank @contentbank_h5p @_file_upload @javascript
Feature: Rename H5P content
  In order to rename H5P content in the content bank
  As an admin
  I need to be able to edit an H5P content title and confirm it is updated into the content bank too

  Background:
    Given the following "contentbank content" exist:
      | contextlevel | reference | contenttype     | user    | contentname          | filepath                               |
      | System       |           | contenttype_h5p | admin   | filltheblanks.h5p    | /h5p/tests/fixtures/filltheblanks.h5p  |
    And I log in as "admin"
    And I am on site homepage
    And I turn editing mode on
    And I add the "Navigation" block if not present
    And I configure the "Navigation" block
    And I set the following fields to these values:
      | Page contexts | Display throughout the entire site |
    And I press "Save changes"

  Scenario: The H5P title updates the content name too
    Given I click on "Site pages" "list_item" in the "Navigation" "block"
    And I click on "Content bank" "link" in the "Navigation" "block"
    And I follow "filltheblanks.h5p"
    And I click on "Edit" "link"
    And I switch to "h5p-editor-iframe" class iframe
    And the field "Title" matches value "Geography"
    And I set the field "Title" to "New title"
    And I switch to the main frame
    When I click on "Save" "button"
    And I should see "New title" in the "h1" "css_element"
    And I click on "Edit" "link"
    And I switch to "h5p-editor-iframe" class iframe
    Then the field "Title" matches value "New title"

  Scenario: When an H5P content is renamed from the content bank, the H5P title is updated too
    Given I click on "Site pages" "list_item" in the "Navigation" "block"
    And I click on "Content bank" "link" in the "Navigation" "block"
    And I follow "filltheblanks.h5p"
    And I click on "Edit" "link"
    And I switch to "h5p-editor-iframe" class iframe
    And the field "Title" matches value "Geography"
    And I switch to the main frame
    And I click on "Cancel" "button"
    And I open the action menu in "region-main-settings-menu" "region"
    When I choose "Rename" in the open action menu
    And I set the field "Content name" to "New name"
    And I click on "Rename" "button"
    And I should see "New name" in the "h1" "css_element"
    And I click on "Edit" "link"
    And I switch to "h5p-editor-iframe" class iframe
    Then the field "Title" matches value "New name"
