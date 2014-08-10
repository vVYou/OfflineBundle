Feature: First Synchronisation
  In order to use the Claroffline tool
  As a website user
  I need to retrieved my account first

  @clarof
  Scenario: Test Claroffline Configuration
    Given the admin account "user_test" is created
    Given I log in with "user_test"/"user_test"
    #Given I log in with "root"/"password"
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
    
  Scenario: User on the account retrieval page should see the form
    Given I log in with "root"/"password"
    When I am on "/sync/config"
    Then I should see "Username"
    And I should see "Password"
    And I should see "URL of the website"
    And I should see "http://www.example.com"
    And I should see "Retrieve Profil"
    
  Scenario: User who retrieved account should be able to modify the url specified.
    Given I log in with "root"/"password"
    And I have retrieved my account
    When I go on the plugin
    Then I should see 'Claroffline'
    And I should see 'Parameters'
    And I should see 'Start synchronisation'
    
  Scenario: Users should be able to modify the url they specified
    Given I log in with "root"/"password"
    And I have retrieved my account
    When I go on the plugin
    And I press "Parameters"
    Then I should see "URL of the website"
    And I should see "http://localhost:14580/Claroline_2/web/app_dev.php"
    And I should see "Ok"
    And I should see "Cancel"

  Scenario: Correct redirection of the Url edit form
    Given I log in with "root"/"password"
    When I am on "/url/form/edit"
    And I press "Cancel"
    Then I should be on "/desktop/tool/open/claroline_offline_tool"
  
  Scenario: Non-existing account retrieval
    #Given I am not logged in
    #And I have not retrieved my account
    #And I am on "/sync/config"
    #When I fill in "offline_form_name" with "unknown_user"
    #And I fill in "offline_form_password" with "wrong_password"
    #And I press "Retrieve profil"
    #Then I should see "The account you have entered doesn't seem to exist"
    
  Scenario: Try to retrieve an account already retrieved
    #Given I am not logged in
    #And I have retrieved my account
    # And the user "user_test" is in my db
    #And I am on "/sync/config"
    #When I fill in "offline_form_name" with "root"
    #And I fill in "offline_form_password" with "password"
    #And I press "Retrieve profil"
    #Then I should see "The account of this user has already been retrieved!"
    
  Scenario: Sucessfull account retrieval
    #Given I am not logged in
    #And I have not retrieved my account
    # And the user "user_test" is in the other DB
    #And I am on "/sync/config"
    #When I fill in "offline_form_name" with "root"
    #And I fill in "offline_form_password" with "password"
    #And I press "Retrieve profil"
    # And I wait for the response
    #Then I should see "Account successfully retrieved! Please start your first synchronisation in order to retrieve all your workspaces and content."    