@paygw @paygw_payway @paygw_payway_customfields @javascript
Feature: Send named PayWay custom fields with transactions
  In order to reconcile course payments with user profile data
  As a student
  I need my mapped values sent to the correct current PayWay fields

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                | idnumber | department |
      | student1 | Student   | 1        | student1@example.com | 0        |            |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "core_payment > payment accounts" exist:
      | name     | gateways |
      | Account1 | payway   |
    And PayWay is configured for payment account "Account1"
    And fee enrolment for "C1" uses "Account1" at "10" "AUD"
    And user "student1" has custom profile field "membership" named "Membership number" with value "MEMBER-123"
    And PayWay has the following custom fields:
      | slot | name       |
      | 1    | Email      |
      | 2    | Membership |
      | 3    | Department |
      | 4    | Reference  |
    And PayWay API response sequence is "approved"

  Scenario: Send standard and custom values using current slots after PayWay reorders fields
    Given PayWay for payment account "Account1" has the following mappings:
      | name       | source             |
      | Email      | user:email         |
      | Membership | profile:membership |
    And PayWay has the following custom fields:
      | slot | name       |
      | 1    | Membership |
      | 4    | Email      |
    And I log in as "student1"
    And I am on course index
    And I follow "Course 1"
    And the PayWay JS library is mocked
    When I submit a PayWay payment
    Then I wait for PayWay payment success
    And PayWay should have sent exactly these custom fields:
      | parameter    | value                |
      | customField1 | MEMBER-123           |
      | customField4 | student1@example.com |
    When I click on "Enter course" "button"
    Then I should see "Course 1"

  Scenario: Omit empty and unmapped fields but preserve zero
    Given PayWay for payment account "Account1" has the following mappings:
      | name       | source          |
      | Email      | user:email      |
      | Department | user:department |
      | Reference  | user:idnumber   |
    And I log in as "student1"
    And I am on course index
    And I follow "Course 1"
    And the PayWay JS library is mocked
    When I submit a PayWay payment
    Then I wait for PayWay payment success
    And PayWay should have sent exactly these custom fields:
      | parameter    | value                |
      | customField1 | student1@example.com |
      | customField4 | 0                    |

  Scenario: Long profile values are truncated to sixty characters before sending
    Given user "student1" has custom profile field "longreference" named "Long reference" with value "123456789012345678901234567890123456789012345678901234567890EXCESS"
    And PayWay for payment account "Account1" has the following mappings:
      | name      | source                |
      | Reference | profile:longreference |
    And I log in as "student1"
    And I am on course index
    And I follow "Course 1"
    And the PayWay JS library is mocked
    When I submit a PayWay payment
    Then I wait for PayWay payment success
    And PayWay should have sent exactly these custom fields:
      | parameter    | value                                                        |
      | customField4 | 123456789012345678901234567890123456789012345678901234567890 |

  Scenario: A renamed remote field prevents a charge instead of receiving the old mapping
    Given PayWay for payment account "Account1" has the following mappings:
      | name       | source             |
      | Membership | profile:membership |
    And PayWay has the following custom fields:
      | slot | name           |
      | 2    | New membership |
    And I log in as "student1"
    And I am on course index
    And I follow "Course 1"
    And the PayWay JS library is mocked
    When I submit a PayWay payment
    Then I should see "PayWay custom field Membership no longer exists" in the "#payway-cc-error" "css_element"
    And PayWay should not have attempted a transaction
    And "#payway-cc-submit" "css_element" should be visible

  Scenario: Removed local profile fields prevent a charge
    Given PayWay for payment account "Account1" has the following mappings:
      | name       | source             |
      | Membership | profile:membership |
    And custom profile field "membership" is removed
    And I log in as "student1"
    And I am on course index
    And I follow "Course 1"
    And the PayWay JS library is mocked
    When I submit a PayWay payment
    Then I should see "The source for custom field Membership is no longer available" in the "#payway-cc-error" "css_element"
    And PayWay should not have attempted a transaction

  Scenario: Unsupported profile characters prevent a charge
    Given user "student1" has custom profile field "unicode" named "Unicode reference" with value "Café"
    And PayWay for payment account "Account1" has the following mappings:
      | name      | source          |
      | Reference | profile:unicode |
    And I log in as "student1"
    And I am on course index
    And I follow "Course 1"
    And the PayWay JS library is mocked
    When I submit a PayWay payment
    Then I should see "must contain only printable ASCII characters" in the "#payway-cc-error" "css_element"
    And PayWay should not have attempted a transaction

  Scenario: Unavailable custom field discovery prevents a charge
    Given PayWay for payment account "Account1" has the following mappings:
      | name  | source     |
      | Email | user:email |
    And PayWay custom field discovery is unavailable
    And I log in as "student1"
    And I am on course index
    And I follow "Course 1"
    And the PayWay JS library is mocked
    When I submit a PayWay payment
    Then I should see "The PayWay custom field configuration is unavailable or out of date" in the "#payway-cc-error" "css_element"
    And PayWay should not have attempted a transaction

  Scenario: Ambiguous remote field names prevent a charge
    Given PayWay for payment account "Account1" has the following mappings:
      | name  | source     |
      | Email | user:email |
    And PayWay has the following custom fields:
      | slot | name  |
      | 1    | Email |
      | 4    | Email |
    And I log in as "student1"
    And I am on course index
    And I follow "Course 1"
    And the PayWay JS library is mocked
    When I submit a PayWay payment
    Then I should see "More than one PayWay custom field is named Email" in the "#payway-cc-error" "css_element"
    And PayWay should not have attempted a transaction