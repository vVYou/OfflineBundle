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
use Claroline\CoreBundle\Entity\Resource\Directory;
use Claroline\CoreBundle\Entity\Resource\Text;
use Claroline\OfflineBundle\ResourceTypeConstant;
use Symfony\Component\HttpFoundation\Request;
use Claroline\CoreBundle\Library\Workspace\Configuration;
use Claroline\CoreBundle\Library\Utilities\ClaroUtilities;
use \ZipArchive;
use \DOMDocument;
use \DOMElement;

/**
 * @DI\Service("claroline.manager.loading_manager")
 */

class LoadingManager
{
    private $om;
    private $pagerFactory;
    private $translator;
    private $userSynchronizedRepo;
    private $resourceManager;
    private $workspaceManager;
    private $templateDir;
    private $user;
    private $ut;

    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "pagerFactory"   = @DI\Inject("claroline.pager.pager_factory"),
     *     "translator"     = @DI\Inject("translator"),
     *     "wsManager"      = @DI\Inject("claroline.manager.workspace_manager"),
     *     "resourceManager"= @DI\Inject("claroline.manager.resource_manager"),
     *     "workspaceManager"   = @DI\Inject("claroline.manager.workspace_manager"),
     *     "templateDir"    = @DI\Inject("%claroline.param.templates_directory%"),
     *     "ut"            = @DI\Inject("claroline.utilities.misc")
     * })
     */
    public function __construct(
        ObjectManager $om,
        PagerFactory $pagerFactory,
        TranslatorInterface $translator,
        WorkspaceManager $wsManager,
        ResourceManager $resourceManager,
        WorkspaceManager $workspaceManager,
        $templateDir,
        ClaroUtilities $ut
    )
    {
        $this->om = $om;
        $this->pagerFactory = $pagerFactory;
        $this->userSynchronizedRepo = $om->getRepository('ClarolineOfflineBundle:UserSynchronized');
        $this->translator = $translator;
        $this->wsManager = $wsManager;
        $this->resourceManager = $resourceManager;
        $this->workspaceManager = $workspaceManager;
        $this->templateDir = $templateDir;
        $this->ut = $ut;
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
                $this->user = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findById($descriptionChilds->item($i)->nodeValue);
                echo 'Mon user : '.$this->user[0]->getFirstName().'<br/>';
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
                *   - if it doesn't exist then it will be created 
                *   - then proceed to the resources (no matter if we have to create the workspace previously)
                */
                $workspace = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findByGuid($item->getAttribute('guid'));

                if(count($workspace) >= 1)
                {
                    /*  When a workspace with the same guid is found, we can update him if it's required.
                    *   First, we need to check if : modification_date_offline > modification_date_online.
                    *       - If it is, we need to check if : modification_date_online <= user_synchronisation_date
                    *           - If it is, we just need to update the workspace
                    *           - If it's not, that means that the workspace has been manipulated offline AND online.
                    *       - If it's not, we don't need to go further.
                    */
                    
                    echo 'I need to update my workspace!'.'<br/>';
                    echo 'Mon Super user : '.$this->user[0]->getFirstName().'<br/>';
                }
                
                else
                {
                    echo 'This workspace : '.$item->getAttribute('code').' needs to be created!'.'<br/>';
                    $workspace_creator = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findById($item->getAttribute('creator'));
                    $this->createWorkspace($item, $workspace_creator[0]);
                    $workspace = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findByCode($item->getAttribute('code'));
                }
                
                echo 'En route pour les ressources!'.'<br/>';
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
                $node = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findResourceNodeByHashname($res->getAttribute('hashname_node'));

                if(count($node) >= 1)
                {
                    echo 'I need to update my resource!'.'<br/>';
                    $modif_date = $res->getAttribute('modification_date');
                    $creation_date = $res->getAttribute('creation_date');
                    $node_modif_date = $node[0]->getModificationDate()->getTimestamp();
                    
                    /*  When a resource with the same hashname is found, we can update him if it's required.
                    *   First, we need to check if : modification_date_online <= user_synchronisation_date.
                    *       - If it is, we know that the resource can be erased.
                    *       - If it's not, we don't need to go further.
                    */

                    $user_sync = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($this->user[0]);
                    if($node_modif_date <= $user_sync[0]->getLastSynchronization()->getTimestamp())
                    {
                        // La nouvelle ressource est une update de l'ancienne
                        $this->resourceManager->delete($node[0]);
                        $this->createResource($res, $workspace, null);
                    }
                    else 
                    {
                        // On sait que la ressource a ete update entre nos deux synchro
                        // On regarde si les dates de modifications de nos deux ressources sont différentes
                        // On part du postulat que deux ressources avec meme hashname et modification_date sont identiques.
                        if($node_modif_date != $modif_date)
                        {
                            // Génération des doublons
                            $this->createResource($res, $workspace, $node[0], true);
                        }
                        else
                        {
                            echo 'Already in the Database!'.'<br/>';
                        }
                    }
                    echo 'Mon Ultra user : '.$this->user[0]->getFirstName().'<br/>';
                }
                
                else
                {
                    $this->createResource($res, $workspace, null);
                }
                
            }
        }
    }
    
    private function createResource($resource, $workspace, $node, $doublon = false)
    {
        $newResource;
        $newResourceNode;
        $type = $this->resourceManager->getResourceTypeByName($resource->getAttribute('type'));
        $creator = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findById($resource->getAttribute('creator'));
        
        $parent_node = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findResourceNodeByHashname($resource->getAttribute('hashname_parent'));
        
        // TODO Create a ressource based on his type. Then send the result to the create method of ResourceManager.
        switch($type->getId())
        {
            
            case ResourceTypeConstant::FILE :
                $newResource = new File();
                $newResource->setSize($resource->getAttribute('size'));
                $newResource->setHashName($resource->getAttribute('hashname'));
                echo 'boum'.'<br/>';
                break;
            case ResourceTypeConstant::DIR : 
                $newResource = new Directory();            
                break;
            case ResourceTypeConstant::TEXT :
                $newResource = new Text();
                break;
     
        }
        
        $newResource->setMimeType($resource->getAttribute('mimetype'));
        echo 'I ask to create a resource'.'<br/>';
        
        if($doublon)
        {
            $newResource->setName($resource->getAttribute('name').'@offline');
            
            echo 'I ask to create a resource'.'<br/>';
            $this->om->startFlushSuite();
            $node->setNodeHashName($this->ut->generateGuid());
            $this->om->endFlushSuite();   
        }
        else
        {
            $newResource->setName($resource->getAttribute('name'));
        }
        
        $this->resourceManager->create($newResource, $type, $creator[0], $workspace, $parent_node[0], null, array(), $resource->getAttribute('hashname_node'));       
        
        //$newResourceNode = $newResource->getResourceNode();
        //echo 'New Resource Node : '.get_class($newResourceNode).'<br/>';
        //echo 'New Resource Node Hashname Before'.$newResourceNode->getNodeHashName().'<br/>';
        //$newResourceNode->setNodeHashName($resource->getAttribute('hashname_node'));
        //echo 'New Resource Node Hashname After'.$newResourceNode->getNodeHashName().'<br/>';
        
        // Element commun a toutes les ressources.

    }
       
    private function createWorkspace($workspace, $user)
    {
        // TODO Create a workspace if no CODE was found in the DataBase.
        // Use the create method from WorkspaceManager.
        $ds = DIRECTORY_SEPARATOR;

        $type = Configuration::TYPE_SIMPLE;
        $config = Configuration::fromTemplate(
            $this->templateDir . $ds . 'default.zip'
        );
        $config->setWorkspaceType($type);
        $config->setWorkspaceName($workspace->getAttribute('name'));
        $config->setWorkspaceCode($workspace->getAttribute('code'));
        $config->setDisplayable($workspace->getAttribute('displayable'));
        $config->setSelfRegistration($workspace->getAttribute('selfregistration'));
        $config->setSelfUnregistration($workspace->getAttribute('selfunregistration'));
        //$user = $this->security->getToken()->getUser();
        $this->workspaceManager->create($config, $user);
        //$this->tokenUpdater->update($this->security->getToken());
        //$route = $this->router->generate('claro_workspace_list');
        echo 'Workspace Created!'.'<br/>';
        //return new RedirectResponse($route);

    }
    
}