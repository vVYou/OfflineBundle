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
use Claroline\CoreBundle\Entity\Resource\File;
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
 * @DI\Service("claroline_offline.offline.file")
 * @DI\Tag("claroline_offline.offline")
 */
class OfflineFile extends OfflineResource
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
        return 'file';
    }
    
   public function addResourceToManifest($domManifest, $domWorkspace, $resToAdd, $archive, $date){
   
        $domRes = parent::addResourceToManifest($domManifest, $domWorkspace, $resToAdd, $archive, $date);
        $my_res = $this->resourceManager->getResourceFromNode($resToAdd);
        $size = $domManifest->createAttribute('size');
        $size->value = $my_res->getSize();
        $domRes->appendChild($size);
        $hashname = $domManifest->createAttribute('hashname');
        $hashname->value = $my_res->getHashName();
        $domRes->appendChild($hashname);
        $archive->addFile('..'.SyncConstant::ZIPFILEDIR.$my_res->getHashName(), 'data'.SyncConstant::ZIPFILEDIR.$my_res->getHashName());      
        return $domManifest;
   }
   
   public function createResource($resource, $workspace, $user, $wsInfo, $path){
   
        $newResource = new File();
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
                              
        $file_hashname = $resource->getAttribute('hashname');
        $newResource->setSize($resource->getAttribute('size'));
        $newResource->setHashName($file_hashname);
        rename($path.'data'.SyncConstant::ZIPFILEDIR.$file_hashname, '..'.SyncConstant::ZIPFILEDIR.$file_hashname);
        
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

        if ($node_modif_date <= $resource->getAttribute('synchronization_date')) {
            $this->resourceManager->delete($node);
            $this->createResource($resource, $workspace);
            $wsInfo->addToUpdate($resource->getAttribute('name'));
        } else {
            if ($node_modif_date != $modif_date) {
                // Doublon generation
                $this->createDoublon($resource, $workspace, $node, $path);
                $wsInfo->addToDoublon($resource->getAttribute('name'));
            } else {
                // echo 'Already in Database'.'<br/>';
            }
        }
        
        return $wsInfo;
   }
   
   public function createDoublon($resource, $workspace, $node, $path){
        
        $newResource = new File();
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
      
        $file_hashname = $resource->getAttribute('hashname');
        $newResource->setSize($resource->getAttribute('size'));
        $newResource->setHashName($file_hashname);

        // The file already exist inside the database. We have to modify the Hashname of the file already present.
        $old_file = $this->resourceManager->getResourceFromNode($node);
        $old_hashname = $old_file->getHashName();
        $extension_name = substr($old_hashname, strlen($old_hashname)-4, 4);
        $new_hashname = $this->ut->generateGuid().$extension_name;

        $this->om->startFlushSuite();
        $old_file->setHashName($new_hashname);
        $this->om->endFlushSuite();

        rename('..'.SyncConstant::ZIPFILEDIR.$old_hashname, '..'.SyncConstant::ZIPFILEDIR.$new_hashname);
        rename($path.'data'.SyncConstant::ZIPFILEDIR.$file_hashname, '..'.SyncConstant::ZIPFILEDIR.$file_hashname);
        
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
   
   private function addResourceToZip(ZipArchive $archive, $resToAdd, $user, $archive, $path){
        $my_res = $this->resourceManager->getResourceFromNode($resToAdd);
        $archive->addFile('..'.SyncConstant::ZIPFILEDIR.$my_res->getHashName(), 'data'.$path.SyncConstant::ZIPFILEDIR.$my_res->getHashName());         
   }
}