<?php

namespace Claroline\OfflineBundle\Tests\Integration\features\bootstrap;

use Behat\Behat\Context\Step;
use Behat\Behat\Event\ScenarioEvent;
use Behat\Gherkin\Node\TableNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Symfony2Extension\Context\KernelDictionary;
use Behat\MinkExtension\Context\MinkContext;
use Goutte\Client;

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
     * @Then /^I should smile$/
     */
    public function iShouldSmile()
    {
        return true;
    }
    
 
