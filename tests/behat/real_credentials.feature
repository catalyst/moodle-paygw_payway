@paygw @paygw_payway @javascript
Feature: Paying with the PayWay gateway (real sandbox credentials)
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
    And PayWay is configured for payment account "Account1" with real sandbox credentials
    And fee enrolment for "C1" uses "Account1" at "10" "AUD"
    And I log in as "student1"
    And I am on course index
    And I follow "Course 1"

  Scenario: The real payway.js library loads and creates a trusted credit card frame
    When I press "Select payment type"
    And ".payway" "css_element" in the "Select payment type" "dialogue" should be visible
    And I click on "Proceed" "button" in the "Select payment type" "dialogue"
    Then "#payway-credit-card iframe" "css_element" should be visible

  Scenario: Completing a real sandbox payment enrols the student in the course
    When I press "Select payment type"
    And ".payway" "css_element" in the "Select payment type" "dialogue" should be visible
    And I click on "Proceed" "button" in the "Select payment type" "dialogue"
    And I enter PayWay card number "4564710000000004" expiry "02/future9" CVV "847" name "Behat Test"
    And I click on "#payway-cc-submit" "css_element"
    Then I wait for PayWay payment success
    And I click on "Enter course" "button"
    And I should see "Course 1"

  Scenario: A real sandbox expired card is handled without exposing provider details
    When I press "Select payment type"
    And ".payway" "css_element" in the "Select payment type" "dialogue" should be visible
    And I click on "Proceed" "button" in the "Select payment type" "dialogue"
    And I enter PayWay card number "4564710000000004" expiry "01/future9" CVV "847" name "Behat Test"
    And I click on "#payway-cc-submit" "css_element"
    Then I wait until "Payment could not be completed" "text" exists
    And I should not see "Expired card"
    And "#payway-cc-submit" "css_element" should be visible

  Scenario: A real sandbox stolen card is handled without exposing provider details
    When I press "Select payment type"
    And ".payway" "css_element" in the "Select payment type" "dialogue" should be visible
    And I click on "Proceed" "button" in the "Select payment type" "dialogue"
    And I enter PayWay card number "5163200000000016" expiry "12/future9" CVV "728" name "Behat Test"
    And I click on "#payway-cc-submit" "css_element"
    Then I wait until "Payment could not be completed" "text" exists
    And I should not see "Stolen card"
    And "#payway-cc-submit" "css_element" should be visible
