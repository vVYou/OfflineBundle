<?php

namespace Claroline\OfflineBundle\Tests\Integration\Context;

use Symfony\Component\HttpKernel\KernelInterface;
use Behat\Symfony2Extension\Context\KernelAwareInterface;
use Behat\MinkExtension\Context\MinkContext;
use Behat\Behat\Context\BehatContext,
    Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Symfony2Extension\Context\KernelDictionary;
use Claroline\CoreBundle\Tests\Integration\Context;
use Behat\Behat\Context\Step;
use Behat\Behat\Event\ScenarioEvent;
use Claroline\CoreBundle\Library\Installation\Settings\SettingChecker;
use Goutte\Client;

//
// Require 3rd-party libraries here:
//
//   require_once 'PHPUnit/Autoload.php';
//   require_once 'PHPUnit/Framework/Assert/Functions.php';
//

/**
 * Feature context.
 */
class FeatureContext extends MinkContext
                  implements KernelAwareInterface
{
    private $kernel;
    private $parameters;

    /**
     * Initializes context with parameters from behat.yml.
     *
     * @param array $parameters
     */
    public function __construct(array $parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * Sets HttpKernel instance.
     * This method will be automatically called by Symfony2Extension ContextInitializer.
     *
     * @param KernelInterface $kernel
     */
    public function setKernel(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }
    
    /**
     * After each scenario, we close the browser
     *
     * @AfterScenario
     */
    public function closeBrowser()
    {
        $this->getSession()->stop();
    }

    /**
     * @Given /^I am not logged in$/
     */
    public function iAmNotLoggedIn()
    {
        $this->getMink()
            ->getSession()
            ->visit($this->locatePath('/logout'))
        ;
    }

    /**
     * @Given /^the admin account "([^"]*)" is created$/
    */
    public function theAdminAccountIsCreated($username)
    {
        $this->loadFixture(
            'Claroline\CoreBundle\DataFixtures\Test\LoadUserData',
            array(array('username' => $username, 'role' => 'ROLE_ADMIN'))
        );
    }

    /**
     * @Given /^I log in with "([^"]*)"\/"([^"]*)"$/
     */
    public function iLogInWith($login, $password)
    {
        return array(
            new Step\When('I am on "/login"'),
            new Step\When('I fill in "Username or email" with "'. $login . '"'),
            new Step\When('I fill in "Password" with "'. $password . '"'),
            new Step\When('I press "Login"'),
            new Step\When('I should be on "/desktop/tool/open/home"')
        );
    }

    /**
     * @Given /^I have not retrieved my account$/
     */

    public function iHaveNotRetrievedMyAccount()
    {
        return true;
    }

    /**
     * @Given /^I have retrieved my account$/
     */

    public function iHaveRetrievedMyAccount()
    {
        if (!(file_exists('./app/config/sync_config.yml'))) {
            file_put_contents('./app/config/sync_config_x.txt', 'test');
        }

        return true;
    }

    /**
     * @When /^I go on the platform$/
     */

    public function iGoOnThePlatform()
    {
        $this->getMink()
            ->getSession()
            ->visit($this->locatePath(''))
        ;
    }

    /**
     * @Then /^I should smile$/
     */
    public function iShouldSmile()
    {
        return true;
    }

    protected function loadFixture($fixtureFqcn, array $args = array())
    {
        $client = new Client();
        $client->request(
            'POST',
            $this->getUrl('test/fixture/load'),
            array('fqcn' => $fixtureFqcn, 'args' => $args)
        );
        $this->checkForResponseError(
            $client->getResponse()->getStatus(),
            $client->getResponse()->getContent(),
            "Unable to load {$fixtureFqcn} fixture"
        );
    }

    private function getUrl($path)
    {
        return $this->getMinkParameter('base_url') . '/' . $path;
    }

    private function checkForResponseError($status, $content, $exceptionMsg)
    {
        if (preg_match('#<title>([^<]+)#', $content, $matches)) {
            $content = $matches[1];
        }

        if ($status !== 200 || preg_match('/Fatal error/i', $content)) {
            throw new \Exception(
                "{$exceptionMsg}.\n"
                . "Response status is: {$status}\n"
                . "Response content is: {$content}"
            );
        }
    }

    public function spin($lambda, $wait = 5)
    {
        for ($i = 0; $i < $wait; $i++) {
            try {
                if ($lambda($this)) {
                    return true;
                }
            } catch (\Exception $e) {
                // do nothing
            }

            sleep(1);
        }

        $backtrace = debug_backtrace();

        throw new \Exception("Timeout thrown by " . $backtrace[1]['class'] . "::" . $backtrace[1]['function']);
    }

}
