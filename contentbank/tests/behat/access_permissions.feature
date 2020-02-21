@core @core_contentbank
Feature: Access permission to Content Bank
  In order to control access to Content bank
  As an admin
  I need to be able to configure users' permissions

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | user1    | User      | 1 | user1@example.com |

  Scenario: Users can't access content bank
    When I log in as "user1"
    Then I should not see "Content bank"

  Scenario: Site level managers can access content bank
    Given the following "role assigns" exist:
      | user  | role    | contextlevel | reference |
      | user1 | manager | System       |           |
    When I log in as "user1"
    Then I should see "Content bank"