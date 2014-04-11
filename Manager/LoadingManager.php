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

use JMS\DiExtraBundle\Annotation as DI;
use Claroline\CoreBundle\Manager\WorkspaceManager;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\ForumBundle\Manager\Manager;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Entity\Resource\File;
use Claroline\CoreBundle\Entity\Resource\Directory;
use Claroline\CoreBundle\Entity\Resource\Text;
use Claroline\CoreBundle\Entity\Resource\Revision;
use Claroline\CoreBundle\Library\Workspace\Configuration;
use Claroline\CoreBundle\Library\Utilities\ClaroUtilities;
use Claroline\CoreBundle\Library\Security\Utilities;
use Claroline\CoreBundle\Library\Security\TokenUpdater;
use Claroline\CoreBundle\Event\StrictDispatcher;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\CoreBundle\Pager\PagerFactory;
use Claroline\ForumBundle\Entity\Forum;
use Claroline\ForumBundle\Entity\Category;
use Claroline\ForumBundle\Entity\Subject;
use Claroline\ForumBundle\Entity\Message;
use Claroline\OfflineBundle\SyncConstant;
use Claroline\OfflineBundle\SyncInfo;
use Claroline\OfflineBundle\Entity\UserSynchronized;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
    private $categoryRepo;
    private $subjectRepo;
    private $messageRepo;
    private $forumRepo;
    private $resourceManager;
    private $workspaceManager;
    private $forumManager;
    private $templateDir;
    private $user;
    private $ut;
    private $dispatcher;
    private $path;
    private $security;
    private $tokenUpdater;
    private $syncInfoArray;

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
     *     "forumManager"   = @DI\Inject("claroline.manager.forum_manager"),
     *     "templateDir"    = @DI\Inject("%claroline.param.templates_directory%"),
     *     "ut"            = @DI\Inject("claroline.utilities.misc"),
     *     "dispatcher"      = @DI\Inject("claroline.event.event_dispatcher"),
     *     "security"           = @DI\Inject("security.context"),
     *     "tokenUpdater"       = @DI\Inject("claroline.security.token_updater")
     * })
     */
    public function __construct(
        ObjectManager $om,
        PagerFactory $pagerFactory,
        TranslatorInterface $translator,
        WorkspaceManager $wsManager,
        ResourceManager $resourceManager,
        WorkspaceManager $workspaceManager,
        Manager $forumManager,
        $templateDir,
        ClaroUtilities $ut,
        StrictDispatcher $dispatcher,
        SecurityContextInterface $security,
        TokenUpdater $tokenUpdater
    )
    {
        $this->om = $om;
        $this->pagerFactory = $pagerFactory;
        $this->userSynchronizedRepo = $om->getRepository('ClarolineOfflineBundle:UserSynchronized');
        $this->resourceNodeRepo = $om->getRepository('ClarolineCoreBundle:Resource\ResourceNode');
        $this->revisionRepo = $om->getRepository('ClarolineCoreBundle:Resource\Revision');
        $this->categoryRepo = $om->getRepository('ClarolineForumBundle:Category');
        $this->subjectRepo = $om->getRepository('ClarolineForumBundle:Subject');
        $this->messageRepo = $om->getRepository('ClarolineForumBundle:Message');
        $this->forumRepo = $om->getRepository('ClarolineForumBundle:Forum');
        $this->translator = $translator;
        $this->wsManager = $wsManager;
        $this->resourceManager = $resourceManager;
        $this->workspaceManager = $workspaceManager;
        $this->forumManager = $forumManager;
        $this->templateDir = $templateDir;
        $this->ut = $ut;
        $this->dispatcher = $dispatcher;
        $this->security = $security;
        $this->tokenUpdater = $tokenUpdater;
        $this->syncInfoArray = array();
        
    }
    
    /*
    *   This method open the zip file, call the loadXML function and 
    *   destroy the zip file while everything is done.
    */
    public function loadZip($zipPath, User $user)
    {   
        //Extract the Zip
        $archive = new ZipArchive();
        if ($archive->open($zipPath))
        {
            //Extract the Hashname of the ZIP from the path (length of hashname = 32 char).
            $zip_hashname = substr($zipPath, strlen($zipPath)-40, 36);
            $this->path = SyncConstant::DIRZIP.'/'.$zip_hashname.'/';
            echo 'J extrait dans ce path : '.$this->path.'<br/>';
            $tmpdirectory = $archive->extractTo($this->path);
            
            //Call LoadXML
            $this->loadXML($this->path.SyncConstant::MANIFEST.'_'.$user->getId().'.xml');
            //$this->loadXML('manifest_test_x.xml'); //Actually used for test.
            
            //Destroy Directory
            //$this->rrmdir($this->path);
            //echo 'DIR deleted <br/>';
            
            //TODO : Utile seulement pour les tests.
            // foreach($this->syncInfoArray as $syncInfo)
            // {
                // echo 'For the workspace : '.$syncInfo->getWorkspace().'<br/>';
                // $add = $syncInfo->getCreate();
                // foreach($add as $elem)
                // {
                    // echo 'Create'.'<br/>';
                    // echo $elem.'<br/>';
                // }
                
                // $update = $syncInfo->getUpdate();
                // foreach($update as $up)
                // {
                    // echo 'Update'.'<br/>';
                    // echo $up.'<br/>';
                // }
                
                // $doublon = $syncInfo->getDoublon();
                // foreach($doublon as $doub)
                // {
                    // echo 'Doublon'.'<br/>';
                    // echo $doub.'<br/>';
                // }
            // }
        }
        else
        {
            //Make a pop-up rather than a exception maybe.
            throw new \Exception('Impossible to load the zip file');
        }
        
        return $this->syncInfoArray;
    }

    public function loadPublicWorkspaceList($allWorkspace)
    {
        $xmlDocument = new DOMDocument();
        $xmlDocument->load($allWorkspace);
        $this->importPlateform($xmlDocument->getElementsByTagName('workspace_list'));      
        //$this->importPlateform($xmlDocument->getElementsByTagName('workspace_list'), false);      
    }

    /*
    *
    *       TO FIX !!!!!!!
    *
    *   Code inspired of :
    *   http://stackoverflow.com/questions/9760526/php-remove-not-empty-folder
    */
    public function rrmdir($dir){
        if (is_dir($dir)){
            $objects = scandir($dir);
            foreach($objects as $object){
                if($object != "." && $object != ".."){
                    if(is_dir($dir."/".$object)){
                    //if(filetype($dir."/".$object) == "dir"){
                        rmdir($dir."/".$object);
                    }else{
                        unlink($dir."/".$object);
                    }
                }
            }
            reset($objects);
            rmdir($dir);
        }
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
    private function importPlateform($plateform)//, $beWorkspaceManager = true)
    {
        $plateformChilds = $plateform->item(0)->childNodes; // Platform childs = list of workspaces.
        for($i = 0; $i<$plateformChilds->length; $i++)
        {
            $item = $plateformChilds->item($i);
            //TODO CREER des constantes pour les fichier XML, ce sera plus propre que tout hardcode partout
            if($item->nodeName == 'workspace')
            {
                /**
                *   Check if a workspace with the given guid already exists.
                *   - if it doesn't exist then it will be created 
                *   - then proceed to the resources (no matter if we have to create the workspace previously)
                */
                
                //$workspace = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findByGuid($item->getAttribute('guid'));
                $workspace = $this->om->getRepository('ClarolineCoreBundle:Workspace\AbstractWorkspace')->findOneBy(array('guid' => $item->getAttribute('guid')));
                
                
                if(count($workspace) >= 1)
                {
                    echo 'Mon Workspace : '.$workspace->getGuid().'<br/>';
                    
                    /*  
                    *   When a workspace with the same guid is found, we can update him if it's required.
                    *   We need to check if : modification_date_offline > modification_date_online.
                    *       - If it is, the workspace can be update with the changes described in the XML.
                    *       - If it's not, it means that the 'online' version of the workspace is up-to-date.
                    */
                    
                    //echo 'I need to update my workspace!'.'<br/>';
                    $NodeWorkspace = $this->resourceNodeRepo->findOneBy(array('workspace' => $workspace));
                    // TODO Mettre à jour la date de modification et le nom du directory
                    echo 'Mon Workspace Node '.$NodeWorkspace->getName().'<br/>';
                    $node_modif_date = $NodeWorkspace->getModificationDate()->getTimestamp();
                    $modif_date = $item->getAttribute('modification_date');
                    
                    if($modif_date > $node_modif_date)
                    {
                       // The properties of the workspace has been changed and need to be update.
                    }
                    else
                    {
                       // The 'online' version of the workspace is up-to-date.
                    }
                }
                
                else
                {
                  //  echo 'This workspace : '.$item->getAttribute('code').' needs to be created!'.'<br/>';
                    $workspace_creator = $this->om->getRepository('ClarolineCoreBundle:User')->findOneBy(array('id' => $item->getAttribute('creator')));
                    echo 'Le creator de mon workspace : '.$workspace_creator->getFirstName().'<br/>';
                    $workspace = $this->createWorkspace($item, $workspace_creator);//, $beWorkspaceManager);
                    //$workspace = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findByGuid($item->getAttribute('guid'));
                }
                
                echo 'En route pour les ressources!'.'<br/>';
                $info = $this->importWorkspace($item->childNodes, $workspace);
                $this->syncInfoArray[] = $info;
            }
        }
    }
    
    /**
    * Visit all the 'resource' field in the 'workspace' inside the XML file and
    * either create or update the corresponding resources.
    */
    private function importWorkspace($resourceList, $workspace)
    {
        $wsInfo = new SyncInfo();
        $wsInfo->setWorkspace($workspace->getName().' ('.$workspace->getCode().')');
        
        for($i=0; $i<$resourceList->length; $i++)
        {     
            $res = $resourceList->item($i);
            if($res->nodeName == 'resource')
            {
                
                // Check, when a resource is visited, if it needs to be updated or created.               
                $node = $this->resourceNodeRepo->findOneBy(array('hashName' => $res->getAttribute('hashname_node')));
                
                if(count($node) >= 1)
                {
                    //echo 'Faut update!'.'<br/>';
                    $this->updateResource($res, $node, $workspace, $wsInfo);           
                }               
                else
                {
                    //echo 'Faut creer!'.'<br/>';
                    $this->createResource($res, $workspace);
                    $wsInfo->addToCreate($res->getAttribute('name'));
                } 
            }
            if($res->nodeName == 'forum')
            {

                // Check the content of a forum described in the XML file.
                $this->checkContent($res);
            }
        }
        
        return $wsInfo;
    }
    
    /*
    *   This method will update an already present resource in the database using the 
    *   informations given by the XML file.
    */   
    private function updateResource($resource, $node, $workspace, $wsInfo)
    {
        echo 'I need to update my resource!'.'<br/>';
        

        // Load the required informations from the XML file.

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
                    $this->resourceManager->rename($node, $resource->getAttribute('name'));
                    $wsInfo->addToUpdate($resource->getAttribute('name'));
                }
                break;
            case SyncConstant::FORUM :
                echo 'It s a forum!'.'<br/>';
                if($node_modif_date < $modif_date)
                {
                    // TODO Mettre à jour la date de modification et le nom du directory
                    // Only Rename?
                    $this->resourceManager->rename($node, $resource->getAttribute('name'));
                    $wsInfo->addToUpdate($resource->getAttribute('name'));
                }
                break;
            default :
                echo 'It s a file or a text!'.'<br/>';
                
                /*  
                *   When a resource with the same hashname is found, we can update him if it's required.
                *   First, we need to check if : modification_date_online <= user_synchronisation_date.
                *       - If it is, we know that the resource can be erased because it's now obsolete.
                *       - If it's not, we check if the modification_date of both resources (in database and in the xml) are
                *       different. 
                *           - If it is we need to create 'doublon' to preserve both resources
                *           - If it's not we suppose that there are the same (connexion lost, ...)
                */

                if($node_modif_date <= $user_sync[0]->getLastSynchronization()->getTimestamp())
                {
                    $this->resourceManager->delete($node);
                    $this->createResource($resource, $workspace);
                    $wsInfo->addToUpdate($resource->getAttribute('name'));
                }
                else 
                {        
                    if($node_modif_date != $modif_date)
                    {
                        // Doublon generation
                        $this->createDoublon($resource, $workspace, $node, true);
                        $wsInfo->addToDoublon($resource->getAttribute('name'));
                    }
                }
                break;
        }
    }
    
    /*
    *   This function create a resource based on the informations given in the XML file.
    */    
    private function createResource($resource, $workspace)
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
                rename($this->path.'data'.SyncConstant::ZIPFILEDIR.$file_hashname, '..'.SyncConstant::ZIPFILEDIR.$file_hashname);
                break;
                
            case SyncConstant::DIR : 
                $newResource = new Directory();            
                break;
                
            case SyncConstant::TEXT :
            
                $newResource = new Text();
                $revision = new Revision();
                $revision->setContent($this->extractCData($resource));
                $revision->setUser($this->user);
                $revision->setText($newResource);
                $this->om->persist($revision);                         
                break;  

            case SyncConstant::FORUM :
            
                $newResource = new Forum();
                break;
        }       
            
        $newResource->setName($resource->getAttribute('name'));
        $newResource->setMimeType($resource->getAttribute('mimetype'));
        
        $this->resourceManager->create($newResource, $type, $creator, $workspace, $parent_node, null, array(), $resource->getAttribute('hashname_node'));       
        $newResourceNode = $newResource->getResourceNode();
              
        // Update of the creation and modification date of the resource. 
        $this->changeDate($newResourceNode,$creation_date,$modification_date);

    }
    
    /*
    *   Create a doublon of a resource modified both online and offline.
    */ 
    private function createDoublon($resource, $workspace, $node)
    {
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
                
                // The file already exist inside the database. We have to modify the Hashname of the file already present.
                $old_file = $this->resourceManager->getResourceFromNode($node);
                $old_hashname = $old_file->getHashName();
                $extension_name = substr($old_hashname, strlen($old_hashname)-4, 4);
                $new_hashname = $this->ut->generateGuid().$extension_name;
                
                $this->om->startFlushSuite();
                $old_file->setHashName($new_hashname);
                $this->om->endFlushSuite(); 
                
                rename('..'.SyncConstant::ZIPFILEDIR.$old_hashname, '..'.SyncConstant::ZIPFILEDIR.$new_hashname);
                rename($this->path.'data'.SyncConstant::ZIPFILEDIR.$file_hashname, '..'.SyncConstant::ZIPFILEDIR.$file_hashname);
                break;
                
            case SyncConstant::DIR : 
                $newResource = new Directory();            
                break;
                
            case SyncConstant::TEXT :
            
                $newResource = new Text();
                $revision = new Revision();
                $revision->setContent($this->extractCData($resource));
                $revision->setUser($this->user);
                $revision->setText($newResource);
                $this->om->persist($revision);                         
                break;       
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
        $newResourceNode = $newResource->getResourceNode();

        // Update of the creation and modification date of the resource. 
        $this->changeDate($newResourceNode,$creation_date,$modification_date);
    }
    
    /*
    *   Create and return a new workspace detailed in the XML file.
    */     
    private function createWorkspace($workspace, $user)//, $manager=true)
    {   
        // Use the create method from WorkspaceManager.
        echo 'Je cree mon Workspace!'.'<br/>';
        $creation_date = new DateTime();
        $modification_date = new DateTime();
        $creator = $this->om->getRepository('ClarolineCoreBundle:User')->findOneBy(array('id' => $workspace->getAttribute('creator')));
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
        $config->setGuid($workspace->getAttribute('guid'));
        $user = $this->security->getToken()->getUser();
        
        $this->workspaceManager->create($config, $creator);   
        $this->tokenUpdater->update($this->security->getToken());
        //$route = $this->router->generate('claro_workspace_list');
               
        $my_ws = $this->om->getRepository('ClarolineCoreBundle:Workspace\AbstractWorkspace')->findOneBy(array('code' => $workspace->getAttribute('code')));
        $NodeWorkspace = $this->resourceNodeRepo->findOneBy(array('workspace' => $my_ws));                       
        
        $this->om->startFlushSuite();
        $creation_date->setTimestamp($workspace->getAttribute('creation_date'));
        $modification_date->setTimestamp($workspace->getAttribute('modification_date'));      
        
        $NodeWorkspace->setCreator($creator);
        $NodeWorkspace->setCreationDate($creation_date);
        $NodeWorkspace->setModificationDate($modification_date);
        $NodeWorkspace->setNodeHashName($workspace->getAttribute('hashname_node'));
        $this->om->endFlushSuite();  
        
        return $my_ws;

    }
    
    /*
    *   Check the content of a forum described in the XML file and 
    *   either create or update this content.
    */
    private function checkContent($content)
    {   
        $content_type = $content->getAttribute('class');
        switch($content_type)
        {
            case SyncConstant::CATE :
                $category = $this->categoryRepo->findOneBy(array('hashName' => $content->getAttribute('hashname')));
                if($category == null)
                {
                    $this->createCategory($content);
                }
                else
                {
                    $this->updateCategory($content, $category);
                }
                
                // Update of the Dates
                // $category = $this->categoryRepo->findOneBy(array('hashName' => $content->getAttribute('hashname')));
                // $this->updateDate($category, $content);
                break;
                
            case SyncConstant::SUB :
                $subject = $this->subjectRepo->findOneBy(array('hashName' => $content->getAttribute('hashname')));
                if($subject == null)
                {
                    $this->createSubject($content);
                }
                else
                {
                    $this->updateSubject($content, $subject);
                }
                
                // Update of the Dates
                // $subject = $this->subjectRepo->findOneBy(array('hashName' => $content->getAttribute('hashname')));
                // $this->updateDate($subject, $content);
                break;
                
            case SyncConstant::MSG :
                $message = $this->messageRepo->findOneBy(array('hashName' => $content->getAttribute('hashname')));
                if($message == null)
                {
                    $this->createMessage($content);
                }
                else
                {
                    $this->updateMessage($content, $message);
                }               
                        
                // Update of the Dates
                // $message = $this->messageRepo->findOneBy(array('hashName' => $content->getAttribute('hashname')));
                // $this->updateDate($message, $content);                              
                break;
        }
        
    }

    /*
    *   Create a new Forum Category based on the XML file in the Archive.
    */
    private function createCategory($category)
    {
        echo 'Category created'.'<br/>';
        
        $node_forum = $this->resourceNodeRepo->findOneBy(array('hashName' => $category->getAttribute('forum_node')));
        $forum = $this->resourceManager->getResourceFromNode($node_forum);
        
        $category_name = $category->getAttribute('name');
        
        $this->forumManager->createCategory($forum, $category_name, true, $category->getAttribute('hashname'));
    }
    
    /*
    *   Update a Forum Category based on the XML file in the Archive.
    */
    private function updateCategory($xmlCategory, $category)
    {
        $xmlName = $xmlCategory->getAttribute('name');
        $dbName = $category->getName();
        $xmlModificationDate = $xmlCategory->getAttribute('update_date');
        $dbModificationDate = $category->getModificationDate()->getTimestamp();
        if($xmlName != $dbName)
        {
            if($xmlModificationDate > $dbModificationDate)
            {
                $this->forumManager->editCategory($category, $dbName, $xmlName);
            }
        }
        
        echo 'Category already in DB!'.'<br/>';
    }
    
    /*
    *   Create a new Forum Subject based on the XML file in the Archive.
    */
    private function createSubject($subject)
    {
        echo 'Subject created'.'<br/>';
        
        $category = $this->categoryRepo->findOneBy(array('hashName' => $subject->getAttribute('category')));
        $creator = $this->om->getRepository('ClarolineCoreBundle:User')->findOneBy(array('id' => $subject->getAttribute('creator_id')));
        $sub = new Subject();
        $sub->setTitle($subject->getAttribute('name'));
        $sub->setCategory($category);
        $sub->setCreator($creator);
        $sub->setIsSticked($subject->getAttribute('sticked'));
        
        $this->forumManager->createSubject($sub, $subject->getAttribute('hashname'));
    }
    
    /*
    *   Update a Forum Subject based on the XML file in the Archive.
    */
    private function updateSubject($xmlSubject, $subject)
    {
        $xmlName = $xmlSubject->getAttribute('name');
        $dbName = $subject->getTitle();
        $xmlModificationDate = $xmlSubject->getAttribute('update_date');
        $dbModificationDate = $subject->getUpdate()->getTimestamp();
        if($xmlName != $dbName)
        {    
            if($xmlModificationDate > $dbModificationDate)
            {
                $this->forumManager->editSubject($subject, $dbName, $xmlName);
                $subject->setIsSticked($xmlSubject->getAttribute('sticked'));
            }
        }
        
        echo 'Subject already in DB!'.'<br/>';
    }
    
    /*
    *   Create a new Forum message based on the XML file in the Archive.
    */
    private function createMessage($message)
    {
        $creation_date = new DateTime();
        $creation_date->setTimestamp($message->getAttribute('creation_date'));
        // Message Creation      
        echo 'Message created'.'<br/>';
        
        $subject = $this->subjectRepo->findOneBy(array('hashName' => $message->getAttribute('subject')));
        $creator = $this->om->getRepository('ClarolineCoreBundle:User')->findOneBy(array('id' => $message->getAttribute('creator_id')));
        $content = $this->extractCData($message);
        $msg = new Message();
        $msg->setContent($content.'<br/>'.'<strong>Message created during synchronisation at : '.$creation_date->format('d/m/Y H:i:s').'</strong>');
        $msg->setSubject($subject);
        $msg->setCreator($creator);
        
        $this->forumManager->createMessage($msg, $message->getAttribute('hashname'));     
        
    }
    
    /*
    *   Update a Forum message based on the XML file in the Archive.
    */
    private function updateMessage($xmlMessage, $message)
    {
        $xmlContent = $this->extractCData($xmlMessage);
        $dbContent = $message->getContent();
        $xmlModificationDate = $xmlMessage->getAttribute('update_date');
        $dbModificationDate = $message->getUpdate()->getTimestamp();
        if($xmlContent != $dbContent)
        {
            if($xmlModificationDate > $dbModificationDate)
            {
                $this->forumManager->editMessage($message, $dbContent, $xmlContent);
            }
        }
        
        echo 'Message already in DB!'.'<br/>';
            
    }
    
    /*
    *   Update the creation and modification/update dates of a category, subject or message.
    */
    private function updateDate($forumContent, $content)
    {
        $creation_date = new DateTime();
        $creation_date->setTimestamp($content->getAttribute('creation_date'));
        $modification_date = new DateTime();
        $modification_date->setTimestamp($content->getAttribute('update_date'));
        $this->om->startFlushSuite();
        $forumContent->setCreationDate($creation_date);
        if($content->getAttribute('class') == SyncConstant::CATE)
        {
            $forumContent->setModificationDate($modification_date);
        }
        else
        {
            $forumContent->setUpdate($modification_date);
        }
        $this->om->persist($forumContent);
        $this->om->endFlushSuite();  
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
    
    private function extractCData($data)
    {      
        foreach($data->childNodes as $child)
        {
            if($child->nodeName == 'content')
            {
                foreach($child->childNodes as $contentsection)
                {
                    if($contentsection->nodeType == XML_CDATA_SECTION_NODE)
                    {
                        // $msg->setContent($child->textContent.'<br/>'.'<strong>Message created during synchronisation at : '.$creation_date->format('d/m/Y H:i:s').'</strong>');  
                        return $child->textContent;
                    }
                }
            } 
        }
    }
    
}