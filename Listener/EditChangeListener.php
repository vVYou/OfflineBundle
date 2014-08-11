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
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\SecurityContextInterface;
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

/**
 * @DI\Service("claroline.edit_hashname_handler")
 * @DI\Tag("doctrine.event_listener", attributes={"event"="preUpdate"})
 */
class EditChangeListener
{
    private $securityContext;
    private $eventDispatcher;
    private $router;
	private $offline;

    /**
     * @DI\InjectParams({
     *     "eventDispatcher" = @DI\Inject("claroline.event.event_dispatcher")
     * })
     *
     */
    public function __construct(
        StrictDispatcher $eventDispatcher
    )
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->offline = array();
    }

    public function addOffline(OfflineElement  $offline)
    {
        $this->offline[$offline->getType()] = $offline;
    }
	
    /*
    *   @DI\Observe("preUpdate")
    */
	public function preUpdate(LifecycleEventArgs $eventArgs)
    {
		// $args = $eventArgs->getEntity();
        // var_dump($this->offline);
		// if($args instanceof ResourceNode && $this->offline[$args->getResourceType()->getName()]){
			// $this->offline[$args->getResourceType()->getName()]->modifyUniqueId($args);	
			// $args->setText('AZED23DGLLEL');
			// file_put_contents('listeneres.txt', 'ARGSEUH');
			// foreach($args->getRevision() as $elem){
				// $eventArgs->setText('Bob');
			// }
		// }
    }
}
