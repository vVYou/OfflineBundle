<?php

namespace Claroline\OfflineBundle;

//use Symfony\Component\HttpKernel\Bundle\Bundle;
use Claroline\CoreBundle\Library\PluginBundle;
use Claroline\KernelBundle\Bundle\ConfigurationBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Claroline\OfflineBundle\DependencyInjection\Compiler\OfflineCompilerPass;


/**
 * Bundle class.
 */
class ClarolineOfflineBundle extends PluginBundle
{

    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new OfflineCompilerPass());
    }
    
    public function getConfiguration($environment)
    {
        $config = new ConfigurationBuilder();

        return $config->addRoutingResource(__DIR__ . '/Resources/config/routing.yml', null, 'sync');
    }

}
