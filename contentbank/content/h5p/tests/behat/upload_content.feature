@core @core_contentbank @contentbank_h5p @_file_upload @_switch_iframe @javascript
Feature: H5P file upload to content bank
  In order import new H5P content to content bank
  As an admin
  I need to be able to upload a new .h5p file to content bank

  Background:
    Given I log in as "admin"
    And I follow "Manage private files..."
    And I upload "h5p/tests/fixtures/filltheblanks.h5p" file to "Files" filemanager
    And I click on "Save changes" "button"

  Scenario: Admins can upload .h5p extension files to content bank
    Given I click on "Content bank" "link"
    And I should not see "filltheblanks.h5p"
    When I click on "Upload" "link"
    And I click on "Choose a file..." "button"
    And I click on "Private files" "link" in the ".fp-repo-area" "css_element"
    And I click on "filltheblanks.h5p" "link"
    And I click on "Select this file" "button"
    And I click on "Save changes" "button"
    And I wait until the page is ready
    Then I should see "filltheblanks.h5p"

  Scenario: Admins can see uploaded H5P contents
    Given I click on "Content bank" "link"
    And I should not see "filltheblanks.h5p"
    When I click on "Upload" "link"
    And I click on "Choose a file..." "button"
    And I click on "Private files" "link" in the ".fp-repo-area" "css_element"
    And I click on "filltheblanks.h5p" "link"
    And I click on "Select this file" "button"
    And I click on "Save changes" "button"
    And I wait until the page is ready
    And I click on "filltheblanks.h5p" "link"
    Then I switch to "h5p-player" class iframe
    And I switch to "h5p-iframe" class iframe
    And I should see "Of which countries"

  Scenario: Users can't see content managed by disabled plugins
    Given I click on "Content bank" "link"
    When I click on "Upload" "link"
    And I click on "Choose a file..." "button"
    And I click on "Private files" "link" in the ".fp-repo-area" "css_element"
    And I click on "filltheblanks.h5p" "link"
    And I click on "Select this file" "button"
    And I click on "Save changes" "button"
    And I wait until the page is ready
    Then I should see "filltheblanks.h5p"
    And I navigate to "Plugins > Content bank > Manage content bank content types" in site administration
    And I click on "Disable" "icon" in the "H5P" "table_row"
    And I wait until the page is ready
    Given I click on "Content bank" "link"
    Then I should not see "filltheblanks.h5p"