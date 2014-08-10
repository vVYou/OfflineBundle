<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\OfflineBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

class OfflineCompilerPass implements CompilerPassInterface
{
    /*
    *   This method checks the existence of the service 'claroline_offline.creation_manager'
    *   or the service 'claroline.manager.loading_manager' and
    *   then add to this service all the services tagged with 'claroline_offline.offline'
    */
    public function process(ContainerBuilder $container)
    {
        $definition_crea = $container->getDefinition('claroline.manager.creation_manager');

        $taggedServices = $container->findTaggedServiceIds('claroline_offline.offline');

        foreach ($taggedServices as $id => $attributes) {
            $definition_crea->addMethodCall('addOffline', array(new Reference($id)));
        }

        $definition_load = $container->getDefinition('claroline.manager.loading_manager');

        $taggedServicess = $container->findTaggedServiceIds('claroline_offline.offline');

        foreach ($taggedServicess as $id => $attributes) {
            $definition_load->addMethodCall('addOffline', array(new Reference($id)));
        }
		
		$definition_listener = $container->getDefinition('claroline.edit_hashname_handler');
		
		foreach ($taggedServicess as $id => $attributes) {
            $definition_load->addMethodCall('addOffline', array(new Reference($id)));
        }
    }
}
