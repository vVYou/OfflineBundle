Feature: First Synchronisation
  In order to use the Claroffline tool
  As a website user
  I need to retrieved my account first

  Scenario:
    Given I am on "http://localhost/Claroline_2/web/app_dev.php/login"
    When I fill in "Username or email" with "root"
    And I fill in "Password" with "password"
    And I press "Login"
    Then I should be on "http://localhost/Claroline_2/web/app_dev.php/desktop/tool/open/home"