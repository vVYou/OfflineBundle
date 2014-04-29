<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\CoreBundle\Controller;

use Claroline\CoreBundle\Entity\Resource\AbstractResource;
use Claroline\CoreBundle\Entity\Resource\Directory;
use Claroline\CoreBundle\Listener\TimestampableListener;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;

class TestController extends Controller
{
    /**
     * @Route("/timestamp", name="claro_test_timestamp")
     */
    public function test()
    {
        $dir = new Directory();
        $dir->setName('TEST');

        $listener = $this->getTimestampListener();
        $listener->forceTime(new \DateTime('@' . (time() - 10e8)));

        $this->createDirectory();

        return new Response('Ok');
    }

    private function getTimestampListener()
    {
        $evm = $this->get('doctrine.orm.entity_manager')->getEventManager();

        foreach ($evm->getListeners() as $listenersByEvent) {
            foreach ($listenersByEvent as $listener) {
                if ($listener instanceof TimestampableListener) {
                    return $listener;
                }
            }
        }

        throw new \Exception('Cannot found timestamp listener');
    }

    private function createDirectory()
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $rm = $this->get('claroline.manager.resource_manager');

        $dir = new Directory();
        $dir->setName('TEST');

        $type = $em->getRepository('Claroline\CoreBundle\Entity\Resource\ResourceType')
            ->findOneByName('directory');
        $creator = $em->getRepository('Claroline\CoreBundle\Entity\User')
            ->findOneByUsername('admin');
        $workspace = $creator->getPersonalWorkspace();
        $parentDir = $em->getRepository('Claroline\CoreBundle\Entity\Resource\ResourceNode')
            ->findWorkspaceRoot($workspace);

        $rm->create($dir, $type, $creator, $workspace, $parentDir, null , array(), new \DateTime());
    }
}
