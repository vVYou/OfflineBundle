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
use Claroline\CoreBundle\Entity\Resource\Text;
use Claroline\CoreBundle\Entity\Resource\Revision;
use Claroline\CoreBundle\Entity\Workspace\Workspace;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Manager\UserManager;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\OfflineBundle\Model\SyncInfo;
use JMS\DiExtraBundle\Annotation as DI;
use Doctrine\ORM\EntityManager;
use Claroline\CoreBundle\Library\Utilities\ClaroUtilities;
use \DateTime;
use \ZipArchive;

/**
 * @DI\Service("claroline_offline.offline.text")
 * @DI\Tag("claroline_offline.offline")
 */
class OfflineText extends OfflineResource
{
    private $revisionRepo;
    private $isUpdate;

    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "resourceManager"= @DI\Inject("claroline.manager.resource_manager"),
     *     "userManager"    = @DI\Inject("claroline.manager.user_manager"),
     *     "ut"             = @DI\Inject("claroline.utilities.misc"),
     *     "em"             = @DI\Inject("doctrine.orm.entity_manager")
     * })
     */
    public function __construct(
        ObjectManager        $om,
        ResourceManager      $resourceManager,
        UserManager          $userManager,
        ClaroUtilities       $ut,
        EntityManager        $em
    )
    {
        $this->om = $om;
        $this->resourceNodeRepo = $om->getRepository('ClarolineCoreBundle:Resource\ResourceNode');
        $this->userRepo = $om->getRepository('ClarolineCoreBundle:User');
        $this->revisionRepo = $om->getRepository('ClarolineCoreBundle:Resource\Revision');
        $this->resourceManager = $resourceManager;
        $this->userManager = $userManager;
        $this->ut = $ut;
        $this->em = $em;
        $this->isUpdate = false;
    }

    // Return the type of resource supported by this service
    public function getType()
    {
        return 'text';
    }

    /**
     * Add informations required to check and recreated a resource if necessary.
     *
     * @param \Claroline\CoreBundle\Entity\Resource\ResourceNode $resToAdd
     * @param \ZipArchive                                        $archive
     */
    public function addResourceToManifest($domManifest, $domWorkspace, ResourceNode $resToAdd, ZipArchive $archive, $date)
    {
        $domRes = parent::addNodeToManifest($domManifest, $this->getType(), $domWorkspace, $resToAdd);
        $myRes = $this->resourceManager->getResourceFromNode($resToAdd);
        $revision = $this->revisionRepo->findOneBy(array('text' => $myRes));

        $version = $domManifest->createAttribute('version');
        $version->value = $myRes->getVersion();
        $domRes->appendChild($version);

        $cdata = $domManifest->createCDATASection($revision->getContent());
        $domRes->appendChild($cdata);

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
        $newResource = new Text();
        $creationDate = new DateTime();
        $modificationDate = new DateTime();
        $creationDate->setTimestamp($resource->getAttribute('creation_date'));
        $modificationDate->setTimestamp($resource->getAttribute('modification_date'));

        $type = $this->resourceManager->getResourceTypeByName($resource->getAttribute('type'));
        // $creator = $this->userRepo->findOneBy(array('exchangeToken' => $resource->getAttribute('creator')));
        $creator = $this->getCreator($resource);
        $parentNode = $this->resourceNodeRepo->findOneBy(array('hashName' => $resource->getAttribute('hashname_parent')));

        if (count($parentNode) < 1) {
            // If the parent node doesn't exist anymore, workspace will be the parent.
            $parentNode  = $this->resourceNodeRepo->findOneBy(array('workspace' => $workspace));
        }

        $revision = new Revision();
        $revision->setContent($this->extractCData($resource));
        $revision->setUser($user);
        $revision->setText($newResource);
        $this->om->persist($revision);

        $newResource->setName($resource->getAttribute('name'));
        $newResource->setMimeType($resource->getAttribute('mimetype'));

        $this->resourceManager->create($newResource, $type, $creator, $workspace, $parentNode, null, array(), $resource->getAttribute('hashname_node'));
        if (!$this->isUpdate) {
            $wsInfo->addToCreate($resource->getAttribute('name'));
        }
        $node = $newResource->getResourceNode();
        $this->changeDate($node, $creationDate, $modificationDate);

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
        $type = $this->resourceManager->getResourceTypeByName($resource->getAttribute('type'));
        $modifDate = $resource->getAttribute('modification_date');
        $nodeModifDate = $node->getModificationDate()->getTimestamp();

        if ($nodeModifDate <= $resource->getAttribute('synchronization_date')) {
            $this->resourceManager->delete($node);
            $this->isUpdate = true;
            $this->createResource($resource, $workspace, $user, $wsInfo);
            $wsInfo->addToUpdate($resource->getAttribute('name'));
            $this->isUpdate = false;
        } else {
            if ($nodeModifDate != $modifDate) {
                // Doublon generation
                $this->createDoublon($resource, $workspace, $node, $path);
                $wsInfo->addToDoublon($resource->getAttribute('name'));
            }
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
        $newResource = new Text();
        $creationDate = new DateTime();
        $modificationDate = new DateTime();
        $creationDate->setTimestamp($resource->getAttribute('creation_date'));
        $modificationDate->setTimestamp($resource->getAttribute('modification_date'));

        $type = $this->resourceManager->getResourceTypeByName($resource->getAttribute('type'));
        $creator = $this->userRepo->findOneBy(array('exchangeToken' => $resource->getAttribute('creator')));
        $parentNode = $this->resourceNodeRepo->findOneBy(array('hashName' => $resource->getAttribute('hashname_parent')));

        if (count($parentNode) < 1) {
            // If the parent node doesn't exist anymore, workspace will be the parent.
            $parentNode  = $this->resourceNodeRepo->findOneBy(array('workspace' => $workspace));
        }

        $revision = new Revision();
        $revision->setContent($this->extractCData($resource));
        $revision->setUser($user);
        $revision->setText($newResource);
        $this->om->persist($revision);

        /*
        *   We add the tag '@offline' to the name of the resource
        *   and modify the Hashname of the resource already present in the Database.
        */

        $newResource->setName($resource->getAttribute('name').'@offline');
        $newResource->setMimeType($resource->getAttribute('mimetype'));
        $oldModificationDate = $node->getModificationDate();

        $this->om->startFlushSuite();
        $node->setNodeHashName($this->ut->generateGuid());
        $this->resourceManager->logChangeSet($node);
        $node->setModificationDate($oldModificationDate);
        $this->om->endFlushSuite();

        $this->resourceManager->create($newResource, $type, $creator, $workspace, $parentNode, null, array(), $resource->getAttribute('hashname_node'));

        $node = $newResource->getResourceNode();
        $this->changeDate($node, $creationDate, $modificationDate);

    }
}
