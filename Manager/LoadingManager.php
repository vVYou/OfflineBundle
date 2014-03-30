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
use Claroline\OfflineBundle\SyncConstant;
use Symfony\Component\HttpFoundation\Request;
use Claroline\CoreBundle\Library\Workspace\Configuration;
use Claroline\CoreBundle\Library\Utilities\ClaroUtilities;
use Claroline\CoreBundle\Event\StrictDispatcher;
use \ZipArchive;
use \DOMDocument;
use \DOMElement;
use \DateTime;

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
    private $dispatcher;
    private $path;

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
     *     "ut"            = @DI\Inject("claroline.utilities.misc"),
     *     "dispatcher"      = @DI\Inject("claroline.event.event_dispatcher")
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
        ClaroUtilities $ut,
        StrictDispatcher $dispatcher
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
        $this->dispatcher = $dispatcher;
    }
    
    /*
    *   This method open the zip file, call the loadXML function and 
    *   destroy the zip file while everything is done.
    */
    
    public function loadZip($zipPath)
    {
        $ds = DIRECTORY_SEPARATOR;
        
        //Extract the Zip
        $archive = new ZipArchive();
        if ($archive->open($zipPath))
        {
            //Extract the Hashname of the ZIP from the path (length of hashname = 32 char).
            $zip_hashname = substr($zipPath, strlen($zipPath)-40, 36);
            $this->path = SyncConstant::DIRZIP.$ds.$zip_hashname.$ds;
            $tmpdirectory = $archive->extractTo($this->path);
            //Call LoadXML
            $this->loadXML($this->path.SyncConstant::MANIFEST.'_'.$zip_hashname.'.xml');
            //Destroy Directory
        }
        else
        {
            //echo 'Impossible to open the zip file';
            throw new \Exception('Impossible to load the zip file');
        }
        //Destroy the Zip
        
    }
    
    /**
     * This method will load and parse the manifest XML file
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
            //echo '$i : '.$i.' '.$descriptionChilds->item($i)->nodeName.' '.$descriptionChilds->item($i)->nodeValue.'<br/>' ;
            /*
            *   ICI on peut controler / stocker les metadata du manfiest
            */
            if($descriptionChilds->item($i)->nodeName == 'user_id')
            {
                $this->user = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findById($descriptionChilds->item($i)->nodeValue);
               // echo 'Mon user : '.$this->user[0]->getFirstName().'<br/>';
            }
        }
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
                    //echo 'I need to update my workspace!'.'<br/>';
                    $NodeWorkspace = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findResourceNodeByWorkspace($workspace[0]);
                    // TODO Mettre à jour la date de modification et le nom du directory
                    $node_modif_date = $NodeWorkspace[0]->getModificationDate()->getTimestamp();
                    $modif_date = $item->getAttribute('modification_date');
                    if($modif_date > $node_modif_date)
                    {
                       // echo 'Need to update!'.'<br/>';
                    }
                    else
                    {
                       // echo 'No need to update!'.'<br/>';
                    }
                }
                
                else
                {
                  //  echo 'This workspace : '.$item->getAttribute('code').' needs to be created!'.'<br/>';
                    $workspace_creator = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findById($item->getAttribute('creator'));
                    $this->createWorkspace($item, $workspace_creator[0]);
                    $workspace = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findByGuid($item->getAttribute('guid'));
                }
                
               // echo 'En route pour les ressources!'.'<br/>';
                $this->importWorkspace($item->childNodes, $workspace[0]);
            }
        }
    }
    
    /**
    * Visit all the 'resource' field in the 'workspace' inside the XML file and
    * either create or update the corresponding resources.
    */
    private function importWorkspace($resourceList, $workspace)
    {
        
        for($i=0; $i<$resourceList->length; $i++)
        {     
            $res = $resourceList->item($i);
            if($res->nodeName == 'resource')
            {
               // echo 'Workspace :          '.$res->nodeName.'<br/>';
               // echo 'Resource Type : '.$res->getAttribute('type').'<br/>';
                $node = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findResourceNodeByHashname($res->getAttribute('hashname_node'));

                if(count($node) >= 1)
                {
                    $this->updateResource($res, $node, $workspace);
                }               
                else
                {
                    $this->createResource($res, $workspace, null);
                } 
            }
        }
    }
    
    private function updateResource($resource, $node, $workspace)
    {
        echo 'I need to update my resource!'.'<br/>';
        $type = $this->resourceManager->getResourceTypeByName($resource->getAttribute('type'));
        $modif_date = $resource->getAttribute('modification_date');
        $creation_date = $resource->getAttribute('creation_date'); //USELESS?
        $node_modif_date = $node[0]->getModificationDate()->getTimestamp();
        $user_sync = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($this->user[0]);
        
        switch($type->getId())
        {
            case SyncConstant::DIR :
                echo 'It s a directory!'.'<br/>';
                if($node_modif_date < $modif_date)
                {
                    // TODO Mettre à jour la date de modification et le nom du directory
                    // Only Rename?
                  //  echo 'Mon directory est renomme'.'<br/>';
                    $this->resourceManager->rename($node[0], $resource->getAttribute('name'));
                }
                else
                {
                   // echo 'No need to update'.'<br/>';
                }
                break;
            case SyncConstant::FORUM :
                echo 'It s a forum!'.'<br/>';
                // trouver le forum-category-sujet-message et ajouter/
                break;
            default :
                echo 'It s a file or a text!'.'<br/>';
                /*  When a resource with the same hashname is found, we can update him if it's required.
                *   First, we need to check if : modification_date_online <= user_synchronisation_date.
                *       - If it is, we know that the resource can be erased.
                *       - If it's not, we check if the modification_date of both resources (in database and in the xml) are
                *       different. 
                *           - If it is we need to create 'doublon' to preserve both resource
                *           - If it's not we suppose that there are the same
                */
                //echo 'It s somthing else...'.'<br/>';
                if($node_modif_date <= $user_sync[0]->getLastSynchronization()->getTimestamp())
                {
                    // La nouvelle ressource est une update de l'ancienne
                    // TODO NOT GOOD!e
                   // echo 'I ask to erase a resource'.'<br/>';
                    $this->resourceManager->delete($node[0]);
                    $this->createResource($resource, $workspace, null);
                }
                else 
                {
                    // On sait que la ressource a ete update entre nos deux synchro
                    // On regarde si les dates de modifications de nos deux ressources sont différentes
                    // On part du postulat que deux ressources avec meme hashname et modification_date sont identiques.
                    if($node_modif_date != $modif_date)
                    {
                        // Génération des doublons
                       // echo 'I ask to create a doublon'.'<br/>';
                        $this->createResource($resource, $workspace, $node[0], true);
                    }
                    else
                    {
                        //TODO : Afficher ce message dans une fenetre plutot que via un echo.
                        echo 'Already in the Database!'.'<br/>';
                    }
                }
                break;
        }
    }
    
    private function createResource($resource, $workspace, $node, $doublon = false)
    {
        $ds = DIRECTORY_SEPARATOR;
        echo 'I ask to create a resource'.'<br/>';
        $newResource;
        $newResourceNode;
        $creation_date = new DateTime();
        $modification_date = new DateTime();
        
        $type = $this->resourceManager->getResourceTypeByName($resource->getAttribute('type'));
        $creator = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findById($resource->getAttribute('creator'));
        
        $parent_node = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findResourceNodeByHashname($resource->getAttribute('hashname_parent'));
        if(count($parent_node) < 1)
        {
            // If the parent node doesn't exist anymore, workspace will be the parent.
            echo 'Mon parent est mort ! '.'<br/>';
            $parent_node  = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findResourceNodeByWorkspace($workspace);
        }
        
        $creation_date->setTimestamp($resource->getAttribute('creation_date'));
        $modification_date->setTimestamp($resource->getAttribute('modification_date'));
        // TODO Create a ressource based on his type. Then send the result to the create method of ResourceManager.
        switch($type->getId())
        {
            
            case SyncConstant::FILE :
                $newResource = new File();
                $file_hashname = $resource->getAttribute('hashname');
                $newResource->setSize($resource->getAttribute('size'));
                if($doublon)
                {
                    echo 'doublon part'.'<br/>';
                    // The file already exist inside the database. We have to modify the Hashname of the file already presents.
                    $old_file = $this->resourceManager->getResourceFromNode($node);
                    $this->om->startFlushSuite();
                    $old_file->setHashName($this->ut->generateGuid());
                    //TODO Modify the file present in the files directory
                    $this->om->endFlushSuite();  
                }

                $newResource->setHashName($file_hashname);
                rename($this->path.'data'.SyncConstant::ZIPFILEDIR.$file_hashname, '..'.SyncConstant::ZIPFILEDIR.$file_hashname);
                //echo 'boum'.'<br/>';
                break;
            case SyncConstant::DIR : 
                $newResource = new Directory();            
                break;
            case SyncConstant::TEXT :
                $newResource = new Text();
                break;
     
        }
        
        $newResource->setMimeType($resource->getAttribute('mimetype'));
        
        if($doublon)
        {
            $newResource->setName($resource->getAttribute('name').'@offline');
            $oldModificationDate = $node->getModificationDate();
            $this->om->startFlushSuite();
            $node->setNodeHashName($this->ut->generateGuid()); 
            $node->setModificationDate($oldModificationDate);      
            $this->om->endFlushSuite();   
        }
        else
        {
            $newResource->setName($resource->getAttribute('name'));
        }
        
        $this->resourceManager->create($newResource, $type, $creator[0], $workspace, $parent_node[0], null, array(), $resource->getAttribute('hashname_node'));       
        $newResourceNode = $newResource->getResourceNode();
        
        $this->om->startFlushSuite();
        $newResourceNode->setCreationDate($creation_date);
        $newResourceNode->setModificationDate($modification_date);
        $this->om->endFlushSuite();  
        
        // Element commun a toutes les ressources.

    }
       
    private function createWorkspace($workspace, $user)
    {
        // TODO Create a workspace if no CODE was found in the DataBase.
        // Use the create method from WorkspaceManager.
        $creation_date = new DateTime();
        $modification_date = new DateTime();
        
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
        
        $my_ws = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findByCode($workspace->getAttribute('code'));
        $NodeWorkspace = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findResourceNodeByWorkspace($my_ws[0]);
        $creation_date->setTimestamp($workspace->getAttribute('creation_date'));
        $modification_date->setTimestamp($workspace->getAttribute('modification_date'));      
        
        $this->om->startFlushSuite();
        $my_ws[0]->setGuid($workspace->getAttribute('guid'));
        $NodeWorkspace[0]->setCreationDate($creation_date);
        $NodeWorkspace[0]->setModificationDate($modification_date);
        $this->om->endFlushSuite();  
        
        //echo 'Workspace Created!'.'<br/>';
        //return new RedirectResponse($route);

    }
    
    private function FileExtract($tmpFile, $hashname)
    {
        
    }
    
    private function cleanDirectory()
    {
        if (!rmdir($this->path))
        {
            throw new \Exception('Impossible to delete the directory containing the extracted files.');
        }
    }
    
}