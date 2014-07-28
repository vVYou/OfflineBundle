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
use Claroline\CoreBundle\Entity\Resource\ResourceType;
use Claroline\CoreBundle\Entity\Resource\ResourceNode;
use Claroline\CoreBundle\Entity\Resource\Text;
use Claroline\CoreBundle\Entity\Resource\Revision;
use Claroline\CoreBundle\Entity\Workspace\Workspace;
use Claroline\CoreBundle\Library\Utilities\ClaroUtilities;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\OfflineBundle\Entity\UserSynchronized;
use Claroline\OfflineBundle\SyncConstant;
use Claroline\OfflineBundle\SyncInfo;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\Translation\TranslatorInterface;
use \DOMDocument;
use \DateTime;

class OfflineText extends OfflineResource
{
    private $om;
    private $resourceManager;
    private $revisionRepo;
    private $userRepo;
    private $resourceNodeRepo;

    
    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "resourceManager"= @DI\Inject("claroline.manager.resource_manager"),
     * })
     */
    public function __construct(
        ObjectManager $om,
        ResourceManager $resourceManager
    )
    {
        $this->om = $om;
        $this->resourceNodeRepo = $om->getRepository('ClarolineCoreBundle:Resource\ResourceNode');
        $this->userRepo = $om->getRepository('ClarolineCoreBundle:User');
        $this->revisionRepo = $om->getRepository('ClarolineCoreBundle:Resource\Revision');
        $this->resourceManager = $resourceManager;
    }
    
    public function addResourceToManifest($domManifest, $domWorkspace, $resToAdd){
   
        $domRes = parent::addResourceToManifest($domManifest, $domWorkspace, $resToAdd);
        $my_res = $this->resourceManager->getResourceFromNode($resToAdd);
        $revision = $this->revisionRepo->findOneBy(array('text' => $my_res));

        $version = $domManifest->createAttribute('version');
        $version->value = $my_res->getVersion();
        $domRes->appendChild($version);

        $cdata = $domManifest->createCDATASection($revision->getContent());
        $domRes->appendChild($cdata);
        
        return $domManifest;
    }
   
    public function createResource($resource, $workspace, $user, $wsInfo){
   
        $newResource = new Text();
        $creation_date = new DateTime();
        $modification_date = new DateTime();
        $creation_date->setTimestamp($resource->getAttribute('creation_date'));
        $modification_date->setTimestamp($resource->getAttribute('modification_date'));

        $type = $this->resourceManager->getResourceTypeByName($resource->getAttribute('type'));
        $creator = $this->userRepo->findOneBy(array('exchangeToken' => $resource->getAttribute('creator')));
        $parent_node = $this->resourceNodeRepo->findOneBy(array('hashName' => $resource->getAttribute('hashname_parent')));

        if (count($parent_node) < 1) {
            // If the parent node doesn't exist anymore, workspace will be the parent.
            // echo 'Mon parent est mort ! '.'<br/>';
            $parent_node  = $this->resourceNodeRepo->findOneBy(array('workspace' => $workspace));
        }
               
        $revision = new Revision();
        $revision->setContent($this->extractCData($resource));
        $revision->setUser($user);
        $revision->setText($newResource);
        $this->om->persist($revision);
        
        $newResource->setName($resource->getAttribute('name'));
        $newResource->setMimeType($resource->getAttribute('mimetype'));

        $this->resourceManager->create($newResource, $type, $creator, $workspace, $parent_node, null, array(), $resource->getAttribute('hashname_node'));
        $wsInfo->addToCreate($resource->getAttribute('name'));
        
        $node = $this->resourceNodeRepo->findOneBy(array('hashName' => $resource->getAttribute('hashname_node')));
        $this->changeDate($node, $creation_date, $modification_date, $this->om, $this->resourceManager);
        return $wsInfo;
    }
   
    public function updateResource($resource, $node, $workspace, $user, $wsInfo){
   
        $type = $this->resourceManager->getResourceTypeByName($resource->getAttribute('type'));
        $modif_date = $resource->getAttribute('modification_date');
        $creation_date = $resource->getAttribute('creation_date'); //USELESS?
        $node_modif_date = $node->getModificationDate()->getTimestamp();
        $user_sync = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($this->user);

        if ($node_modif_date <= $user_sync[0]->getLastSynchronization()->getTimestamp()) {
            $this->resourceManager->delete($node);
            $this->createResource($resource, $workspace, $user, $wsInfo);
            $wsInfo->addToUpdate($resource->getAttribute('name'));
        } else {
            if ($node_modif_date != $modif_date) {
                // Doublon generation
                $this->createDoublon($resource, $workspace, $node, true);
                $wsInfo->addToDoublon($resource->getAttribute('name'));
            } else {
                // echo 'Already in Database'.'<br/>';
            }
        }
    }
   
    public function createDoublon($resource, $workspace, $node){
        
        $newResource = new Text();
        $creation_date = new DateTime();
        $modification_date = new DateTime();
        $creation_date->setTimestamp($resource->getAttribute('creation_date'));
        $modification_date->setTimestamp($resource->getAttribute('modification_date'));

        $type = $this->resourceManager->getResourceTypeByName($resource->getAttribute('type'));
        $creator = $this->userRepo->findOneBy(array('exchangeToken' => $resource->getAttribute('creator')));
        $parent_node = $this->resourceNodeRepo->findOneBy(array('hashName' => $resource->getAttribute('hashname_parent')));

        if (count($parent_node) < 1) {
            // If the parent node doesn't exist anymore, workspace will be the parent.
            // echo 'Mon parent est mort ! '.'<br/>';
            $parent_node  = $this->resourceNodeRepo->findOneBy(array('workspace' => $workspace));
        }
      
        $revision = new Revision();
        $revision->setContent($this->extractCData($resource));
        $revision->setUser($this->user);
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

        $this->resourceManager->create($newResource, $type, $creator, $workspace, $parent_node, null, array(), $resource->getAttribute('hashname_node'));  
    }
}
