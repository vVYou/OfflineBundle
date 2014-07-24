<?php

namespace Claroline\OfflineBundle\Tests\Integration\features\bootstrap;

use Behat\MinkExtension\Context\MinkContext;

/**
 * Feature context.
 */
class FeatureContext extends MinkContext
{
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
   * After each scenario, we close the browser
   *
   * @AfterScenario
   */
    public function closeBrowser()
    {
        $this->getSession()->stop();
    }

    /**
     * @Given /^that I\'m not log in$/
     */
    public function thatIMNotLogIn()
    {
        return true;
    }

    /**
     * @Then /^I should smile$/
     */
    public function iShouldSmile()
    {
        return true;
    }
