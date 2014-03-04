<?php

namespace Claroline\OfflineBundle;

//use Symfony\Component\HttpKernel\Bundle\Bundle;
use Claroline\CoreBundle\Library\PluginBundle;
use Claroline\KernelBundle\Bundle\ConfigurationBuilder;

/**
 * Bundle class.
 */
class ClarolineOfflineBundle extends PluginBundle
{
    public function getConfiguration($environment)
    {
        $config = new ConfigurationBuilder();

        return $config->addRoutingResource(__DIR__ . '/Resources/config/routing.yml', null, 'sync');
    }
    
}
