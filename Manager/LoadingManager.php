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
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\CoreBundle\Pager\PagerFactory;
use Claroline\OfflineBundle\Entity\UserSynchronized;
//use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use JMS\DiExtraBundle\Annotation as DI;
use Claroline\CoreBundle\Manager\WorkspaceManager;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Entity\Resource\File;
use Claroline\OfflineBundle\ResourceTypeConstant;
use \ZipArchive;
use \DOMDocument;
use \DOMElement;

/**
 * @DI\Service("claroline.manager.loading_manager")
 */
 
//CONST FIL = 1;

class LoadingManager
{
    private $om;
    private $pagerFactory;
    private $translator;
    private $userSynchronizedRepo;
    private $resourceManager;

    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "pagerFactory"   = @DI\Inject("claroline.pager.pager_factory"),
     *     "translator"     = @DI\Inject("translator"),
     *     "wsManager"      = @DI\Inject("claroline.manager.workspace_manager"),
     *     "resourceManager"= @DI\Inject("claroline.manager.resource_manager")
     * })
     */
    public function __construct(
        ObjectManager $om,
        PagerFactory $pagerFactory,
        TranslatorInterface $translator,
        WorkspaceManager $wsManager,
        ResourceManager $resourceManager
    )
    {
        $this->om = $om;
        $this->pagerFactory = $pagerFactory;
        $this->userSynchronizedRepo = $om->getRepository('ClarolineOfflineBundle:UserSynchronized');
        $this->translator = $translator;
        $this->wsManager = $wsManager;
        $this->resourceManager = $resourceManager;
    }
    
    /**
     * This method will load and parse the manifest XML file
     *
     *
     */
    public function loadXML($xmlFilePath){
        $xmlDocument = new DOMDocument();
        $xmlDocument->load($xmlFilePath);
        
        /*
        *   getElementsByTagName renvoit un NodeList
        *   Sur un NodeList on peut faire ->length et ->item($i) qui retourne un NodeItem
        *   sur un NodeItem on peut faire 
                ->nodeName
                ->nodeValue
                ->childNodes qui renvoit lui meme un NodeList. la boucle est bouclée
        */
        $this->importDescription($xmlDocument->getElementsByTagName('description'));
        $this->importPlateform($xmlDocument->getElementsByTagName('plateform'));
        
        /*
        echo $descriptionNodeList->item(0)->nodeName.'<br/>';
        
        for ($i = 0; $i < $descriptionNodeList->length; $i++) {
            echo 'i='.$i.'  '.$descriptionNodeList->item($i)->nodeValue. "<br/>";
            echo $descriptionNodeList->item(0)->childNodes->item(0)->nodeValue;
            
         $enfants = $descriptionNodeList->childNodes;
            foreach($enfants as $child){
            echo $child->item(0)->nodeName.'<br/>';
        }
        }*/
        
    }
    
    private function importDescription($documentDescription)
    {
        $descriptionChilds = $documentDescription->item(0)->childNodes;
        for($i = 0; $i<$descriptionChilds->length ; $i++){
            echo '$i : '.$i.' '.$descriptionChilds->item($i)->nodeName.' '.$descriptionChilds->item($i)->nodeValue.'<br/>' ;
            /*
            *   ICI on peut controler / stocker les metadata du manfiest
            */
            if($descriptionChilds->item($i)->nodeName == 'user_id')
            {
                //$this->user = $descriptionChilds->item($i)->nodeValue;
            }
        }
        //echo $this->user;
    }
    
    private function importPlateform($plateform)
    {
        $plateformChilds = $plateform->item(0)->childNodes; // Platform childs = list of workspaces.
        for($i = 0; $i<$plateformChilds->length; $i++)
        {
            $item = $plateformChilds->item($i);
            //TODO CREER des constantes pour les fichier XML, ce sera plus propre que tout hardcode partout
            if($item->nodeName == 'workspace')
            {
                /**
                *   Check if a workspace with the given code already exist.
                */
                $workspace = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findByCode($item->getAttribute('code'));

                if(count($workspace) >= 1)
                {
                    echo 'I ve got my workspace! Yeah!'.'<br/>';
                    echo 'Its name : '.$workspace[0]->getName().'<br/>';
                }
                
                else
                {
                    echo 'This workspace : '.$item->getAttribute('code').' needs to be created!'.'<br/>';
                }
                /*
                *   - if workspace_code do not exist then create the workspace
                *       
                *   - proceed to the ressources (no matter if we have to create the workspace previously)
                */
                
                
                $this->importWorkspace($item->childNodes, $workspace[0]);
            }
        }
    }
    
    /**
    * Recupere un NodeList contenant les ressources  d'un workspace
    * Chaque ressource de cette NodeList devra donc être importee dans Claroline
    */
    private function importWorkspace($resourceList, $workspace)
    {
        
        for($i=0; $i<$resourceList->length; $i++)
        {     
            $res = $resourceList->item($i);
            if($res->nodeName == 'resource')
            {
                echo 'Workspace :          '.$res->nodeName.'<br/>';
                echo 'Resource Type : '.$res->getAttribute('type').'<br/>';
                /**
                * TODO Check if the resource already exist. 
                * If it does update it
                * If it doesnt call the createResource method.
                */
                $this->createResource($res, $workspace);
            }
        }
    }
    
    private function createResource($resource, $workspace)
    {
        $newResource;
        $type = $this->resourceManager->getResourceTypeByName($resource->getAttribute('type'));
        $creator = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findById($resource->getAttribute('creator'));
        
        // TODO a changer
        
        $parent_node = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findResourceNodeByWorkspace($workspace);
        
        
        // TODO Create a ressource based on his type. Then send the result to the create method of ResourceManager.
        echo 'HAAAAAAAAAAAAAAAAA '.get_class($type);
        switch($type->getId())
        {
            
            case ResourceTypeConstant::FILE :
                $newResource = new File();
                $newResource->setSize($resource->getAttribute('size'));
                $newResource->setHashName($resource->getAttribute('hashname'));
                $newResource->setName($resource->getAttribute('name'));
                $newResource->setMimeType($resource->getAttribute('mimetype'));
                echo 'I ask to create a resource'.'<br/>';
                $this->resourceManager->create($newResource, $type, $creator[0], $workspace, $parent_node[0]);
                echo 'File is done!'.'<br/>';
                break;
           // case DIR :            
            //    break;
           // case TEXT :
           //     break;
            
            
        }
        
        // Element commun a toutes les ressources.

    }
    
    private function createWorkspace($workspace)
    {
        // TODO Create a workspace if no CODE was found in the DataBase.
        // Use the create method from WorkspaceManager.
    }
    
}