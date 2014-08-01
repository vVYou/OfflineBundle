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

use \DOMDocument;
use Claroline\OfflineBundle\Model\SyncConstant;
use Claroline\OfflineBundle\Model\SyncInfo;
use Claroline\CoreBundle\Entity\Resource\ResourceNode;
use Claroline\CoreBundle\Entity\Workspace\Workspace;
use Claroline\CoreBundle\Entity\User;
use \DateTime;

abstract class OfflineResource
{
    /**
     * Add informations required to check and recreated a resource if necessary.
     *
     * @param \Claroline\CoreBundle\Entity\Resource\ResourceNode $resToAdd
     * @param \ZipArchive $archive
     * @param \DateTime $date
     */
    abstract public function addResourceToManifest($domManifest, $domWorkspace, ResourceNode $resToAdd, ZipArchive $archive, DateTime $date);
    
    /**
     * Create a resource of the type supported by the service based on the XML file.
     *
     * @param \Claroline\CoreBundle\Entity\Workspace\Workspace $workspace
     * @param \Claroline\CoreBundle\Entity\User $user
     * @param \Claroline\OfflineBundle\Model\SyncInfo $wsInfo
     * @param string $path
     *
     * @return \Claroline\OfflineBundle\Model\SyncInfo
     */
    abstract public function createResource($resource, Workspace $workspace, User $user, SyncInfo $wsInfo, $path);
   
    /**
     * Update a resource of the type supported by the service based on the XML file.
     *
     * @param \Claroline\CoreBundle\Entity\Resource\ResourceNode $node
     * @param \Claroline\CoreBundle\Entity\Workspace\Workspace $workspace
     * @param \Claroline\CoreBundle\Entity\User $user
     * @param \Claroline\OfflineBundle\Model\SyncInfo $wsInfo
     * @param string $path
     *
     * @return \Claroline\OfflineBundle\Model\SyncInfo
     *
     */
    abstract public function updateResource($resource, ResourceNode $node, Workspace $workspace, User $user, SyncInfo $wsInfo, $path);
   
    /**
     * Create a copy of the resource in case of conflict (e.g. if a ressource has been modified both offline
     * and online)
     *
     * @param \Claroline\CoreBundle\Entity\Workspace\Workspace $workspace
     * @param \Claroline\CoreBundle\Entity\Resource\ResourceNode $node     
     * @param string $path
     */
    abstract public function createDoublon($resource, Workspace $workspace, ResourceNode $node, $path);   

    // Return the type of resource supported by the service
    abstract public function getType();
    
    /**
     * Add informations required to check and recreated a node if necessary.
     *
     * @param \Claroline\CoreBundle\Entity\Resource\ResourceNode $resToAdd
     */
    public function addNodeToManifest($domManifest, $off_type, $domWorkspace, ResourceNode $resToAdd)
    {  
        $typeNode = $resToAdd->getResourceType()->getId();
        $creation_time = $resToAdd->getCreationDate()->getTimestamp();
        $modification_time = $resToAdd->getModificationDate()->getTimestamp();

        if (!($resToAdd->getParent() == NULL && $typeNode == SyncConstant::DIR)) {
            $domRes = $domManifest->createElement('resource-'.$off_type);
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
            $creator = $domManifest->createAttribute('creator');
            $creator->value = $resToAdd->getCreator()->getExchangeToken();
            $domRes->appendChild($creator);
            $hashname_node = $domManifest->createAttribute('hashname_node');
            $hashname_node->value = $resToAdd->getNodeHashName();
            $domRes->appendChild($hashname_node);
            $hashname_parent = $domManifest->createAttribute('hashname_parent');
            $hashname_parent->value = $resToAdd->getParent()->getNodeHashName();
            $domRes->appendChild($hashname_parent);
            $creation_date = $domManifest->createAttribute('creation_date');
            $creation_date->value = $creation_time;
            $domRes->appendChild($creation_date);
            $modification_date = $domManifest->createAttribute('modification_date');
            $modification_date->value = $modification_time;
            $domRes->appendChild($modification_date);
            
            return $domRes;
            
        }
    
    }
   
    /**
     * Extract the text contains in the CDATA section of the XML file.
     */
    public function extractCData($resource)
    {
        foreach ($resource->childNodes as $child) {
            if ($child->nodeType == XML_CDATA_SECTION_NODE) {
                return $child->textContent;
            }
        }
    }
    
    /**
     * Change the creation and modification dates of a node.
     *
     * @param \Claroline\CoreBundle\Entity\Resource\ResourceNode $node
     *
     * @return \Claroline\CoreBundle\Entity\Resource\ResourceNode
     */
    public function changeDate($node, $creation_date, $modification_date, $om, $resourceManager)
    {
        $node->setCreationDate($creation_date);
        $node->setModificationDate($modification_date);
        $om->persist($node);
        $resourceManager->logChangeSet($node);
        $om->flush();

        return $node;
    }
}
