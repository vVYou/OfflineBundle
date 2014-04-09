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
use Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Entity\Resource\ResourceType;
use Claroline\CoreBundle\Entity\Resource\ResourceNode;
use Claroline\OfflineBundle\SyncConstant;
use Claroline\CoreBundle\Library\Utilities\ClaroUtilities;
use \ZipArchive;
use \DateTime;

/**
 * @DI\Service("claroline.manager.synchronize_manager")
 */
class CreationManager
{
    private $om;
    private $pagerFactory;
    private $translator;
    private $userSynchronizedRepo;
    private $revisionRepo;
    private $subjectRepo;
    private $messageRepo;
    private $forumRepo;
    private $categoryRepo;
    private $resourceManager;
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
        $this->revisionRepo = $om->getRepository('ClarolineCoreBundle:Resource\Revision');
        $this->subjectRepo = $om->getRepository('ClarolineForumBundle:Subject');
        $this->messageRepo = $om->getRepository('ClarolineForumBundle:Message');
        $this->forumRepo = $om->getRepository('ClarolineForumBundle:Forum');
        $this->categoryRepo = $om->getRepository('ClarolineForumBundle:Category');
        $this->translator = $translator;
        $this->resourceManager = $resourceManager;
        $this->ut = $ut;
    }
    
    /**
     * Create a the archive based on the user     
     * Attention, if the archive file created is empty, it will not write zip file on disk !
     *
     * @param \Claroline\CoreBundle\Entity\User $user
     *
     */
    
     public function createSyncZip(User $user)
    {
        $archive = new ZipArchive(); 
        $syncTime = time();
        
        $userRes = array();
        $typeList = array('file', 'directory', 'text', 'claroline_forum'); //TODO ! PAS OPTIMAL !
        $typeArray = $this->buildTypeArray($typeList);
        $userWS = $this->om->getRepository('ClarolineCoreBundle:Workspace\AbstractWorkspace')->findByUser($user);
        
        $hashname_zip = $this->ut->generateGuid(); 
        
        $manifestName = SyncConstant::MANIFEST.'_'.$user->getId().'.xml';
        $manifest = fopen($manifestName, 'w');
        fputs($manifest,'<manifest>');
        $this->writeManifestDescription($manifest, $user, $syncTime);
        //echo get_resource_type($manifest).'<br/>';
 
        $fileName = SyncConstant::SYNCHRO_DOWN_DIR.$user->getId().'/sync_'.$hashname_zip.'.zip';
        if($archive->open($fileName, ZipArchive::CREATE) === true)
        {
        //echo 'THIS Is THE ARCHIVE NAME : '.$archive->filename ;
        
        fputs($manifest,'
    <plateform>');
    
           $this->fillSyncZip($userWS, $manifest, $typeArray, $user, $archive);
        fputs($manifest,'
    </plateform>');
           
            /*return array(
                'user_courses' => $userWS,
                'user_res' => $userRes
            );*/
        }
        else
        {
            //echo 'Impossible to open the zip file';
            throw new \Exception('Impossible to open the zip file');
        }
        fputs($manifest,'
</manifest>');
        fclose($manifest);
        
        $archive->addFile($manifestName);
        $archivePath = $archive->filename;
        $archive->close();
        // Erase the manifest from the current folder.
        //unlink($manifestName);
        
        return $archivePath;
    }
    
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
    
    private function fillSyncZip($userWS, $manifest, $typeArray, $user, $archive)
    {
        foreach($userWS as $element)
        {
            $this->addWorkspaceToManifest($manifest, $element);
            foreach($typeArray as $resType)
            {
                $ressourcesToSync = array();
                $forum_content = array();
                //$em_res = $this->getDoctrine()->getManager();
                $userRes = $this->om->getRepository('ClarolineCoreBundle:Resource\ResourceNode')->findByWorkspaceAndResourceType($element, $resType);
                if(count($userRes) >= 1)
                {
                    
                    $path = ''; // USELESS?
                    $ressourcesToSync = $this->checkObsolete($userRes, $user);  // Remove all the resources not modified.
                    //echo get_class($ressourcesToSync);//Ajouter le resultat dans l'archive Zip

                    $this->addResourcesToArchive($ressourcesToSync, $archive, $manifest, $user, $path);
                    //echo "<br/>".count($ressourcesToSync)."<br/>";
                    
                    if($resType->getId() == SyncConstant::FORUM)
                    {
                        /*
                        *   Check, if the resource is a forum, is there are new messages, subjects or category created offline.
                        */
                        $forum_content = $this->checkNewContent($userRes, $user); 
                        echo count($forum_content);
                        $this->addForumToArchive($manifest, $forum_content);
                    }
                    
                }
            }
            fputs($manifest, '
        </workspace>');
        }
    }
    
    /*
    *   Check all the messages, subjects and categories of the forums
    *   and return the one that have been created.
    */
    private function checkNewContent(array $userRes, User $user)
    {
        $date_tmp = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);
        $date_sync = $date_tmp[0]->getLastSynchronization()->getTimestamp();
        
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
    *   Check all category of a list and see if they are new or updated.
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
    *   Check all subject of a list and see if they are new or updated.
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
    private function checkObsolete(array $userRes, User $user)
    {
        //$em = $this->getDoctrine()->getManager();
        $dateSync = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);
        $user_tmp = $dateSync[0]->getLastSynchronization();
        $date_user = $user_tmp->getTimestamp();
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
    private function addForumToArchive($manifest, $forum_content)
    {
        foreach($forum_content as $element)
        {
            //$class_name = getQualifiedClassName($element);
            //echo 'Bonjour'.'<br/>';
            //echo get_class($element).'<br/>';
            //echo get_class($element).'<br/>';
            $class_name = ''.get_class($element);
            switch($class_name)
            {
                case "Claroline\ForumBundle\Entity\Category" :
                    $this->addCategoryToManifest($manifest, $element);
                    break;
                case "Claroline\ForumBundle\Entity\Subject" :
                    $this->addSubjectToManifest($manifest, $element);
                    break;
                case "Claroline\ForumBundle\Entity\Message" :
                    $this->addMessageToManifest($manifest, $element);
                    break;
            }

        }
    }
    
    /**
     * Create a the archive based on the user     
     * Attention, if the archive file created is empty, it will not write zip file on disk !
     *
     */
    private function addResourcesToArchive(array $resToAdd, ZipArchive $archive, $manifest, $user, $path)
    {
        foreach($resToAdd as $element)
        {
            $this->addResourceToManifest($manifest, $element);
            $this->addResourceToZip($archive, $element, $user, $archive, $manifest, $path);
        }
    }
    
    /*
    *   Add the ressource inside a file in the Archive file.
    *   
    *   03/04/2014 : Interesting ONLY for the file at this time.
    */
    
    private function addResourceToZip(ZipArchive $archive, $resToAdd, $user, $archive, $manifest, $path)
    {
        switch($resToAdd->getResourceType()->getId())
        {
            case SyncConstant::FILE :
                $my_res = $this->resourceManager->getResourceFromNode($resToAdd);
                //echo 'Le fichier : '. $resToAdd->getName() . "<br/>";
                //echo 'Add to the Archive' . "<br/>";
                //$path = $path.$resToAdd->getWorkspace()->getId();
                $archive->addFile('..'.SyncConstant::ZIPFILEDIR.$my_res->getHashName(), 'data/'.$path.SyncConstant::ZIPFILEDIR.$my_res->getHashName());
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

    /*
    *   Here figure all methods used to manipulate the xml file.
    */
    
    /*
    *   Add a specific resource to the Manifest. 
    *   The informations written in the Manifest will depend of the type of the file.
    */
    private function addResourceToManifest($manifest, $resToAdd)
    {
        $type = $resToAdd->getResourceType()->getId();
        $creation_time = $resToAdd->getCreationDate()->getTimestamp();  
        $modification_time = $resToAdd->getModificationDate()->getTimestamp(); 
        
        switch($type)
        {
            case SyncConstant::FILE :
                $my_res = $this->resourceManager->getResourceFromNode($resToAdd);
                //echo 'My res class : '.get_class($my_res).'<br/>';
                //$creation_time = $resToAdd->getCreationDate()->getTimestamp();  
                //$modification_time = $resToAdd->getModificationDate()->getTimestamp();                
                
                fputs($manifest, '
                    <resource type="'.$resToAdd->getResourceType()->getName().'"
                    name="'.$resToAdd->getName().'"  
                    mimetype="'.$resToAdd->getMimeType().'"
                    creator="'.$resToAdd->getCreator()->getId().'"
                    size="'.$my_res->getSize().'"
                    hashname="'.$my_res->getHashName().'"
                    hashname_node="'.$resToAdd->getNodeHashName().'"
                    hashname_parent="'.$resToAdd->getParent()->getNodeHashName().'"
                    creation_date="'.$creation_time.'"
                    modification_date="'.$modification_time.'">
                    </resource>
                    ');
                break;
            case SyncConstant::DIR :
                // TOREMOVE SI BUG! ATTENTION LES WORKSPACES SONT AUSSI DES DIRECTORY GARE AU DOUBLE CHECK
                if($resToAdd->getParent() != NULL)
                {
                    //$creation_time = $resToAdd->getCreationDate()->getTimestamp();
                    //$modification_time = $resToAdd->getModificationDate()->getTimestamp();                    
                    
                fputs($manifest, '
                    <resource type="'.$resToAdd->getResourceType()->getName().'"
                    name="'.$resToAdd->getName().'"  
                    mimetype="'.$resToAdd->getMimeType().'"
                    creator="'.$resToAdd->getCreator()->getId().'"
                    hashname_node="'.$resToAdd->getNodeHashName().'"
                    hashname_parent="'.$resToAdd->getParent()->getNodeHashName().'"
                    creation_date="'.$creation_time.'"
                    modification_date="'.$modification_time.'">
                    </resource>
                    ');
                }
                break;
            case SyncConstant::TEXT :
                $my_res = $this->resourceManager->getResourceFromNode($resToAdd);  
                //echo get_class($my_res);
                $revision = $this->revisionRepo->findOneBy(array('text' => $my_res));
                //$creation_time = $resToAdd->getCreationDate()->getTimestamp();
                //$modification_time = $resToAdd->getModificationDate()->getTimestamp();
                //echo get_class($revision);
                fputs($manifest, '
                    <resource type="'.$resToAdd->getResourceType()->getName().'"
                    name="'.$resToAdd->getName().'"  
                    mimetype="'.$resToAdd->getMimeType().'"
                    creator="'.$resToAdd->getCreator()->getId().'"
                    version="'.$my_res->getVersion().'"
                    hashname_node="'.$resToAdd->getNodeHashName().'"
                    hashname_parent="'.$resToAdd->getParent()->getNodeHashName().'"
                    content="'.$revision->getContent().'"
                    creation_date="'.$creation_time.'"
                    modification_date="'.$modification_time.'">
                    </resource>
                    ');
                break;
        }
    }

    /*
    *   Add a specific Category in the Manifest.
    */
    private function addCategoryToManifest($manifest, $content)
    {
        echo 'Edition du manifeste pour ajouter une category'.'<br/>';
        $modification_time = $content->getModificationDate()->getTimestamp();
        $node_forum = $content->getForum()->getResourceNode();
    
                fputs($manifest, '
                    <category class="'.get_class($content).'"
                    id="'.$content->getId().'"
                    name="'.$content->getName().'"
                    hashname="'.$content->getHashName().'"
                    forum_node="'.$node_forum->getNodeHashName().'"  
                    date="'.$modification_time.'">
                    </category>
                    ');
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
                    <subject class="'.get_class($content).'"
                    name="'.$content->getTitle().'"
                    hashname="'.$content->getHashName().'"
                    category="'.$category.'"  
                    creator_id="'.$content->getCreator()->getId().'"
                    creation_date="'.$creation_time.'"
                    date="'.$update_time.'"
                    sticked="'.$content->isSticked().'">
                    </subject>
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
                    <message class="'.get_class($content).'"
                    id="'.$content->getId().'"
                    subject="'.$subject.'"  
                    hashname="'.$content->getHashName().'"
                    creator_id="'.$content->getCreator()->getId().'"
                    content="'.$content->getContent().'"
                    creation_date="'.$creation_time.'"
                    update_date="'.$update_time.'">
                    </message>
                    ');
    }
    
    /*
    *   Add informations of a specific workspace in the manifest.
    */
    private function addWorkspaceToManifest($manifest, $workspace)
    {
        $my_res_node = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findResourceNodeByWorkspace($workspace);
        //echo 'Ma creation_time : '.$my_res_node[0]->getCreationDate()->format('Y-m-d H:i:s').'<br/>';
        //echo 'Ma modification_time : '.$my_res_node[0]->getModificationDate()->format('Y-m-d H:i:s').'<br/>';
        $creation_time = $my_res_node[0]->getCreationDate()->getTimestamp();  
        $modification_time = $my_res_node[0]->getModificationDate()->getTimestamp(); 
        
        fputs($manifest,  '
        <workspace id="'.$workspace->getId().'"
        type="'.get_class($workspace).'"
        creator="'.$workspace->getCreator()->getId().'"
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
    *   Create the description of the manifest.
    */
    private function writeManifestDescription($manifest, User $user, $syncTime)
    {
        $dateSync = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);
        $user_tmp = $dateSync[0]->getLastSynchronization(); 
        $sync_timestamp = $user_tmp->getTimestamp();
        
            
        //$current_time = time();
        //$current_timestamp = $current_time->getTimestamp();
        
        fputs($manifest ,'
    <description>
        <creation_date>'.$syncTime.'</creation_date>
        <reference_date>'.$sync_timestamp.'</reference_date>
        <user>'.$user->getUsername().'</user>
        <user_id>'.$user->getId().'</user_id>
    </description>
        ');
    }
}
