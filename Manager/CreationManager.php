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
use Claroline\CoreBundle\Entity\Resource\ResourceType;
use Claroline\CoreBundle\Entity\Resource\ResourceNode;
use Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace;
use Claroline\CoreBundle\Library\Utilities\ClaroUtilities;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Manager\WorkspaceManager;
use Claroline\CoreBundle\Pager\PagerFactory;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\CoreBundle\Repository\WorkspaceRepository;
use Claroline\OfflineBundle\Entity\UserSynchronized;
use Claroline\OfflineBundle\SyncConstant;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\DependencyInjection\ContainerInterface;
//use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\TranslatorInterface;
use \ZipArchive;
use \DOMDocument;
use \DOMElement;
use \DateTime;

/**
 * @DI\Service("claroline.manager.creation_manager")
 */
class CreationManager
{
    private $om;
    private $pagerFactory;
    private $translator;
    private $userSynchronizedRepo;
    private $resourceNodeRepo;
    private $revisionRepo;
    private $subjectRepo;
    private $messageRepo;
    private $forumRepo;
    private $categoryRepo;
    private $resourceManager;
    private $workspaceRepo;
    private $roleRepo;
    private $ut;

    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "pagerFactory"   = @DI\Inject("claroline.pager.pager_factory"),
     *     "translator"     = @DI\Inject("translator"),
     *     "resourceManager"= @DI\Inject("claroline.manager.resource_manager"),
     *     "ut"            = @DI\Inject("claroline.utilities.misc")
     * })
     */
    public function __construct(
        ObjectManager $om,
        PagerFactory $pagerFactory,
        TranslatorInterface $translator,
        ResourceManager $resourceManager,
        ClaroUtilities $ut
    )
    {
        $this->om = $om;
        $this->pagerFactory = $pagerFactory;
        $this->userSynchronizedRepo = $om->getRepository('ClarolineOfflineBundle:UserSynchronized');
        $this->resourceNodeRepo = $om->getRepository('ClarolineCoreBundle:Resource\ResourceNode');
        $this->revisionRepo = $om->getRepository('ClarolineCoreBundle:Resource\Revision');
        $this->subjectRepo = $om->getRepository('ClarolineForumBundle:Subject');
        $this->messageRepo = $om->getRepository('ClarolineForumBundle:Message');
        $this->forumRepo = $om->getRepository('ClarolineForumBundle:Forum');
        $this->categoryRepo = $om->getRepository('ClarolineForumBundle:Category');
        $this->workspaceRepo = $om->getRepository('ClarolineCoreBundle:Workspace\AbstractWorkspace');
        $this->roleRepo = $om->getRepository('ClarolineCoreBundle:Role');
        $this->translator = $translator;
        $this->resourceManager = $resourceManager;
        $this->ut = $ut;
    }
    
    /**
     * Create a the archive based on the user     
     * Warning : If the archive file created is empty, it will not write zip file on disk !
     *
     * @param \Claroline\CoreBundle\Entity\User $user
     *
     */
     public function createSyncZip(User $user, $date)
    {
        ini_set('max_execution_time', 0);
        $typeList = array('directory', 'file', 'text', 'claroline_forum'); //TODO ! PAS OPTIMAL !

        $archive = new ZipArchive();        
        $domManifest = new DOMDocument('1.0', "UTF-8");
        $domManifest->formatOutput = true;
        $manifestName = SyncConstant::MANIFEST.'_'.$user->getUsername().'.xml';
        
        // Manifest section
        $sectManifest = $domManifest->createElement('manifest');    
        $domManifest->appendChild($sectManifest);
        
        //Description section
        $this->writeManifestDescription($domManifest, $sectManifest, $user, $date);           

        $dir = SyncConstant::SYNCHRO_DOWN_DIR.$user->getId();
        // Ca ne fonctionne pas chez moi
        if(!is_dir($dir)){
            echo $dir;
            mkdir($dir, 0777);
        }
        $hashname_zip = $this->ut->generateGuid(); 
        $fileName = $dir.'/sync_'.$hashname_zip.'.zip';
        
        $typeArray = $this->buildTypeArray($typeList);
        $userWS = $this->workspaceRepo->findByUser($user);
        
        if($archive->open($fileName, ZipArchive::CREATE) === true)
        {   
           $this->fillSyncZip($userWS, $domManifest, $sectManifest, $typeArray, $user, $archive, $date);
        }
        else
        {           
            throw new \Exception('Impossible to open the zip file');
        }
        
        $domManifest->save($manifestName);
        $archive->addFile($manifestName);
        $archivePath = $archive->filename;
        $archive->close();
        // Erase the manifest from the current folder.
        // unlink($manifestName);
        
        return $archivePath;
    }


    public function writeWorspaceList(User $user)
    {
        $workspaces = $this->workspaceRepo->findWorkspacesWithSelfRegistration($user);
        $dir = SyncConstant::SYNCHRO_DOWN_DIR.$user->getId().'/';
        if(!is_dir($dir)){
            mkdir($dir);
        }
        $fileName = $dir.'all_workspaces.xml';
        $allWorkspaces = fopen($fileName, "w+");
        fputs($allWorkspaces, "<workspace_list>");

        foreach($workspaces as $workspace)
        {
            $this->addWorkspaceToManifest_($allWorkspaces, $workspace);
            fputs($allWorkspaces, '
        </workspace>');
        }

        fputs($allWorkspaces, "
</workspace_list>");
        fclose($allWorkspaces);
        //echo "filename : ".$fileName.'<br/>';
        return $fileName;
    }

    /*
    *   Create an array with the different ResourceType that need to be offline.
    */
    private function buildTypeArray(array $typeList)
    {
        $typeArrayTmp = array();
        foreach($typeList as $element)
        {
            $typeArrayTmp[] = $this->resourceManager->getResourceTypeByName($element);
        }
        //echo count($typeArrayTmp);
        return $typeArrayTmp;
    }
    
    /*
    *   Fill the Zip with the file required for the synchronisation.
    *   Also, create a manifest containing all the changes done.
    */
    private function fillSyncZip($userWS, $domManifest, $sectManifest, $typeArray, $user, $archive, $date)
    {
        foreach($userWS as $element)
        {
            $domWorkspace = $this->addWorkspaceToManifest($domManifest, $sectManifest, $element, $user);
            foreach($typeArray as $resType)
            {
                $ressourcesToSync = array();
                $forum_content = array();
                //$em_res = $this->getDoctrine()->getManager();
                $userRes = $this->resourceNodeRepo->findByWorkspaceAndResourceType($element, $resType);
                if(count($userRes) >= 1)
                {
                    
                    $path = ''; // USELESS?
                    $ressourcesToSync = $this->checkObsolete($userRes, $user, $date);  // Remove all the resources not modified.
                    //echo get_class($ressourcesToSync);//Ajouter le resultat dans l'archive Zip

                    $this->addResourcesToArchive($ressourcesToSync, $archive, $domManifest, $domWorkspace, $user, $path);
                    //echo "<br/>".count($ressourcesToSync)."<br/>";
                    
                    if($resType->getId() == SyncConstant::FORUM)
                    {
                        /*
                        *   Check, if the resource is a forum, is there are new messages, subjects or category created offline.
                        */
                        $forum_content = $this->checkNewContent($userRes, $user, $date); 
                        echo count($forum_content);
                        $this->addForumToArchive($domManifest, $domWorkspace, $forum_content);
                    }
                }
            }
        }
    }
    

    /*
    *   Check all the messages, subjects and categories of the forums
    *   and return the ones that have been created.
    */
    private function checkNewContent(array $userRes, User $user, $date_sync)
    {
        // $date_tmp = $this->userSynchronizedRepo->findUserSynchronized($user);
        // $date_sync = $date_tmp[0]->getLastSynchronization()->getTimestamp();
        
        $elem_to_sync = array();
        foreach($userRes as $node_forum)
        {
            //echo 'Un forum'.'<br/>';
            $current_forum = $this->forumRepo->findOneBy(array('resourceNode' => $node_forum));
            $categories = $this->categoryRepo->findBy(array('forum' => $current_forum));
            $elem_to_sync = $this->checkCategory($categories, $elem_to_sync, $date_sync);           
        }
        
        return $elem_to_sync;
    
    }
    

    /*
    *   Check all categories of a list and see if they are new or updated.
    */
    private function checkCategory($categories, $elem_to_sync, $date_sync)
    {
        foreach($categories as $category)
        {
            /*
            *   TODO :  Profiter de ce passage pour voir si la category a ete mise a jour
            *           ou si elle est nouvelle. 
            */
            
            if($category->getModificationDate()->getTimestamp() > $date_sync)
            {
                echo 'Une categorie'.'<br/>';
                 $elem_to_sync[] = $category;
            }
            $subjects = $this->subjectRepo->findBy(array('category' => $category));
            $elem_to_sync = $this->checkSubject($subjects, $elem_to_sync, $date_sync);
        }
        
        return $elem_to_sync;

    }


    /*
    *   Check all subjects of a list and see if they are new or updated.
    */
    private function checkSubject($subjects, $elem_to_sync, $date_sync)
    {
        foreach($subjects as $subject)
        {
            /*
            *   TODO :  Profiter de ce passage pour voir si le sujet a ete mis a jour
            *           ou si il est nouveau. 
            */
            if($subject->getUpdate()->getTimestamp() > $date_sync)
            {
                echo 'Un sujet'.'<br/>';
                 $elem_to_sync[] = $subject;
            }
            
            $messages = $this->messageRepo->findBySubject($subject);
            $elem_to_sync = $this->checkMessage($messages, $elem_to_sync, $date_sync);
        }
        
        return $elem_to_sync;

    }


    /*
    *   Check all message of a list and see if they are new or updated.
    */
    private function checkMessage($messages, $elem_to_sync, $date_sync)
    {
        foreach($messages as $message)
        {
            /*
            *   TODO :  Gerer les messages update.
            */
            echo 'Un message'.'<br/>';
            if($message->getUpdate()->getTimestamp() > $date_sync)
            {
                echo 'Le message est nouveau'.'<br/>';
                $elem_to_sync[] = $message;
            }
        }
        
        return $elem_to_sync;
    }
    

    /*
    *   Filter all the resources based on the user's last synchronization and
    *   check which one need to be sent.
    */
    private function checkObsolete(array $userRes, User $user, $date_user)
    {
        //$em = $this->getDoctrine()->getManager();
        // $dateSync = $this->userSynchronizedRepo->findUserSynchronized($user);
        // $user_tmp = $dateSync[0]->getLastSynchronization();
        // $date_user = $user_tmp->getTimestamp();
        $new_res = array();
        
        foreach($userRes as $resource)
        {
            //echo 'La date de mon cours :';
            //echo $resource->getModificationDate()->format('Y-m-d') . "<br/>";
            $res_tmp = $resource->getModificationDate();
            $date_res = $res_tmp->getTimestamp();
            $interval = $date_res - $date_user;
            //echo $resource->getName() . "<br/>";
            //echo $interval . "<br/>";
            
            
            if($interval > 0)
            {
                //echo 'Name file : ';
                //echo $resource->getName() . "<br/>";
                //echo 'This file has been modified' . "<br/>";
                $new_res[] = $resource;
            }
            
        }
        //echo 'Ma date à moi :';
        //echo $dateSync[0]->getLastSynchronization()->format('Y-m-d') . "<br/>";
        return $new_res;
        
    }
    

    /*
    *   Add the content of the forum in the Archive.
    */
    private function addForumToArchive($domManifest, $domWorkspace, $forum_content)
    {
        foreach($forum_content as $element)
        {
            
            //$class_name = getQualifiedClassName($element);
            // echo 'Bonjour'.'<br/>';
            //echo get_class($element).'<br/>';
            //echo get_class($element).'<br/>';
            $class_name = ''.get_class($element);
            
            $this->addContentToManifest($domManifest, $domWorkspace, $element);
            
            // switch($class_name)
            // {
                // case SyncConstant::CATE :
                    // $this->addCategoryToManifest($domManifest, $domWorkspace, $element);
                    // break;
                // case SyncConstant::SUB :
                    // $this->addSubjectToManifest($domManifest, $domWorkspace, $element);
                    // break;
                // case SyncConstant::MSG :
                    // $this->addMessageToManifest($domManifest, $domWorkspace, $element);
                    // break;
            // }

        }
    }
    

    /**
     * Create a the archive based on the user     
     * Attention, if the archive file created is empty, it will not write zip file on disk !
     *
     */
    private function addResourcesToArchive(array $resToAdd, ZipArchive $archive, $domManifest, $domWorkspace, $user, $path)
    {
        foreach($resToAdd as $element)
        {
            $this->addResourceToManifest($domManifest, $domWorkspace, $element);
            $this->addResourceToZip($archive, $element, $user, $archive, $path);
        }
    }
    

    /*
    *   Add the ressource inside a file in the Archive file.
    *   
    *   03/04/2014 : Interesting ONLY for the file at this time.
    */
    private function addResourceToZip(ZipArchive $archive, $resToAdd, $user, $archive, $path)
    {
        switch($resToAdd->getResourceType()->getId())
        {
            case SyncConstant::FILE :
                $my_res = $this->resourceManager->getResourceFromNode($resToAdd);
                //echo 'Le fichier : '. $resToAdd->getName() . "<br/>";
                //echo 'Add to the Archive' . "<br/>";
                //$path = $path.$resToAdd->getWorkspace()->getId();
                $archive->addFile('..'.SyncConstant::ZIPFILEDIR.$my_res->getHashName(), 'data'.$path.SyncConstant::ZIPFILEDIR.$my_res->getHashName());
                //$archive->renameName('../files/'.$my_res->getHashName(), 'data/'.$workspace_id.'/files/'.$my_res->getHashName());
                break;
            case SyncConstant::DIR :
                // TOREMOVE SI BUG! ATTENTION LES WORKSPACES SONT AUSSI DES DIRECTORY GARE AU DOUBLE CHECK
                //$my_res = $this->resourceManager->getResourceFromNode($resToAdd);
                //$this->resourceFromDir($resToAdd, $user, $archive, $manifest, $path);
                break;
            case SyncConstant::TEXT :
                //echo 'Le fichier : '. $resToAdd->getName() . "<br/>";
                //echo 'Work In Progress'. "<br/>";
                break;
        }
    }


    /************************************************************
    *   Here figure all methods used to manipulate the xml file. *
    *************************************************************/

    /*
    *   Add a specific resource to the Manifest. 
    *   The informations written in the Manifest will depend of resource's type.
    */
    private function addResourceToManifest($domManifest, $domWorkspace, $resToAdd)
    {
        $typeNode = $resToAdd->getResourceType()->getId();
        $creation_time = $resToAdd->getCreationDate()->getTimestamp();  
        $modification_time = $resToAdd->getModificationDate()->getTimestamp(); 
        
        if(!($resToAdd->getParent() == NULL & $typeNode == SyncConstant::DIR))
        {   
            $domRes = $domManifest->createElement('resource');
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
            // $creator->value = $resToAdd->getCreator()->getId(); 
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
            
            
            switch($typeNode)
            {                
                case SyncConstant::DIR :               
                    // Check if the directory is not a Workspace
                    if($resToAdd->getParent() != NULL)
                    {                       
                        // Futur resolution goes here
                    }
                    break;
                case SyncConstant::FILE :
                    $my_res = $this->resourceManager->getResourceFromNode($resToAdd);           
                    $size = $domManifest->createAttribute('size');
                    $size->value = $my_res->getSize(); 
                    $domRes->appendChild($size);
                    $hashname = $domManifest->createAttribute('hashname');
                    $hashname->value = $my_res->getHashName(); 
                    $domRes->appendChild($hashname);
                    break;
                case SyncConstant::TEXT :
                    $my_res = $this->resourceManager->getResourceFromNode($resToAdd);  
                    $revision = $this->revisionRepo->findOneBy(array('text' => $my_res));
                                    
                    $version = $domManifest->createAttribute('version');
                    $version->value = $my_res->getVersion(); 
                    $domRes->appendChild($version);
                    
                    $cdata = $domManifest->createCDATASection($revision->getContent());
                    $domRes->appendChild($cdata);
                    
                    break;
                case SyncConstant::FORUM :                
                    // On pourrait gerer le parcours des forums a partir d'ici plutot qu'au tout debut
                    break;
            }
        }
    }


    /*
    *   Add a specific Category, Subject or Message to the Manifest.
    */
    private function addContentToManifest($domManifest, $domWorkspace, $content)
    {
        
        $creation_time = $content->getCreationDate()->getTimestamp();
        $content_type = get_class($content);
      
        $domRes = $domManifest->createElement('forum');
        $domWorkspace->appendChild($domRes);
        
        $class = $domManifest->createAttribute('class');
        $class->value = $content_type;   
        $domRes->appendChild($class);
        $hashname = $domManifest->createAttribute('hashname');
        $hashname->value = $content->getHashName();   
        $domRes->appendChild($hashname);
        $creation_date = $domManifest->createAttribute('creation_date');
        $creation_date->value = $creation_time ;   
        $domRes->appendChild($creation_date);

        
        switch($content_type)
        {
            case SyncConstant::CATE :
                echo 'Edition du manifeste pour ajouter une category'.'<br/>';
                $modification_time = $content->getModificationDate()->getTimestamp();
                $node_forum = $content->getForum()->getResourceNode();
        
                $update_date = $domManifest->createAttribute('update_date');
                $update_date->value = $modification_time;   
                $domRes->appendChild($update_date);
                $forum_node = $domManifest->createAttribute('forum_node');
                $forum_node->value = $node_forum->getNodeHashName();   
                $domRes->appendChild($forum_node);
                $name = $domManifest->createAttribute('name');
                $name->value = $content->getName();   
                $domRes->appendChild($name);

                break;
            case SyncConstant::SUB :
                echo 'Edition du manifeste pour ajouter un sujet'.'<br/>';
                $modification_time = $content->getUpdate()->getTimestamp();
                $category_hash = $content->getCategory()->getHashName();
                
                $update_date = $domManifest->createAttribute('update_date');
                $update_date->value = $modification_time;   
                $domRes->appendChild($update_date);
                $category = $domManifest->createAttribute('category');
                $category->value = $category_hash;   
                $domRes->appendChild($category);
                $title = $domManifest->createAttribute('title');
                $title->value = $content->getTitle();   
                $domRes->appendChild($title);
                $creator_id = $domManifest->createAttribute('creator_id');
                $creator_id->value = $content->getCreator()->getExchangeToken();   
                $domRes->appendChild($creator_id);
                $sticked = $domManifest->createAttribute('sticked');
                $sticked->value = $content->isSticked();   
                $domRes->appendChild($sticked);

                
                break;
            case SyncConstant::MSG :
                $modification_time = $content->getUpdate()->getTimestamp();
                $subject_hash = $content->getSubject()->getHashName();
                
                $update_date = $domManifest->createAttribute('update_date');
                $update_date->value = $modification_time;   
                $domRes->appendChild($update_date);
                $subject = $domManifest->createAttribute('subject');
                $subject->value = $subject_hash;   
                $domRes->appendChild($subject);
                $creator_id = $domManifest->createAttribute('creator_id');
                $creator_id->value = $content->getCreator()->getExchangeToken();   
                $domRes->appendChild($creator_id);
                $cdata = $domManifest->createCDATASection($content->getContent());
                $domRes->appendChild($cdata);
                break;
        }
    
    }


    /*
    *   Add a specific Subject in the Manifest.
    */
    private function addSubjectToManifest($manifest, $content)
    {
        echo 'Edition du manifeste pour ajouter un sujet'.'<br/>';
        $creation_time = $content->getCreationDate()->getTimestamp();
        $update_time = $content->getUpdate()->getTimestamp();
        $category = $content->getCategory()->getHashName();
    
                fputs($manifest, '
                    <forum class="'.get_class($content).'"
                    name="'.$content->getTitle().'"
                    hashname="'.$content->getHashName().'"
                    category="'.$category.'"  
                    creator_id="'.$content->getCreator()->getExchangeToken().'"
                    creation_date="'.$creation_time.'"
                    update_date="'.$update_time.'"
                    sticked="'.$content->isSticked().'">
                    </forum>
                    ');
    }
    

    /*
    *   Add a specific Message in the Manifest.
    */
    private function addMessageToManifest($manifest, $content)
    {
        //echo 'Edition du manifeste pour ajouter un message'.'<br/>';
        $creation_time = $content->getCreationDate()->getTimestamp();
        $update_time = $content->getUpdate()->getTimestamp();
        $subject = $content->getSubject()->getHashName();
    
                fputs($manifest, '
                    <forum class="'.get_class($content).'"
                    id="'.$content->getId().'"
                    subject="'.$subject.'"  
                    hashname="'.$content->getHashName().'"
                    creator_id="'.$content->getCreator()->getExchangeToken().'"
                    creation_date="'.$creation_time.'"
                    update_date="'.$update_time.'">
                        <content><![CDATA['.$content->getContent().']]></content>
                    </forum>
                    ');
    }
    

    /*
    *   Add informations of a specific workspace in the manifest.
    */
    private function addWorkspaceToManifest_($manifest, $workspace)
    {
        $my_res_node = $this->userSynchronizedRepo->findResourceNodeByWorkspace($workspace);
        //echo 'Ma creation_time : '.$my_res_node[0]->getCreationDate()->format('Y-m-d H:i:s').'<br/>';
        //echo 'Ma modification_time : '.$my_res_node[0]->getModificationDate()->format('Y-m-d H:i:s').'<br/>';
        $creation_time = $my_res_node[0]->getCreationDate()->getTimestamp();  
        $modification_time = $my_res_node[0]->getModificationDate()->getTimestamp(); 
        
        fputs($manifest,  '
        <workspace id="'.$workspace->getId().'"
        type="'.get_class($workspace).'"
        creator="'.$workspace->getCreator()->getExchangeToken().'"
        name="'.$workspace->getName().'"
        code="'.$workspace->getCode().'"
        displayable="'.$workspace->isDisplayable().'"
        selfregistration="'.$workspace->getSelfRegistration().'"
        selfunregistration="'.$workspace->getSelfUnregistration().'"
        guid="'.$workspace->getGuid().'"
        hashname_node="'.$my_res_node[0]->getNodeHashName().'"
        creation_date="'.$creation_time.'"
        modification_date="'.$modification_time.'">
        ');
    }

    /*
    *   Add informations of a specific workspace in the manifest.
    */
    private function addWorkspaceToManifest($domManifest, $sectManifest, $workspace, $user)
    {
        //Risque d'être un tableau.
        $my_role = $this->roleRepo->findByUserAndWorkspace($user, $workspace);
        
        $my_res_node = $this->userSynchronizedRepo->findResourceNodeByWorkspace($workspace);
        $creation_time = $my_res_node[0]->getCreationDate()->getTimestamp();  
        $modification_time = $my_res_node[0]->getModificationDate()->getTimestamp(); 
              
        $domWorkspace = $domManifest->createElement('workspace');
        $sectManifest->appendChild($domWorkspace);
        
        $type = $domManifest->createAttribute('type');
        $type->value = get_class($workspace);
        $domWorkspace->appendChild($type);        
        $creator = $domManifest->createAttribute('creator');
        $creator->value = $workspace->getCreator()->getExchangeToken();
        $domWorkspace->appendChild($creator);
        $role = $domManifest->createAttribute('role');
        $role->value = $my_role->getName();
        $domWorkspace->appenChild($type);
        $name = $domManifest->createAttribute('name');
        $name->value = $workspace->getName();
        $domWorkspace->appendChild($name);     
        $code = $domManifest->createAttribute('code');
        $code->value = $workspace->getCode();
        $domWorkspace->appendChild($code);    
        $displayable = $domManifest->createAttribute('displayable');
        $displayable->value = $workspace->isDisplayable();
        $domWorkspace->appendChild($displayable);        
        $selfregistration = $domManifest->createAttribute('selfregistration');
        $selfregistration->value = $workspace->getSelfRegistration();
        $domWorkspace->appendChild($selfregistration);       
        $selfunregistration = $domManifest->createAttribute('selfunregistration');
        $selfunregistration->value = $workspace->getSelfUnregistration();
        $domWorkspace->appendChild($selfunregistration);        
        $guid = $domManifest->createAttribute('guid');
        $guid->value = $workspace->getGuid();
        $domWorkspace->appendChild($guid);        
        $hashname_node = $domManifest->createAttribute('hashname_node');
        $hashname_node->value = $my_res_node[0]->getNodeHashName();
        $domWorkspace->appendChild($hashname_node);       
        $creation_date = $domManifest->createAttribute('creation_date');
        $creation_date->value = $creation_time;
        $domWorkspace->appendChild($creation_date);       
        $modification_date = $domManifest->createAttribute('modification_date');
        $modification_date->value = $modification_time;
        $domWorkspace->appendChild($modification_date);
        
        return $domWorkspace;
    }    


    /*
    *   Create the description of the manifest.
    */
    private function writeManifestDescription($domManifest, $sectManifest, User $user, $date)
    {
        // $dateSync = $this->userSynchronizedRepo->findUserSynchronized($user);
        // $user_tmp = $dateSync[0]->getLastSynchronization(); 
        // $sync_timestamp = $user_tmp->getTimestamp();
        
        $sectDescription = $domManifest->createElement('description');
        $sectManifest->appendChild($sectDescription);
        
        $descCreation = $domManifest->createAttribute('creation_date');
        $descCreation->value = time();   
        $sectDescription->appendChild($descCreation);
        
        // $userSync = $this->userSynchronizedRepo->findUserSynchronized($user);
        $descReference = $domManifest->createAttribute('synchronization_date');
        // $descReference->value = $userSync[0]->getLastSynchronization()->getTimestamp();   
        $descReference->value = $date;
        $sectDescription->appendChild($descReference);
        
        $descPseudo = $domManifest->createAttribute('username');
        $descPseudo->value = $user->getUsername();   
        $sectDescription->appendChild($descPseudo);
        
        $descMail = $domManifest->createAttribute('user_mail');
        $descMail->value = $user->getMail();   
        $sectDescription->appendChild($descMail);
    }
}
