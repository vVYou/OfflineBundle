Feature: First Synchronisation
  In order to use the Claroffline tool
  As a website user
  I need to retrieved my account first


  Scenario: Test Claroffline Configuration
    Given the admin account "user_test" is created
    Given I log in with "user_test"/"user_test"
    And I am on "/sync/config"
    Then I should see "Claroffline - Configuration"

  Scenario: Redirection to Claroffline Configuration
    Given I am not logged in
    And I have not retrieved my account
    When I go on the platform
    Then I should see "Claroline" 
 
  Scenario: User not logged in but account retrieved
    Given I am not logged in
    And I have retrieved my account
    When I go on the platform
    Then I should not see "Claroffline - Configuration"
    
  Scenario: User logged in and account retrieved
    Given I log in with "root"/"password"
    And I have retrieved my account
    When I go on the platform
    Then I should not see "Claroffline - Configuration"
  
  Scenario: Non-existing account retrieval
    Given I am not logged in
    And I have not retrieved my account
    And I am on "/sync/config"
    When I fill in "offline_form_name" with "unknown_user"
    And I fill in "offline_form_password" with "wrong_password"
    And I press "Retrieve profil"
    Then I should see "The account you have entered doesn't seem to exist"
    
  Scenario: Try to retrieve an account already retrieved
    Given I am not logged in
    And I have retrieved my account
    # And the user "user_test" is in my db
    And I am on "/sync/config"
    When I fill in "offline_form_name" with "root"
    And I fill in "offline_form_password" with "password"
    And I press "Retrieve profil"
    Then I should see "The account of this user has already been retrieved!"
    
  Scenario: Sucessfull account retrieval
    Given I am not logged in
    And I have not retrieved my account
    # And the user "user_test" is in the other DB
    And I am on "/sync/config"
    When I fill in "offline_form_name" with "root"
    And I fill in "offline_form_password" with "password"
    And I press "Retrieve profil"
    # And I wait for the response
    Then I should see "Account successfully retrieved! Please start your first synchronisation in order to retrieve all your workspaces and content."    