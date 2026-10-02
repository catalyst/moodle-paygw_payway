@paygw @paygw_payway
Feature: PayWay gateway configuration locking
  In order to avoid conflicting PayWay configuration updates
  As a Moodle administrator
  I need gateway configuration locking to work correctly

  Background:
    Given the following "core_payment > payment accounts" exist:
      | name     | gateways |
      | Account1 | payway   |
    And PayWay is configured for payment account "Account1"
    And I log in as "admin"

  Scenario: Editing settings while renewal holds the lock shows a lock error
    Given I am on the PayWay configuration page for payment account "Account1"
    And the PayWay configuration lock is held for payment account "Account1"
    When I press "Save changes"
    Then I should see "The configuration is currently locked by another task (most likely secret key auto rotation). Please try again shortly."
    And the PayWay configuration lock is released
