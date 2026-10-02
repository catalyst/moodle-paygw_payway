@paygw @paygw_payway
Feature: Automated PayWay secret token renewal
  In order to keep PayWay payments working when a secret token rotates
  As a Moodle administrator
  I need the automated renewal task to safely validate and save replacement tokens

  Background:
    Given the following "core_payment > payment accounts" exist:
      | name     | gateways |
      | Account1 | payway   |
    And PayWay is configured for payment account "Account1"

  Scenario: A valid replacement token is saved
    Given PayWay renewal API returns key "APPLICATION_SEC_replacement", HTTP "200", cURL "0" and verification "200"
    When I run PayWay token renewal for payment account "Account1"
    Then PayWay token renewal should leave the secret key as "APPLICATION_SEC_replacement"

  Scenario: The current token is a no-op
    Given PayWay renewal API returns key "APPLICATION_SEC_placeholder", HTTP "200", cURL "0" and verification "200"
    When I run PayWay token renewal for payment account "Account1"
    Then PayWay token renewal should leave the secret key as "APPLICATION_SEC_placeholder"

  Scenario: A successful response without a token does not change configuration
    Given PayWay renewal API returns key "", HTTP "200", cURL "0" and verification "200"
    When I run PayWay token renewal for payment account "Account1"
    Then PayWay token renewal should report an error containing "PayWay API key response is missing the required key field."
    And PayWay token renewal should leave the secret key as "APPLICATION_SEC_placeholder"

  Scenario: A network error does not change configuration
    Given PayWay renewal API returns key "unused", HTTP "0", cURL "7" and verification "200"
    When I run PayWay token renewal for payment account "Account1"
    Then PayWay token renewal should report an error containing "cURL error code: 7"
    And PayWay token renewal should leave the secret key as "APPLICATION_SEC_placeholder"

  Scenario: An unsuccessful HTTP response does not change configuration
    Given PayWay renewal API returns key "unused", HTTP "503", cURL "0" and verification "200"
    When I run PayWay token renewal for payment account "Account1"
    Then PayWay token renewal should report an error containing "HTTP status: 503"
    And PayWay token renewal should leave the secret key as "APPLICATION_SEC_placeholder"

  Scenario: An invalid replacement token is rejected
    Given PayWay renewal API returns key "APPLICATION_SEC_replacement", HTTP "200", cURL "0" and verification "401"
    When I run PayWay token renewal for payment account "Account1"
    Then PayWay token renewal should report an error containing "Secret API key validation failed. HTTP status: 401"
    And PayWay token renewal should leave the secret key as "APPLICATION_SEC_placeholder"

  Scenario: A configuration lock conflict does not overwrite the token
    Given PayWay renewal API returns key "APPLICATION_SEC_replacement", HTTP "200", cURL "0" and verification "200"
    And PayWay renewal lock acquisition is forced to fail
    When I run PayWay token renewal for payment account "Account1"
    Then PayWay token renewal should leave the secret key as "APPLICATION_SEC_placeholder"
