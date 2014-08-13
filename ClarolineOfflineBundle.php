<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\OfflineBundle;

//use Symfony\Component\HttpKernel\Bundle\Bundle;
use Claroline\CoreBundle\Library\PluginBundle;
use Claroline\KernelBundle\Bundle\ConfigurationBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Claroline\OfflineBundle\DependencyInjection\Compiler\OfflineCompilerPass;
use Claroline\OfflineBundle\Installation\AdditionalInstaller;

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

    public function getAdditionalInstaller()
    {
        return new AdditionalInstaller();
    }
}
