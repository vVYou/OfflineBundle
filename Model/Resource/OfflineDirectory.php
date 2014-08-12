<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\OfflineBundle\Model\Resource;

use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Entity\Resource\ResourceNode;
use Claroline\CoreBundle\Entity\Resource\Directory;
use Claroline\CoreBundle\Entity\Workspace\Workspace;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Manager\UserManager;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\OfflineBundle\Model\SyncInfo;
use JMS\DiExtraBundle\Annotation as DI;
use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use \DateTime;
use \ZipArchive;

/**
 * @DI\Service("claroline_offline.offline.directory")
 * @DI\Tag("claroline_offline.offline")
 */
class OfflineDirectory extends OfflineResource
{

    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "container"      = @DI\Inject("service_container")
     * })
     */
    public function __construct(
        ContainerInterface   $container
    )
    {
        $this->container = $container;
    }

    // Return the type of resource supported by this service
    public function getType()
    {
        return 'directory';
    }

    /**
     * Add informations required to check and recreated a resource if necessary.
     *
     * @param \Claroline\CoreBundle\Entity\Resource\ResourceNode $resToAdd
     * @param \ZipArchive                                        $archive
     */
    public function addResourceToManifest($domManifest, $domWorkspace, ResourceNode $resToAdd, ZipArchive $archive, $date)
    {
        parent::addNodeToManifest($domManifest, $this->getType(), $domWorkspace, $resToAdd);

        return $domManifest;
    }

    /**
     * Create a resource of the type supported by the service based on the XML file.
     *
     * @param \Claroline\CoreBundle\Entity\Workspace\Workspace $workspace
     * @param \Claroline\CoreBundle\Entity\User                $user
     * @param \Claroline\OfflineBundle\Model\SyncInfo          $wsInfo
     * @param string                                           $path
     *
     * @return \Claroline\OfflineBundle\Model\SyncInfo
     */
    public function createResource($resource, Workspace $workspace, User $user, SyncInfo $wsInfo, $path)
    {
		$this->om = $this->container->get('claroline.persistence.object_manager');
		$this->resourceManager = $this->container->get('claroline.manager.resource_manager');
		$this->userManager = $this->container->get('claroline.manager.user_manager');
		$this->resourceNodeRepo = $this->om->getRepository('ClarolineCoreBundle:Resource\ResourceNode');
		$this->userRepo = $this->om->getRepository('ClarolineCoreBundle:User');
		 
        $newResource = new Directory();
        $creationDate = new DateTime();
        $modificationDate = new DateTime();
        $creationDate->setTimestamp($resource->getAttribute('creation_date'));
        $modificationDate->setTimestamp($resource->getAttribute('modification_date'));

        $type = $this->resourceManager->getResourceTypeByName($resource->getAttribute('type'));
        // $creator = $this->userRepo->findOneBy(array('username' => $resource->getAttribute('creator')));
        $creator = $this->getCreator($resource);
        $parentNode = $this->resourceNodeRepo->findOneBy(array('hashName' => $resource->getAttribute('hashname_parent')));

        if (count($parentNode) < 1) {
            // If the parent node doesn't exist anymore, workspace will be the parent.
            $parentNode  = $this->resourceNodeRepo->findOneBy(array('workspace' => $workspace));
        }

        $newResource->setName($resource->getAttribute('name'));
        $newResource->setMimeType($resource->getAttribute('mimetype'));

        $this->resourceManager->create($newResource, $type, $creator, $workspace, $parentNode, null, array(), $resource->getAttribute('hashname_node'));
        $wsInfo->addToCreate($resource->getAttribute('name'));

        $node = $newResource->getResourceNode();
        $this->changeDate($node, $creationDate, $modificationDate, $this->resourceManager);

        return $wsInfo;
    }

    /**
     * Update a resource of the type supported by the service based on the XML file.
     *
     * @param \Claroline\CoreBundle\Entity\Resource\ResourceNode $node
     * @param \Claroline\CoreBundle\Entity\Workspace\Workspace   $workspace
     * @param \Claroline\CoreBundle\Entity\User                  $user
     * @param \Claroline\OfflineBundle\Model\SyncInfo            $wsInfo
     * @param string                                             $path
     *
     * @return \Claroline\OfflineBundle\Model\SyncInfo
     *
     */
    public function updateResource($resource, ResourceNode $node, Workspace $workspace, User $user, SyncInfo $wsInfo, $path)
    {
		$this->om = $this->container->get('claroline.persistence.object_manager');
		$this->resourceManager = $this->container->get('claroline.manager.resource_manager');
		
        $type = $this->resourceManager->getResourceTypeByName($resource->getAttribute('type'));
        $modificationDate = $resource->getAttribute('modification_date');
        $creationDate = $resource->getAttribute('creation_date');
        $NodeModifDate = $node->getModificationDate()->getTimestamp();

        if ($NodeModifDate < $modificationDate) {
            $this->resourceManager->rename($node, $resource->getAttribute('name'));
            $wsInfo->addToUpdate($resource->getAttribute('name'));
            $creation = new DateTime();
            $creation->setTimeStamp($creationDate);
            $modif = new DateTime();
            $modif->setTimeStamp($modificationDate);
            $this->changeDate($node, $creation, $modif, $this->resourceManager);
        }

        return $wsInfo;
    }

    /**
     * Create a copy of the resource in case of conflict (e.g. if a ressource has been modified both offline
     * and online)
     *
     * @param \Claroline\CoreBundle\Entity\Workspace\Workspace   $workspace
     * @param \Claroline\CoreBundle\Entity\Resource\ResourceNode $node
     * @param string                                             $path
     */
    public function createDoublon($resource, Workspace $workspace, ResourceNode $node, $path)
    {
        return;
    }
}
