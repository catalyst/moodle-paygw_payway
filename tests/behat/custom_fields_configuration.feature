@paygw @paygw_payway @paygw_payway_customfields
Feature: Configure named PayWay custom fields
  In order to send the right profile data to PayWay
  As an administrator
  I need mappings to follow field names rather than numeric slots

  Background:
    Given the following "core_payment > payment accounts" exist:
      | name     | gateways |
      | Account1 | payway   |
    And PayWay is configured for payment account "Account1"
    And PayWay has the following custom fields:
      | slot | name       |
      | 1    | Email      |
      | 4    | Membership |
    And user "admin" has custom profile field "membership" named "Membership number" with value "ADMIN-1"
    And I log in as "admin"

  Scenario: Save standard and custom profile mappings and preserve them after reordering
    Given I am on the PayWay configuration page for payment account "Account1"
    Then I should not see "Custom field 2"
    And I should not see "No field configured in PayWay"
    When I set the following fields to these values:
      | Email      | User profile: email                  |
      | Membership | Custom user field: Membership number |
    And I press "Save changes"
    Then PayWay for payment account "Account1" should have exactly these mappings:
      | name       | source             |
      | Email      | user:email         |
      | Membership | profile:membership |
    Given PayWay has the following custom fields:
      | slot | name       |
      | 1    | Membership |
      | 2    | Email      |
    When I am on the PayWay configuration page for payment account "Account1"
    Then the field "Email" matches value "User profile: email"
    And the field "Membership" matches value "Custom user field: Membership number"
    When I press "Save changes"
    Then PayWay for payment account "Account1" should have exactly these mappings:
      | name       | source             |
      | Email      | user:email         |
      | Membership | profile:membership |

  Scenario: Clear a mapping by choosing Do not send
    Given PayWay for payment account "Account1" has the following mappings:
      | name       | source             |
      | Email      | user:email         |
      | Membership | profile:membership |
    And I am on the PayWay configuration page for payment account "Account1"
    When I set the field "Membership" to "Do not send"
    And I press "Save changes"
    Then PayWay for payment account "Account1" should have exactly these mappings:
      | name  | source     |
      | Email | user:email |

  Scenario: Removed remote fields are not displayed and saving clears their mappings
    Given PayWay for payment account "Account1" has the following mappings:
      | name       | source             |
      | Email      | user:email         |
      | Membership | profile:membership |
    And PayWay has the following custom fields:
      | slot | name  |
      | 3    | Email |
    When I am on the PayWay configuration page for payment account "Account1"
    Then I should see "Their previous mappings will be cleared when you save"
    And "Membership" "select" should not exist
    When I press "Save changes"
    Then PayWay for payment account "Account1" should have exactly these mappings:
      | name  | source     |
      | Email | user:email |

  Scenario: A renamed PayWay field starts unmapped
    Given PayWay for payment account "Account1" has the following mappings:
      | name       | source             |
      | Membership | profile:membership |
    And PayWay has the following custom fields:
      | slot | name           |
      | 4    | New membership |
    When I am on the PayWay configuration page for payment account "Account1"
    Then the field "New membership" matches value "Do not send"
    And "Membership" "select" should not exist

  Scenario: Failed discovery does not erase existing mappings
    Given PayWay for payment account "Account1" has the following mappings:
      | name  | source     |
      | Email | user:email |
    And PayWay custom field discovery is unavailable
    When I am on the PayWay configuration page for payment account "Account1"
    Then I should see "Unable to load custom fields from PayWay"
    And "Email" "select" should not exist
    When I press "Save changes"
    Then I should see "Unable to load custom fields from PayWay"
    And PayWay for payment account "Account1" should have exactly these mappings:
      | name  | source     |
      | Email | user:email |

  Scenario: A deleted custom profile source must be cleared before saving
    Given PayWay for payment account "Account1" has the following mappings:
      | name       | source             |
      | Email      | user:email         |
      | Membership | profile:membership |
    And custom profile field "membership" is removed
    When I am on the PayWay configuration page for payment account "Account1"
    Then the field "Membership" matches value "Previously selected user profile field (removed)"
    When I press "Save changes"
    Then I should see "The source for custom field Membership is no longer available"
    When I set the field "Membership" to "Do not send"
    And I press "Save changes"
    Then PayWay for payment account "Account1" should have exactly these mappings:
      | name  | source     |
      | Email | user:email |