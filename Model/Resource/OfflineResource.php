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

use Claroline\OfflineBundle\Model\SyncConstant;
use Claroline\OfflineBundle\Model\SyncInfo;
use Claroline\CoreBundle\Entity\Resource\ResourceNode;
use Claroline\CoreBundle\Entity\Workspace\Workspace;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Library\Utilities\ClaroUtilities;
use \ZipArchive;
use \DOMDocument;

abstract class OfflineResource extends OfflineElement
{
    protected $ut;
    protected $om;

    /**
     * Add informations required to check and recreated a resource if necessary.
     *
     * @param \Claroline\CoreBundle\Entity\Resource\ResourceNode $resToAdd
     * @param \ZipArchive                                        $archive
     */
    abstract public function addResourceToManifest($domManifest, $domWorkspace, ResourceNode $resToAdd, ZipArchive $archive, $date);

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
    abstract public function createResource($resource, Workspace $workspace, User $user, SyncInfo $wsInfo, $path);

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
    abstract public function updateResource($resource, ResourceNode $node, Workspace $workspace, User $user, SyncInfo $wsInfo, $path);

    /**
     * Create a copy of the resource in case of conflict (e.g. if a ressource has been modified both offline
     * and online)
     *
     * @param \Claroline\CoreBundle\Entity\Workspace\Workspace   $workspace
     * @param \Claroline\CoreBundle\Entity\Resource\ResourceNode $node
     * @param string                                             $path
     */
    abstract public function createDoublon($resource, Workspace $workspace, ResourceNode $node, $path);

    // Return the type of resource supported by the service
    abstract public function getType();
    
    /**
     * Add informations required to check and recreated a node if necessary.
     *
     * @param \Claroline\CoreBundle\Entity\Resource\ResourceNode $resToAdd
     */
    protected function addNodeToManifest($domManifest, $offType, $domWorkspace, ResourceNode $resToAdd)
    {
        $typeNode = $resToAdd->getResourceType()->getName();
        $creationTime = $resToAdd->getCreationDate()->getTimestamp();
        $modificationTime = $resToAdd->getModificationDate()->getTimestamp();

        if (!($resToAdd->getParent() == NULL && $typeNode == 'directory')) {
            $domRes = $domManifest->createElement('resource-'.$offType);
            $domWorkspace->appendChild($domRes);

            $type = $domManifest->createAttribute('type');
            $type->value = $resToAdd->getResourceType()->getName();
            $domRes->appendChild($type);
            $name = $domManifest->createAttribute('name');
            $name->value = $resToAdd->getName();
            $domRes->appendChild($name);
            $mimetype = $domManifest->createAttribute('mimetype');
            $mimetype->value = $resToAdd->getMimeType();
            $domRes->appendChild($mimetype);
            $hashname_node = $domManifest->createAttribute('hashname_node');
            $hashname_node->value = $resToAdd->getNodeHashName();
            $domRes->appendChild($hashname_node);
            $hashname_parent = $domManifest->createAttribute('hashname_parent');
            $hashname_parent->value = $resToAdd->getParent()->getNodeHashName();
            $domRes->appendChild($hashname_parent);
            $creation_date = $domManifest->createAttribute('creation_date');
            $creation_date->value = $creationTime;
            $domRes->appendChild($creation_date);
            $modification_date = $domManifest->createAttribute('modification_date');
            $modification_date->value = $modificationTime;
            $domRes->appendChild($modification_date);
            $domRes = $this->addCreatorInformations($domManifest, $domRes, $resToAdd->getCreator());

            return $domRes;
        }
    }
    
    protected function addResourceAndId($domManifest, ResourceNode $resToAdd, $resourcesSec)
    {
        $domRes = $domManifest->createElement('resource');
        $resourcesSec->appendChild($domRes);
        $hashname_node = $domManifest->createAttribute('hashname_node');
        $hashname_node->value = $resToAdd->getNodeHashName();
        $domRes->appendChild($hashname_node);
        return $domRes;
    }
    
    public function modifyUniqueId($resource)
    {
        // $resource->setNodeHashName($this->ut->generateGuid());
        $resource->setNodeHashName('AAAA-AAAA-AAAAAAA');
        $this->om->flush();
    }
}
