@tool @tool_oauth2 @external
Feature: Basic OAuth2 functionality
  In order to use them later for authentication or repository plugins
  As an administrator
  I need to add a manage customised OAuth2 services.

  Background:
    Given I log in as "admin"
    And I navigate to "Server > OAuth 2 services" in site administration

  Scenario: Create, edit and delete standard service for Google
    Given I press "Google"
    And I should see "Create new service: Google"
    And I set the following fields to these values:
      | Name                       | Testing service                           |
      | Client ID                  | thisistheclientid                         |
      | Client secret              | supersecret                               |
    When I press "Save changes"
    Then I should see "Changes saved"
    And I should see "Testing service"
    And "Configured" "icon" should exist in the "Testing service" "table_row"
    And "Allow login" "icon" should exist in the "Testing service" "table_row"
    And "Service discovery successful" "icon" should exist in the "Testing service" "table_row"
    And I click on "Configure endpoints" "link" in the "Testing service" "table_row"
    And I should see "https://accounts.google.com/.well-known/openid-configuration" in the "discovery_endpoint" "table_row"
    And I should see "authorization_endpoint"
    And I follow "OAuth 2 services"
    And I click on "Configure user field mappings" "link" in the "Testing service" "table_row"
    And I should see "firstname" in the "given_name" "table_row"
    And I should see "middlename" in the "middle_name" "table_row"
    And I follow "OAuth 2 services"
    And I click on "Edit" "link" in the "Testing service" "table_row"
    And I set the following fields to these values:
      | Name                       | Testing service modified                 |
    And I press "Save changes"
    And I should see "Changes saved"
    And I should see "Testing service modified"
    And I click on "Delete" "link" in the "Testing service modified" "table_row"
    And I should see "Are you sure you want to delete the identity issuer \"Testing service modified\"?"
    And I press "Continue"
    And I should see "Identity issuer deleted"
    And I should not see "Testing service modified"

  Scenario: Create, edit and delete standard service for Microsoft
    Given I press "Microsoft"
    And I should see "Create new service: Microsoft"
    And I set the following fields to these values:
      | Name                       | Testing service                           |
      | Client ID                  | thisistheclientid                         |
      | Client secret              | supersecret                               |
    When I press "Save changes"
    Then I should see "Changes saved"
    And I should see "Testing service"
    And "Configured" "icon" should exist in the "Testing service" "table_row"
    And "Allow login" "icon" should exist in the "Testing service" "table_row"
    And I should see "-" in the "Testing service" "table_row"
    And I click on "Configure endpoints" "link" in the "Testing service" "table_row"
    And I should see "authorization_endpoint"
    And I should not see "discovery_endpoint"
    And I follow "OAuth 2 services"
    And I click on "Configure user field mappings" "link" in the "Testing service" "table_row"
    And I should see "firstname" in the "givenName" "table_row"
    And I follow "OAuth 2 services"
    And I click on "Edit" "link" in the "Testing service" "table_row"
    And I set the following fields to these values:
      | Name                       | Testing service modified                 |
    And I press "Save changes"
    And I should see "Changes saved"
    And I should see "Testing service modified"
    And I click on "Delete" "link" in the "Testing service modified" "table_row"
    And I should see "Are you sure you want to delete the identity issuer \"Testing service modified\"?"
    And I press "Continue"
    And I should see "Identity issuer deleted"
    And I should not see "Testing service modified"

  Scenario: Create, edit and delete standard service for Facebook
    Given I press "Facebook"
    And I should see "Create new service: Facebook"
    And I set the following fields to these values:
      | Name                       | Testing service                           |
      | Client ID                  | thisistheclientid                         |
      | Client secret              | supersecret                               |
    When I press "Save changes"
    Then I should see "Changes saved"
    And I should see "Testing service"
    And "Configured" "icon" should exist in the "Testing service" "table_row"
    And "Allow login" "icon" should exist in the "Testing service" "table_row"
    And I should see "-" in the "Testing service" "table_row"
    And I click on "Configure endpoints" "link" in the "Testing service" "table_row"
    And I should see "authorization_endpoint"
    And I should not see "discovery_endpoint"
    And I follow "OAuth 2 services"
    And I click on "Configure user field mappings" "link" in the "Testing service" "table_row"
    And I should see "firstname" in the "first_name" "table_row"
    And I follow "OAuth 2 services"
    And I click on "Edit" "link" in the "Testing service" "table_row"
    And I set the following fields to these values:
      | Name                       | Testing service modified                 |
    And I press "Save changes"
    And I should see "Changes saved"
    And I should see "Testing service modified"
    And I click on "Delete" "link" in the "Testing service modified" "table_row"
    And I should see "Are you sure you want to delete the identity issuer \"Testing service modified\"?"
    And I press "Continue"
    And I should see "Identity issuer deleted"
    And I should not see "Testing service modified"

  @javascript
  Scenario: Create, edit and delete standard service for Nextcloud
    Given I press "Nextcloud"
    And I should see "Create new service: Nextcloud"
    And I set the following fields to these values:
      | Name                       | Testing service                           |
      | Client ID                  | thisistheclientid                         |
      | Client secret              | supersecret                               |
    And I press "Save changes"
    And I should see "You must supply a value here."
    And I set the following fields to these values:
      | Service base URL           | https://dummy.local/nextcloud/            |
    When I press "Save changes"
    Then I should see "Changes saved"
    And I should see "Testing service"
    And "Configured" "icon" should exist in the "Testing service" "table_row"
    And "Do not allow login" "icon" should exist in the "Testing service" "table_row"
    And I should see "-" in the "Testing service" "table_row"
    And I click on "Configure endpoints" "link" in the "Testing service" "table_row"
    And I should see "authorization_endpoint"
    And I should not see "discovery_endpoint"
    And I follow "OAuth 2 services"
    And I click on "Configure user field mappings" "link" in the "Testing service" "table_row"
    And I should see "username" in the "ocs-data-id" "table_row"
    And I follow "OAuth 2 services"
    And I click on "Edit" "link" in the "Testing service" "table_row"
    And I set the following fields to these values:
      | Name                       | Testing service modified                 |
    And I press "Save changes"
    And I should see "Could not discover service endpoints"
    And I should see "Testing service modified"
    And I click on "Delete" "link" in the "Testing service modified" "table_row"
    And I should see "Are you sure you want to delete the identity issuer \"Testing service modified\"?"
    And I press "Continue"
    And I should see "Identity issuer deleted"
    And I should not see "Testing service modified"

  Scenario: Create, edit and delete standard service for IMS OBv2.1
    Given I press "IMS OBv2.1"
    And I should see "Create new service: IMS OBv2.1"
    And I set the following fields to these values:
      | Client ID                  | thisistheclientid                         |
      | Client secret              | supersecret                               |
      | Service base URL           | https://dc.imsglobal.org/                 |
    When I press "Save changes"
    Then I should see "Changes saved"
    And I should see "IMS OBv2.1"
    And "Configured" "icon" should exist in the "IMS OBv2.1" "table_row"
    And "Do not allow login" "icon" should exist in the "IMS OBv2.1" "table_row"
    And "Service discovery successful" "icon" should exist in the "IMS OBv2.1" "table_row"
    And the "src" attribute of "table.admintable th img" "css_element" should contain "IMS-Global-Logo.png"
    And I click on "Configure endpoints" "link" in the "IMS OBv2.1" "table_row"
    And I should see "https://dc.imsglobal.org/.well-known/badgeconnect.json" in the "discovery_endpoint" "table_row"
    And I should see "authorization_endpoint"
    And I follow "OAuth 2 services"
    And I click on "Configure user field mappings" "link" in the "IMS OBv2.1" "table_row"
    And I should not see "given_name"
    And I should not see "middle_name"
    And I follow "OAuth 2 services"
    And I click on "Edit" "link" in the "IMS OBv2.1" "table_row"
    And I set the following fields to these values:
      | Name                       | IMS Global                                |
    And I press "Save changes"
    And I should see "Changes saved"
    And I should see "IMS Global"
    And I click on "Delete" "link" in the "IMS Global" "table_row"
    And I should see "Are you sure you want to delete the identity issuer \"IMS Global\"?"
    And I press "Continue"
    And I should see "Identity issuer deleted"
    And I should not see "IMS Global"

  Scenario: Create, edit and delete valid custom OIDC service
    Given I press "Custom"
    And I should see "Create new service: Custom"
    And I set the following fields to these values:
      | Name                       | Google custom                             |
      | Client ID                  | thisistheclientid                         |
      | Client secret              | supersecret                               |
      | Service base URL           | https://accounts.google.com/              |
    When I press "Save changes"
    Then I should see "Changes saved"
    And I should see "Google custom"
    And "Configured" "icon" should exist in the "Google custom" "table_row"
    And "Do not allow login" "icon" should exist in the "Google custom" "table_row"
    And "Service discovery successful" "icon" should exist in the "Google custom" "table_row"
    And the "src" attribute of "table.admintable th img" "css_element" should contain "favicon.ico"
    And I click on "Configure endpoints" "link" in the "Google custom" "table_row"
    And I should see "https://accounts.google.com/.well-known/openid-configuration" in the "discovery_endpoint" "table_row"
    And I should see "authorization_endpoint"
    And I follow "OAuth 2 services"
    And I click on "Configure user field mappings" "link" in the "Google custom" "table_row"
    And I should see "firstname" in the "given_name" "table_row"
    And I should see "middlename" in the "middle_name" "table_row"
    And I follow "OAuth 2 services"
    And I click on "Edit" "link" in the "Google custom" "table_row"
    And I set the following fields to these values:
      | Name                       | Google custom modified                     |
    And I press "Save changes"
    And I should see "Changes saved"
    And I should see "Google custom modified"
    And I click on "Delete" "link" in the "Google custom modified" "table_row"
    And I should see "Are you sure you want to delete the identity issuer \"Google custom modified\"?"
    And I press "Continue"
    And I should see "Identity issuer deleted"
    And I should not see "Google custom modified"

  Scenario: Create, edit and delete invalid custom OIDC service
    Given I press "Custom"
    And I should see "Create new service: Custom"
    And I set the following fields to these values:
      | Name                       | Invalid custom service                    |
      | Client ID                  | thisistheclientid                         |
      | Client secret              | supersecret                               |
      | Service base URL           | https://dc.imsglobal.org/                 |
    When I press "Save changes"
    Then I should see "Could not discover end points for identity issuer: Invalid custom service"
    And I should see "URL: https://dc.imsglobal.org/.well-known/openid-configuration"
    And "Configured" "icon" should exist in the "Invalid custom service" "table_row"
    And "Do not allow login" "icon" should exist in the "Invalid custom service" "table_row"
    And I should see "-" in the "Invalid custom service" "table_row"
    And I click on "Configure endpoints" "link" in the "Invalid custom service" "table_row"
    And I should not see "discovery_endpoint"
    And I follow "OAuth 2 services"
    And I click on "Configure user field mappings" "link" in the "Invalid custom service" "table_row"
    And I should not see "given_name"
    And I should not see "middle_name"
    And I follow "OAuth 2 services"
    And I click on "Edit" "link" in the "Invalid custom service" "table_row"
    And I set the following fields to these values:
      | Name                       | Valid custom service                        |
      | Service base URL           | https://accounts.google.com/                |
    And I press "Save changes"
    And "Configured" "icon" should exist in the "Valid custom" "table_row"
    And "Do not allow login" "icon" should exist in the "Valid custom" "table_row"
    And "Service discovery successful" "icon" should exist in the "Valid custom" "table_row"
    And I click on "Edit" "link" in the "Valid custom service" "table_row"
    And I set the following fields to these values:
      | Name                       | Invalid custom service                    |
      | Service base URL           | https://dc.imsglobal.org/                 |
    And I press "Save changes"
    Then I should see "Could not discover end points for identity issuer: Invalid custom service"
    And I should see "-" in the "Invalid custom service" "table_row"
    And I click on "Delete" "link" in the "Invalid custom service" "table_row"
    And I should see "Are you sure you want to delete the identity issuer \"Invalid custom service\"?"
    And I press "Continue"
    And I should see "Identity issuer deleted"
    And I should not see "Invalid custom service"

  Scenario: Create, edit and delete empty custom OIDC service
    Given I press "Custom"
    And I should see "Create new service: Custom"
    And I set the following fields to these values:
      | Name                       | Empty custom service                      |
      | Client ID                  | thisistheclientid                         |
      | Client secret              | supersecret                               |
    When I press "Save changes"
    And I should see "Changes saved"
    And I should see "Empty custom service"
    And "Configured" "icon" should exist in the "Empty custom service" "table_row"
    And "Do not allow login" "icon" should exist in the "Empty custom service" "table_row"
    And I should see "-" in the "Empty custom service" "table_row"
    And I click on "Configure endpoints" "link" in the "Empty custom service" "table_row"
    And I should not see "discovery_endpoint"
    And I follow "OAuth 2 services"
    And I click on "Configure user field mappings" "link" in the "Empty custom service" "table_row"
    And I should not see "given_name"
    And I should not see "middle_name"
    And I follow "OAuth 2 services"
    And I click on "Edit" "link" in the "Empty custom service" "table_row"
    # Check it works as expected too without slash at the end of the service base URL.
    And I set the following fields to these values:
      | Name                       | Valid custom service                      |
      | Service base URL           | https://accounts.google.com               |
    And I press "Save changes"
    And "Configured" "icon" should exist in the "Valid custom" "table_row"
    And "Do not allow login" "icon" should exist in the "Valid custom" "table_row"
    And "Service discovery successful" "icon" should exist in the "Valid custom" "table_row"
    And I click on "Edit" "link" in the "Valid custom service" "table_row"
    And I set the following fields to these values:
      | Name                       | Invalid custom service                    |
      | Service base URL           | https://dc.imsglobal.org/                 |
    And I press "Save changes"
    Then I should see "Could not discover end points for identity issuer: Invalid custom service"
    And I should see "-" in the "Invalid custom service" "table_row"
    And I click on "Edit" "link" in the "Invalid custom service" "table_row"
    And I set the following fields to these values:
      | Name                       | Empty custom service                      |
      | Service base URL           |                                           |
    And I press "Save changes"
    And I should see "Changes saved"
    And I should see "Empty custom service"
    And I click on "Delete" "link" in the "Empty custom service" "table_row"
    And I should see "Are you sure you want to delete the identity issuer \"Empty custom service\"?"
    And I press "Continue"
    And I should see "Identity issuer deleted"
    And I should not see "Empty custom service"
