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
use Claroline\CoreBundle\Entity\Resource\Revision;
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
    private $resourceNodeRepo;
    private $revisionRepo;
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
        $this->resourceNodeRepo = $om->getRepository('ClarolineCoreBundle:Resource\ResourceNode');
        $this->revisionRepo = $om->getRepository('ClarolineCoreBundle:Resource\Revision');
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
    public function loadZip($zipPath, User $user)
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
            $this->loadXML($this->path.SyncConstant::MANIFEST.'_'.$user->getId().'.xml');
            //$this->loadXML('manifest_test_x.xml'); //Actually used for test.
            
            //Destroy Directory
        }
        else
        {
            //Make a pop-up rather than a exception maybe.
            throw new \Exception('Impossible to load the zip file');
        }
        
        //Destroy the Zip
        
    }
    
    /**
     * This method will load and parse the manifest XML file
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
        
    }
    
    /*
    *   This method is used to work on the different fields inside the
    *   <description> tags in the XML file.
    */
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
                //$this->user = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findById($descriptionChilds->item($i)->nodeValue);
                $this->user = $this->om->getRepository('ClarolineCoreBundle:User')->findOneBy(array('id' => $descriptionChilds->item($i)->nodeValue));
                echo 'My user : '.$this->user->getFirstName().'<br/>';
            }
        }
    }
    
    /*
    *   This method is used to work on the different fields inside the
    *   <platform> tags in the XML file.
    */
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
                //$workspace = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findByGuid($item->getAttribute('guid'));
                $workspace = $this->om->getRepository('ClarolineCoreBundle:Workspace\AbstractWorkspace')->findOneBy(array('guid' => $item->getAttribute('guid')));
                
                
                if(count($workspace) >= 1)
                {
                    echo 'Mon Workspace : '.$workspace->getGuid().'<br/>';
                    /*  When a workspace with the same guid is found, we can update him if it's required.
                    *   First, we need to check if : modification_date_offline > modification_date_online.
                    *       - If it is, we need to check if : modification_date_online <= user_synchronisation_date
                    *           - If it is, we just need to update the workspace
                    *           - If it's not, that means that the workspace has been manipulated offline AND online.
                    *       - If it's not, we don't need to go further.
                    */
                    //echo 'I need to update my workspace!'.'<br/>';
                    $NodeWorkspace = $this->resourceNodeRepo->findOneBy(array('workspace' => $workspace));
                    // TODO Mettre à jour la date de modification et le nom du directory
                    echo 'Mon Workspace Node '.$NodeWorkspace->getName().'<br/>';
                    $node_modif_date = $NodeWorkspace->getModificationDate()->getTimestamp();
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
                    $workspace_creator = $this->om->getRepository('ClarolineCoreBundle:User')->findOneBy(array('id' => $item->getAttribute('creator')));
                    echo 'Le creator de mon workspace : '.$workspace_creator->getFirstName().'<br/>';
                    $workspace = $this->createWorkspace($item, $workspace_creator);
                    //$workspace = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findByGuid($item->getAttribute('guid'));
                }
                
                echo 'En route pour les ressources!'.'<br/>';
                $this->importWorkspace($item->childNodes, $workspace);
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
                $node = $this->resourceNodeRepo->findOneBy(array('hashName' => $res->getAttribute('hashname_node')));
                
                if(count($node) >= 1)
                {
                    //echo 'Faut update!'.'<br/>';
                    $this->updateResource($res, $node, $workspace);           
                }               
                else
                {
                    //echo 'Faut creer!'.'<br/>';
                    $this->createResource($res, $workspace, null, false);
                } 
            }
        }
    }
    
    /*
    *   This method will update an already present resource in the database using the 
    *   informations given by the XML file.
    */   
    private function updateResource($resource, $node, $workspace)
    {
        echo 'I need to update my resource!'.'<br/>';
        $type = $this->resourceManager->getResourceTypeByName($resource->getAttribute('type'));
        $modif_date = $resource->getAttribute('modification_date');
        $creation_date = $resource->getAttribute('creation_date'); //USELESS?
        $node_modif_date = $node->getModificationDate()->getTimestamp();
        $user_sync = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($this->user);
        
        switch($type->getId())
        {
            case SyncConstant::DIR :
                echo 'It s a directory!'.'<br/>';
                if($node_modif_date < $modif_date)
                {
                    // TODO Mettre à jour la date de modification et le nom du directory
                    // Only Rename?
                    //  echo 'Mon directory est renomme'.'<br/>';
                    $this->resourceManager->rename($node, $resource->getAttribute('name'));
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
                *       - If it is, we know that the resource can be erased because it's now obsolete.
                *       - If it's not, we check if the modification_date of both resources (in database and in the xml) are
                *       different. 
                *           - If it is we need to create 'doublon' to preserve both resource
                *           - If it's not we suppose that there are the same (connexion lost, ...)
                */

                if($node_modif_date <= $user_sync[0]->getLastSynchronization()->getTimestamp())
                {
                    // La nouvelle ressource est une update de l'ancienne
                   // echo 'I ask to erase a resource'.'<br/>';
                   echo'after'.'<br/>';
                   echo 'name : '.$node->getName().'<br/>';
                    $this->resourceManager->delete($node);
                    $this->createResource($resource, $workspace, null, false);
                    echo'before';
                }
                else 
                {
                    // On sait que la ressource a ete update entre nos deux synchro
                    // On regarde si les dates de modifications de nos deux ressources sont différentes
                    // On part du postulat que deux ressources avec meme hashname et modification_date sont identiques.
                    echo 'ModificationDate of Node : '.$node->getModificationDate()->format('d/m/Y H:i:s').'<br/>';
                    echo 'ModificationDate XML : '.$modif_date.'<br/>';
            
                    if($node_modif_date != $modif_date)
                    {
                        // Génération des doublons
                        echo 'I ask to create a doublon'.'<br/>';
                        $this->createResource($resource, $workspace, $node, true);
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
    
    /*
    *   This function create a resource based on the informations given in the XML file.
    */
    
    private function createResource($resource, $workspace, $node, $doublon)
    {   /* 
        *   Attention, les dates de modifications sont erronees en DB.
        *   A chaque creation de ressources, le champ next_id de la ressource precedentes
        *   est modifie et donc sa modification_date egalement
        */
        
        /*
        *   Solution possible :
        *   utiliser l'entity manager avec getlistener pour trouver celui responsable
        *   de l'update automatique des dates
        *   Ensuite utiliser geteventmanager puis removeeventsubscriber pour enlever 
        *   ce listener.
        */
       // $dispatcher = $this->get('event_dispatcher');
        echo 'I ask to create a resource'.'<br/>';
        
        /*
        *   Load the required informations from the XML file.
        */
        $ds = DIRECTORY_SEPARATOR;
        $newResource;
        $newResourceNode;
        $creation_date = new DateTime();
        $modification_date = new DateTime();
        $creation_date->setTimestamp($resource->getAttribute('creation_date'));
        $modification_date->setTimestamp($resource->getAttribute('modification_date'));
        
        $type = $this->resourceManager->getResourceTypeByName($resource->getAttribute('type'));
        $creator = $this->om->getRepository('ClarolineCoreBundle:User')->findOneBy(array('id' => $resource->getAttribute('creator')));
        $parent_node = $this->resourceNodeRepo->findOneBy(array('hashName' => $resource->getAttribute('hashname_parent')));
        
        if(count($parent_node) < 1)
        {
            // If the parent node doesn't exist anymore, workspace will be the parent.
            echo 'Mon parent est mort ! '.'<br/>';
            $parent_node  = $this->resourceNodeRepo->findOneBy(array('workspace' => $workspace));
        }
        
        /*
        *   Prepare the resource based on its Type.
        */
        switch($type->getId())
        {
            
            case SyncConstant::FILE :
                $newResource = new File();
                $file_hashname = $resource->getAttribute('hashname');
                $newResource->setSize($resource->getAttribute('size'));
                $newResource->setHashName($file_hashname);
                
                if($doublon)
                {
                    // The file already exist inside the database. We have to modify the Hashname of the file already present.
                    
                    $old_file = $this->resourceManager->getResourceFromNode($node);
                    $old_hashname = $old_file->getHashName();
                    $extension_name = substr($old_hashname, strlen($old_hashname)-4, 4);
                    $new_hashname = $this->ut->generateGuid().$extension_name;
                    
                    $this->om->startFlushSuite();
                    $old_file->setHashName($new_hashname);
                    $this->om->endFlushSuite(); 
                    
                    rename('..'.SyncConstant::ZIPFILEDIR.$old_hashname, '..'.SyncConstant::ZIPFILEDIR.$new_hashname);
                
                }
                
                rename($this->path.'data'.SyncConstant::ZIPFILEDIR.$file_hashname, '..'.SyncConstant::ZIPFILEDIR.$file_hashname);
                break;
                
            case SyncConstant::DIR : 
                $newResource = new Directory();            
                break;
                
            case SyncConstant::TEXT :
            
                $newResource = new Text();
                $revision = new Revision();
                $revision->setContent($resource->getAttribute('content'));
                $revision->setUser($this->user);
                $revision->setText($newResource);
                $this->om->persist($revision);                         
                break;       
        }      
        
        /*
        *   If doublon == true, we add the tag '@offline' to the name of the resource
        *   and modify the Hashname of the resource already present in the Database.
        */
        
        if($doublon)
        {
            $newResource->setName($resource->getAttribute('name').'@offline');
            echo 'ModificationDate of Node Before : '.$node->getModificationDate()->format('d/m/Y H:i:s').'<br/>';
            $oldModificationDate = $node->getModificationDate();
            echo 'ModificationDate Old Before : '.$oldModificationDate->format('d/m/Y H:i:s').'<br/>';
            
            $this->om->startFlushSuite();
            $node->setNodeHashName($this->ut->generateGuid());
            $this->resourceManager->logChangeSet($node);            
            //$node->setModificationDate($oldModificationDate);      
            $this->om->endFlushSuite(); 
           
            echo 'ModificationDate of Node After : '.$node->getModificationDate()->format('d/m/Y H:i:s').'<br/>';
            echo 'ModificationDate Old After : '.$oldModificationDate->format('d/m/Y H:i:s').'<br/>';
                       
        }
        else
        {
            $newResource->setName($resource->getAttribute('name'));
        }
        
        $newResource->setMimeType($resource->getAttribute('mimetype'));
        
        $this->resourceManager->create($newResource, $type, $creator, $workspace, $parent_node, null, array(), $resource->getAttribute('hashname_node'));       
        $newResourceNode = $newResource->getResourceNode();
        
        echo 'ModificationDate of New Node Before : '.$newResourceNode->getModificationDate()->format('d/m/Y H:i:s').'<br/>';
        echo 'ModificationDate of XML Before : '.$modification_date->format('d/m/Y H:i:s').'<br/>';
        
        // Update of the creation and modification date of the resource. 
       // $this->changeDate($newResourceNode,$creation_date,$modification_date);

    }
    
    /*
    *   Create and return a new workspace detailed in the XML file.
    */
       
    private function createWorkspace($workspace, $user)
    {
        // Use the create method from WorkspaceManager.
        echo 'Je cree mon Workspace!'.'<br/>';
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
               
        $my_ws = $this->om->getRepository('ClarolineCoreBundle:Workspace\AbstractWorkspace')->findOneBy(array('code' => $workspace->getAttribute('code')));
        $NodeWorkspace = $this->resourceNodeRepo->findOneBy(array('workspace' => $my_ws));                       
        
        $creation_date->setTimestamp($workspace->getAttribute('creation_date'));
        $modification_date->setTimestamp($workspace->getAttribute('modification_date'));      
        
        $this->om->startFlushSuite();
        $my_ws->setGuid($workspace->getAttribute('guid'));
        $NodeWorkspace->setCreationDate($creation_date);
        $NodeWorkspace->setModificationDate($modification_date);
        $NodeWorkspace->setNodeHashName($workspace->getAttribute('hashname_node'));
        $this->om->endFlushSuite();  
        
        return $my_ws;

    }
    
    /*
    *   Change the creation and modification dates of a node.
    *
    *   @param \Claroline\CoreBundle\Entity\Resource\ResourceNode $node 
    *
    *   @return \Claroline\CoreBundle\Entity\Resource\ResourceNode   
    */
    
    private function changeDate($node, $creation_date, $modification_date)
    {
        echo 'I update my date!'.$node->getName().'<br/>';
        /*
        $this->om->startFlushSuite();
        $node->setCreationDate($creation_date);
        $node->setModificationDate($modification_date);
        $this->om->persist($node);
        $this->resourceManager->logChangeSet($node);
        $this->om->endFlushSuite();  
        */
        
        $node->setCreationDate($creation_date);
        $node->setModificationDate($modification_date);
        $this->om->persist($node);
        $this->resourceManager->logChangeSet($node);
        $this->om->flush();

        return $node;
        
        echo 'ModificationDate of New Node After : '.$node->getModificationDate()->format('d/m/Y H:i:s').'<br/>';
        echo 'ModificationDate of XML After : '.$modification_date->format('d/m/Y H:i:s').'<br/>';
        
    }
    
}