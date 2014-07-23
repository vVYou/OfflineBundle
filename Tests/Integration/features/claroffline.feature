Feature: Claroffline Page
  In order to use the Claroffline tool
  As a website user
  I need to be logged in first
  @clarof
  Scenario: Sucessfull acces to Claroffline
    Given the admin account "user_test" is created
    Given I log in with "user_test"/"user_test"
    And I am on "/sync"
    Then I should see "Claroffline"

  Scenario: Unsucessfull acces to Claroffline
    Given I am not logged in
    And I am on "/sync"
    Then I should be on "/login"