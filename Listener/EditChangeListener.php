<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\OfflineBundle\Listener;

use Claroline\CoreBundle\Event\StrictDispatcher;
use Claroline\CoreBundle\Entity\Resource\AbstractResource;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\Routing\Router;
use Claroline\CoreBundle\Entity\Resource\ResourceNode;
use Doctrine\ORM\Events as ORM;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Claroline\OfflineBundle\Model\Resource\OfflineElement;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @DI\Service("claroline.edit_hashname_handler")
 * @DI\Tag("doctrine.event_listener", attributes={"event"="onFlush"})
 */
class EditChangeListener
{
    private $eventDispatcher;
    private $router;
    private $offline;
    private $securityContext;

    /**
     * @DI\InjectParams({
     *     "container"      = @DI\Inject("service_container"),
     *     "eventDispatcher" = @DI\Inject("claroline.event.event_dispatcher")
     * })
     *
     */
    public function __construct(
        ContainerInterface   $container,
        StrictDispatcher $eventDispatcher
    )
    {
        $this->container = $container;
        $this->eventDispatcher = $eventDispatcher;
        $this->offline = array();
    }

    public function addOffline(OfflineElement  $offline)
    {
        $this->offline[$offline->getType()] = $offline;
    }

    /**
     * @DI\Observe("onFlush")
     */
    public function onFlush(OnFlushEventArgs $eventArgs = null)
    {
        $em = $eventArgs->getEntityManager();
        $uow = $em->getUnitOfWork();
        $env = $this->container->getParameter("kernel.environment");
        $ut = $this->container->get('claroline.utilities.misc');
        $securityContext = $this->container->get("security.context");
		$disableListener = $this->container->getParameter('claroline.synchronisation.disable_listener');
        $types = array_keys($this->offline);
        $user = null;
        $token = $securityContext->getToken();
        if ($token !== null) {
            $user = $token->getUser();
        }

        if ($user !== null && $user !== 'anon.' && !$disableListener) {
            foreach ($uow->getScheduledEntityUpdates() as $entity) {

                if ($entity instanceof AbstractResource) {
                    $resNode = $entity->getResourceNode();
                    $nodeType = $resNode->getResourceType()->getName();

                    if ($env == 'offline' && $user->getId() !== $resNode->getCreator()->getId()) {
                        if (in_array($nodeType, $types)) {
                            $this->offline[$nodeType]->modifyUniqueId($resNode, $em, $uow, $ut);
                        }
                    }
                }

                if ($entity instanceof ResourceNode) {
                    $nodeType = $entity->getResourceType()->getName();
                    if ($env == 'offline' && $user->getId() !== $entity->getCreator()->getId()) {
                        if (in_array($nodeType, $types)) {
                            $this->offline[$nodeType]->modifyUniqueId($entity, $em, $uow, $ut);
                        }
                    }
                }
            }
        }
    }
}
