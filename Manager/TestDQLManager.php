<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\OfflineBundle\Manager;

use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Entity\Resource\ResourceType;
use Claroline\CoreBundle\Entity\Resource\ResourceNode;
use Claroline\CoreBundle\Entity\Workspace\Workspace;
use Claroline\CoreBundle\Library\Utilities\ClaroUtilities;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Pager\PagerFactory;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\OfflineBundle\Entity\UserSynchronized;
use Claroline\OfflineBundle\Model\SyncConstant;
use JMS\DiExtraBundle\Annotation as DI;
//use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Translation\TranslatorInterface;
use \ZipArchive;
use \DOMDocument;
use \DateTime;
use Claroline\OfflineBundle\Model\Resource\OfflineElement;

class TestDQLManager
{
    private $om;
    private $pagerFactory;
    private $translator;
    private $userSynchronizedRepo;
    private $resourceNodeRepo;
    private $revisionRepo;
    private $resourceManager;
    private $workspaceRepo;
    private $roleRepo;
    private $ut;
    private $offline;

    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "pagerFactory"   = @DI\Inject("claroline.pager.pager_factory"),
     *     "translator"     = @DI\Inject("translator"),
     *     "resourceManager"= @DI\Inject("claroline.manager.resource_manager"),
     *     "ut"            = @DI\Inject("claroline.utilities.misc")
     * })
     */
    public function __construct(
        ObjectManager $om,
        PagerFactory $pagerFactory,
        TranslatorInterface $translator,
        ResourceManager $resourceManager
    )
    {
        $this->om = $om;
        $this->pagerFactory = $pagerFactory;
        $this->userSynchronizedRepo = $om->getRepository('ClarolineOfflineBundle:UserSynchronized');
        $this->resourceNodeRepo = $om->getRepository('ClarolineCoreBundle:Resource\ResourceNode');
        $this->revisionRepo = $om->getRepository('ClarolineCoreBundle:Resource\Revision');
        $this->workspaceRepo = $om->getRepository('ClarolineCoreBundle:Workspace\Workspace');
        $this->roleRepo = $om->getRepository('ClarolineCoreBundle:Role');
        $this->translator = $translator;
        $this->resourceManager = $resourceManager;
        $this->ut = $ut;
        $this->offline = array('text', 'claroline_forum', 'directory', 'file');
    }
	
    public function firstDQL($user, $date)
    {
        ini_set('max_execution_time', 0);

        $userWS = $this->workspaceRepo->findByUser($user);
		$this->firstMethod($userWS, $this->offline, $user, $date);
		
    }

    public function firstMethod($userWS, $types, $user, $date)
    {
        foreach ($userWS as $element) {
            $dateTimeStamp = new DateTime();
            $dateTimeStamp->setTimeStamp($date);
            $ressourcesToSync = $this->first($element, $types, $dateTimeStamp);

            if (count($ressourcesToSync) >= 1) {

                foreach ($ressourcesToSync as $res) {
					$myRes = $this->resourceManager->getResourceFromNode($res);
					$revision = $this->revisionRepo->findOneBy(array('text' => $myRes));
                }
            }
        }
    }
	
	public function secondDQL($user, $date)
	{
		ini_set('max_execution_time', 0);

        $userWS = $this->workspaceRepo->findByUser($user);
		$this->secondMethod($userWS, $this->offline, $user, $date);
	}
	
	public function secondMethod()
	{
        foreach ($userWS as $element) {
			foreach($offline as $type){
				$result = $this->second($element, $type, $date);
				$resultToSync = $this->XXX;
			}
		}
	}

    private function first($workspace, $types, $date)
    {
        $query = $this->resourceNodeRepo->createQueryBuilder('res')
            ->join('res.resourceType', 'type')
            ->where('res.workspace = :workspace')
            ->andWhere('res.modificationDate > :date')
            ->andWhere('type.name IN (:types)')
            ->setParameter('workspace', $workspace)
            ->setParameter('types', $types)
            ->setParameter('date', $date)
            ->getQuery();

        return $query->getResult();

    }
	
	private function second($workspace, $types, $date)
	{
        $query = $this->resourceNodeRepo->createQueryBuilder('res')
            ->join('res.resourceType', 'type')
            ->where('res.workspace = :workspace')
            ->andWhere('res.modificationDate > :date')
            ->andWhere('type.name = :types)')
            ->setParameter('workspace', $workspace)
            ->setParameter('types', $types)
            ->setParameter('date', $date)
            ->getQuery();

        return $query->getResult();	
	}

}
