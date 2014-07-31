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
use Claroline\CoreBundle\Entity\Resource\Directory;
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

/**
 * @DI\Service("claroline_offline.offline.directory")
 * @DI\Tag("claroline_offline.offline")
 */
class OfflineDirectory extends OfflineResource
{
    private $om;
    private $resourceManager;
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
        $this->resourceManager = $resourceManager;
    }

   public function getType(){
        return 'directory';
    }
   
   public function addResourceToManifest($domManifest, $domWorkspace, $resToAdd, $archive, $date){
       parent::addResourceToManifest($domManifest, $domWorkspace, $resToAdd, $archive, $date);
       return $domManifest;
   }
   
   public function createResource($resource, $workspace, $user, $wsInfo, $path){
   
        $newResource = new Directory();
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
        
        $newResource->setName($resource->getAttribute('name'));
        $newResource->setMimeType($resource->getAttribute('mimetype'));

        $this->resourceManager->create($newResource, $type, $creator, $workspace, $parent_node, null, array(), $resource->getAttribute('hashname_node'));
        $wsInfo->addToCreate($resource->getAttribute('name'));
        
        $node = $newResource->getResourceNode();
        $this->changeDate($node, $creation_date, $modification_date, $this->om, $this->resourceManager);
        return $wsInfo;
   }
   
   public function updateResource($resource, $node, $workspace, $user, $wsInfo, $path){
   
        $type = $this->resourceManager->getResourceTypeByName($resource->getAttribute('type'));
        $modif_date = $resource->getAttribute('modification_date');
        $creation_date = $resource->getAttribute('creation_date'); //USELESS?
        $node_modif_date = $node->getModificationDate()->getTimestamp();
        $user_sync = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);
                
        if ($node_modif_date < $modif_date) {
            $this->resourceManager->rename($node, $resource->getAttribute('name'));
            $wsInfo->addToUpdate($resource->getAttribute('name'));
        }
   }
   
   public function createDoublon($resource, $workspace, $node, $path){
   
        $newResource = new Directory();
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
            
        $node = $newResource->getResourceNode();
        $this->changeDate($node, $creation_date, $modification_date, $this->om, $this->resourceManager);

   }
}
