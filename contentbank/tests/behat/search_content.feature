@core @core_contentbank @contentbank_h5p @_file_upload @javascript
Feature: Search content in the content bank
  In order to find easily content in the content bank
  As an admin
  I need to be able to search content in the content bank

  Background:
    Given the following "contentbank contents" exist:
        | name                 | contenttype       | contextid |
        | santjordi.h5p        | contenttype_h5p   | 1         |
        | santjordi_rose.h5p   | contenttype_h5p   | 1         |
        | SantJordi_book       | contenttype_h5p   | 1         |
        | Dragon_santjordi.h5p | contenttype_h5p   | 1         |
        | princess.h5p         | contenttype_h5p   | 1         |
        | mathsbook.h5p        | contenttype_h5p   | 1         |
        | historybook.h5p      | contenttype_h5p   | 1         |
        | santvicenc.h5p       | contenttype_h5p   | 1         |

  Scenario: Admins can search content in the content bank
    Given I log in as "admin"
    And I click on "Content bank" "link"
    And I should see "santjordi.h5p"
    And "Clear search input" "button" should not exist
    And I should not see "items found"
    When I set the field "Search" to "book"
    Then "Clear search input" "button" should exist
    And I should see "3 items found"
    And I should see "SantJordi_book"
    And I should see "mathsbook.h5p"
    And I should see "historybook.h5p"
    And I set the field "Search" to "sant"
    And "Clear search input" "button" should exist
    And I should see "5 items found"
    And I set the field "Search" to "santjordi"
    And I should see "4 items found"
    And I should see "santjordi.h5p"
    And I should see "santjordi_rose.h5p"
    And I should see "SantJordi_book"
    And I should see "Dragon_santjordi.h5p"
    And I click on "Clear search input" "button"
    And "Clear search input" "button" should not exist
    And I should not see "items found"
    And I set the field "Search" to ".h5p"
    And "Clear search input" "button" should exist
    And I should see "7 items found"
    And I set the field "Search" to "friend"
    And I should see "0 items found"
