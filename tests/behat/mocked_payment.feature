@paygw_payway @javascript
Feature: Paying with the PayWay gateway (mocked)
  In order to complete a purchase using PayWay
  As a student
  I need to be able to open the payment modal and submit a payment

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "core_payment > payment accounts" exist:
      | name     | gateways |
      | Account1 | payway   |
    And PayWay is configured for payment account "Account1"
    And enrolment on payment is set up for course "C1" using payment account "Account1" costing "10" "AUD"
    And I log in as "student1"
    And I am on course index
    And I follow "Course 1"
    And the PayWay JS library is mocked

  Scenario: Opening and closing the payment modal repeatedly does not break the flow
    When I press "Select payment type"
    And I click on "Cancel" "button" in the "Select payment type" "dialogue"
    And I press "Select payment type"
    And ".payway" "css_element" in the "Select payment type" "dialogue" should be visible
    And I click on "Proceed" "button" in the "Select payment type" "dialogue"
    And I click on "Close" "button" in the "Pay using Westpac PayWay" "dialogue"
    And "Pay using Westpac PayWay" "dialogue" should not exist
    And I press "Select payment type"
    And ".payway" "css_element" in the "Select payment type" "dialogue" should be visible
    And I click on "Proceed" "button" in the "Select payment type" "dialogue"
    Then "#payway-cc-submit" "css_element" should be visible

  Scenario: The loading placeholder is shown while the gateway configuration is being fetched
    Given PayWay config lookup is delayed by "3" seconds
    When I press "Select payment type"
    And ".payway" "css_element" in the "Select payment type" "dialogue" should be visible
    And I click on "Proceed" "button" in the "Select payment type" "dialogue"
    Then ".bg-pulse-grey" "css_element" in the "Pay using Westpac PayWay" "dialogue" should be visible
    And "#payway-cc-submit" "css_element" should be visible

  Scenario: Successfully submitting a payment enrols the student in the course
    Given PayWay payment processing is forced to succeed
    When I press "Select payment type"
    And ".payway" "css_element" in the "Select payment type" "dialogue" should be visible
    And I click on "Proceed" "button" in the "Select payment type" "dialogue"
    And I click on "#payway-cc-submit" "css_element"
    Then I should see "Payment successful"
    And I click on "Enter course" "button"
    And I should see "Course 1"

  Scenario: An error from the payment webservice is shown without losing the credit card form
    Given PayWay payment processing is forced to fail
    When I press "Select payment type"
    And ".payway" "css_element" in the "Select payment type" "dialogue" should be visible
    And I click on "Proceed" "button" in the "Select payment type" "dialogue"
    And I click on "#payway-cc-submit" "css_element"
    Then I should see "Unable to load the credit card payment form" in the "#payway-cc-error" "css_element"
    And "#payway-cc-submit" "css_element" should be visible
    And the "disabled" attribute of "#payway-cc-submit" "css_element" should not be set
