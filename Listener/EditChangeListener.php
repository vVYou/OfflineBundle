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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Routing\Router;
use Claroline\CoreBunde\Entity\Text;
use Claroline\CoreBundle\Entity\Revision;
use Claroline\CoreBundle\Entity\Resource\ResourceNode;
use Claroline\OfflineBundle\Model\SyncConstant;
use Doctrine\Common\EventSubscriber;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as EXT;
use Doctrine\ORM\Events as ORM;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
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
        if (!$eventArgs) return;
		$em = $eventArgs->getEntityManager();
        $uow = $em->getUnitOfWork();
		$env = $this->container->getParameter("kernel.environment");
		$securityContext = $this->container->get("security.context");
		$types = array_keys($this->offline);
        $user = $securityContext->getToken()->getUser();
        
        if ($user !== null && $user !== 'anon.') {
            foreach ($uow->getScheduledEntityUpdates() AS $entity) {
                
                if ($entity instanceof AbstractResource){	
                    $resNode = $entity->getResourceNode();
                    $nodeType = $resNode->getResourceType()->getName();	
                    
                    if ($env == 'offline' && $user->getId() !== $resNode->getCreator()->getId()) {
                        if (in_array($nodeType, $types)){
                            $this->offline[$nodeType]->modifyUniqueId($resNode, $em, $uow);	
                        }
                    }
                }
                
                if ($entity instanceof ResourceNode){
                    $nodeType = $entity->getResourceType()->getName();	
                    if ($env == 'offline' && $user->getId() !== $entity->getCreator()->getId()) {
                        if(in_array($nodeType, $types)){
                            $this->offline[$nodeType]->modifyUniqueId($entity, $em, $uow);	
                        }
                    }
                }
            }
        }
	}
}
