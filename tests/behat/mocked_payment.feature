@paygw @paygw_payway @javascript
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
    And fee enrolment for "C1" uses "Account1" at "10" "AUD"
    And I log in as "student1"
    And I am on course index
    And I follow "Course 1"
    And the PayWay JS library is mocked
    And PayWay API response sequence is "approved"

  Scenario: Opening and closing the payment modal repeatedly does not break the flow
    When I press "Select payment type"
    And I click on "Cancel" "button" in the "Select payment type" "dialogue"
    And I wait until ".modal.show" "css_element" does not exist
    And I press "Select payment type"
    And I wait until ".modal.show [data-region='gateways-container'] .payway" "css_element" exists
    And ".modal.show [data-region='gateways-container'] .payway" "css_element" should be visible
    And I click on ".modal.show [data-action='proceed']" "css_element"
    And I click on "Close" "button" in the "Pay using Westpac PayWay" "dialogue"
    And "Pay using Westpac PayWay" "dialogue" should not exist
    And I wait until only the gateway selector modal is open
    And I click on ".modal.show [data-action='cancel']" "css_element"
    And I wait until ".modal.show" "css_element" does not exist
    And I press "Select payment type"
    And I wait until ".modal.show [data-region='gateways-container'] .payway" "css_element" exists
    And ".modal.show [data-region='gateways-container'] .payway" "css_element" should be visible
    And I click on ".modal.show [data-action='proceed']" "css_element"
    Then "#payway-cc-submit" "css_element" should be visible

  Scenario: The loading placeholder is shown while the gateway configuration is being fetched
    Given PayWay config lookup is delayed by "3" seconds
    When I press "Select payment type"
    And I wait until ".modal.show [data-region='gateways-container'] .payway" "css_element" exists
    And ".modal.show [data-region='gateways-container'] .payway" "css_element" should be visible
    And I proceed with PayWay and see its loading placeholder
    Then "#payway-cc-submit" "css_element" should be visible

  Scenario: Closing the payment modal while it is loading cancels the payment flow
    Given PayWay config lookup is delayed by "3" seconds
    When I press "Select payment type"
    And I wait until ".modal.show [data-region='gateways-container'] .payway" "css_element" exists
    And I proceed with PayWay and see its loading placeholder
    And I click on "Close" "button" in the "Pay using Westpac PayWay" "dialogue"
    Then "Pay using Westpac PayWay" "dialogue" should not exist

  Scenario: Successfully submitting a payment enrols the student in the course
    When I press "Select payment type"
    And I wait until ".modal.show [data-region='gateways-container'] .payway" "css_element" exists
    And ".modal.show [data-region='gateways-container'] .payway" "css_element" should be visible
    And I click on ".modal.show [data-action='proceed']" "css_element"
    And I click on "#payway-cc-submit" "css_element"
    Then I wait for PayWay payment success
    And I click on "Enter course" "button"
    And I should see "Course 1"

  Scenario: A zero-cost fee does not offer a PayWay payment
    Given the following config values are set as admin:
      | cost | 0 | enrol_fee |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 2 | C2        | topics |
    And fee enrolment for "C2" uses "Account1" at "0" "AUD"
    When I am on course index
    And I follow "Course 2"
    Then I should see "There is no cost to enrol in this course!"
    And I should not see "Select payment type"
    And "Pay using Westpac PayWay" "dialogue" should not exist

  Scenario: Closing the success modal also enters the course
    When I press "Select payment type"
    And I wait until ".modal.show [data-region='gateways-container'] .payway" "css_element" exists
    And I click on ".modal.show [data-action='proceed']" "css_element"
    And I click on "#payway-cc-submit" "css_element"
    And I wait for PayWay payment success
    And I click on "Close" "button" in the "Pay using Westpac PayWay" "dialogue"
    Then the url should match "/course/view\.php\?id=[0-9]+$"
    And I should see "Course 1"

  Scenario: An error from the payment webservice is shown without losing the credit card form
    Given PayWay payment processing is forced to fail
    When I press "Select payment type"
    And I wait until ".modal.show [data-region='gateways-container'] .payway" "css_element" exists
    And ".modal.show [data-region='gateways-container'] .payway" "css_element" should be visible
    And I click on ".modal.show [data-action='proceed']" "css_element"
    And I click on "#payway-cc-submit" "css_element"
    Then I should see "Unable to load the credit card payment form" in the "#payway-cc-error" "css_element"
    And "#payway-cc-submit" "css_element" should be visible
    And the "disabled" attribute of "#payway-cc-submit" "css_element" should not be set

  Scenario: A retryable PayWay failure is retried once with the same payment attempt
    Given PayWay retry delay is "1" second
    And PayWay API response sequence is "retry,approved"
    When I press "Select payment type"
    And I wait until ".modal.show [data-region='gateways-container'] .payway" "css_element" exists
    And I click on ".modal.show [data-action='proceed']" "css_element"
    And I click on "#payway-cc-submit" "css_element"
    And I wait until "The payment service is temporarily busy. Retrying in 1 seconds." "text" exists
    And the "disabled" attribute of "#payway-cc-submit" "css_element" should be set
    Then I wait for PayWay payment success
    And PayWay should have retried the same payment request

  Scenario: A retryable PayWay failure stops after one retry
    Given PayWay retry delay is "1" second
    And PayWay API response sequence is "retry,retry"
    When I press "Select payment type"
    And I wait until ".modal.show [data-region='gateways-container'] .payway" "css_element" exists
    And I click on ".modal.show [data-action='proceed']" "css_element"
    And I click on "#payway-cc-submit" "css_element"
    Then I wait until "The payment service is still unavailable" "text" exists

  Scenario: A declined PayWay response shows a generic error and keeps the form available
    Given PayWay API response sequence is "declined"
    When I press "Select payment type"
    And I wait until ".modal.show [data-region='gateways-container'] .payway" "css_element" exists
    And I click on ".modal.show [data-action='proceed']" "css_element"
    And I click on "#payway-cc-submit" "css_element"
    Then I wait until "Payment could not be completed" "text" exists
    And "#payway-cc-submit" "css_element" should be visible

  Scenario: A new submission after a declined payment can succeed
    Given PayWay API response sequence is "declined,approved"
    When I press "Select payment type"
    And I wait until ".modal.show [data-region='gateways-container'] .payway" "css_element" exists
    And I click on ".modal.show [data-action='proceed']" "css_element"
    And I click on "#payway-cc-submit" "css_element"
    And I wait until "Payment could not be completed" "text" exists
    And I click on "#payway-cc-submit" "css_element"
    Then I wait for PayWay payment success
    And PayWay should have used distinct payment keys

  Scenario: A pending PayWay response delivers the order and completes the flow
    Given PayWay API response sequence is "pending"
    When I press "Select payment type"
    And I wait until ".modal.show [data-region='gateways-container'] .payway" "css_element" exists
    And I click on ".modal.show [data-action='proceed']" "css_element"
    And I click on "#payway-cc-submit" "css_element"
    Then I wait for PayWay payment success

  Scenario: A successful but unusable PayWay response keeps the payment available for review
    Given PayWay API response sequence is "responseerror"
    When I press "Select payment type"
    And I wait until ".modal.show [data-region='gateways-container'] .payway" "css_element" exists
    And I click on ".modal.show [data-action='proceed']" "css_element"
    And I click on "#payway-cc-submit" "css_element"
    Then I wait until "Unexpected payment response from PayWay API" "text" exists

  Scenario: A PayWay network failure warns that a duplicate payment may have occurred
    Given PayWay retry delay is "1" second
    And PayWay API response sequence is "networkerror,networkerror"
    When I press "Select payment type"
    And I wait until ".modal.show [data-region='gateways-container'] .payway" "css_element" exists
    And I click on ".modal.show [data-action='proceed']" "css_element"
    And I click on "#payway-cc-submit" "css_element"
    Then I wait until "The payment service is still unavailable" "text" exists
