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
use Claroline\CoreBundle\Entity\Resource\File;
use Claroline\CoreBundle\Entity\Workspace\Workspace;
use Claroline\CoreBundle\Library\Utilities\ClaroUtilities;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Manager\UserManager;
use Claroline\CoreBundle\Persistence\ObjectManager;
// use Claroline\OfflineBundle\Model\SyncConstant;
use Claroline\OfflineBundle\Model\SyncInfo;
use JMS\DiExtraBundle\Annotation as DI;
use Doctrine\ORM\EntityManager;
use \DateTime;
use \ZipArchive;

/**
 * @DI\Service("claroline_offline.offline.file")
 * @DI\Tag("claroline_offline.offline")
 */
class OfflineFile extends OfflineResource
{
    private $ut;
    private $isUpdate;
    private $fileDir;

    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "om"              = @DI\Inject("claroline.persistence.object_manager"),
     *     "resourceManager" = @DI\Inject("claroline.manager.resource_manager"),
     *     "userManager"     = @DI\Inject("claroline.manager.user_manager"),
     *     "ut"              = @DI\Inject("claroline.utilities.misc"),
     *     "em"              = @DI\Inject("doctrine.orm.entity_manager"),
     *     "fileDir"         = @DI\Inject("%claroline.param.files_directory%")
     * })
     */
    public function __construct(
        ObjectManager $om,
        ResourceManager $resourceManager,
        UserManager $userManager,
        ClaroUtilities $ut,
        EntityManager $em,
        $fileDir
    )
    {
        $this->om = $om;
        $this->resourceNodeRepo = $om->getRepository('ClarolineCoreBundle:Resource\ResourceNode');
        $this->userRepo = $om->getRepository('ClarolineCoreBundle:User');
        $this->resourceManager = $resourceManager;
        $this->userManager = $userManager;
        $this->ut = $ut;
        $this->em = $em;
        $this->isUpdate = false;
        $this->fileDir = $fileDir.'/';
    }

    // Return the type of resource supported by this service
    public function getType()
    {
        return 'file';
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
        $size = $domManifest->createAttribute('size');
        $size->value = $myRes->getSize();
        $domRes->appendChild($size);
        $hashname = $domManifest->createAttribute('hashname');
        $hashname->value = $myRes->getHashName();
        $domRes->appendChild($hashname);

        // Add the file corresponding to the resource inside de 'data' folder of the archive.
        $archive->addFile($this->fileDir.$myRes->getHashName(), 'data/files/'.$myRes->getHashName());

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
        $newResource = new File();
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

        $fileHashname = $resource->getAttribute('hashname');
        $newResource->setSize($resource->getAttribute('size'));
        $newResource->setHashName($fileHashname);
        rename($path.'data/files/'.$fileHashname, $this->fileDir.$fileHashname);

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
        $modif_date = $resource->getAttribute('modification_date');
        $nodeModifDate = $node->getModificationDate()->getTimestamp();

        if ($nodeModifDate <= $resource->getAttribute('synchronization_date')) {
            $this->resourceManager->delete($node);
            $this->isUpdate = true;
            $this->createResource($resource, $workspace);
            $wsInfo->addToUpdate($resource->getAttribute('name'));
            $this->isUpdate = false;
        } else {
            if ($nodeModifDate != $modif_date) {
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
        $newResource = new File();
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

        $fileHashname = $resource->getAttribute('hashname');
        $newResource->setSize($resource->getAttribute('size'));
        $newResource->setHashName($fileHashname);

        // The file already exist inside the database. We have to modify the Hashname of the file already present.
        $oldFile = $this->resourceManager->getResourceFromNode($node);
        $oldHashname = $oldFile->getHashName();
        $extensionName = substr($oldHashname, strlen($oldHashname)-4, 4);
        $newHashname = $this->ut->generateGuid().$extensionName;

        $this->om->startFlushSuite();
        $oldFile->setHashName($newHashname);
        $this->om->endFlushSuite();

        rename($this->fileDir.$oldHashname, $this->fileDir.$newHashname);
        rename($path.'data/files/'.$fileHashname, $this->fileDir.$fileHashname);

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
