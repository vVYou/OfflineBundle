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
use Claroline\CoreBundle\Entity\Workspace\Workspace;
use Claroline\CoreBundle\Library\Utilities\ClaroUtilities;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\ForumBundle\Entity\Forum;
use Claroline\ForumBundle\Entity\Category;
use Claroline\ForumBundle\Entity\Subject;
use Claroline\ForumBundle\Entity\Message;
use Claroline\ForumBundle\Manager\Manager;
use Claroline\OfflineBundle\Entity\UserSynchronized;
use Claroline\OfflineBundle\SyncConstant;
use Claroline\OfflineBundle\SyncInfo;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\Translation\TranslatorInterface;
use \DOMDocument;
use \DateTime;

/**
 * @DI\Service("claroline_offline.offline.forum")
 * @DI\Tag("claroline_offline.offline")
 */
class OfflineForum extends OfflineResource
{    

    private $om;
    private $resourceManager;
    private $forumManager;
    private $userRepo;
    private $subjectRepo;
    private $messageRepo;
    private $forumRepo;
    private $categoryRepo;
    private $resourceNodeRepo;
    
    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "resourceManager"= @DI\Inject("claroline.manager.resource_manager"),
     *     "forumManager"   = @DI\Inject("claroline.manager.forum_manager")
     * })
     */
    public function __construct(
        ObjectManager $om,
        ResourceManager $resourceManager,
        Manager $forumManager

    )
    {
        $this->om = $om;
        $this->resourceNodeRepo = $om->getRepository('ClarolineCoreBundle:Resource\ResourceNode');
        $this->userRepo = $om->getRepository('ClarolineCoreBundle:User');
        $this->subjectRepo = $om->getRepository('ClarolineForumBundle:Subject');
        $this->messageRepo = $om->getRepository('ClarolineForumBundle:Message');
        $this->forumRepo = $om->getRepository('ClarolineForumBundle:Forum');
        $this->categoryRepo = $om->getRepository('ClarolineForumBundle:Category');
        $this->resourceManager = $resourceManager;
        $this->forumManager = $forumManager;
    }
    
    public function getType(){
        return 'claroline_forum';
    }
    
   public function addResourceToManifest($domManifest, $domWorkspace, $resToAdd, $archive, $date){
       $domRes = parent::addResourceToManifest($domManifest, $domWorkspace, $resToAdd, $archive, $date);
       $forum_content = $this->checkNewContent($resToAdd, $date);
       $this->addForumToArchive($domManifest, $domWorkspace, $forum_content);
       return $domManifest;            
   }
   
   public function createResource($resource, $workspace, $user, $wsInfo, $path){
   
        $newResource = new Forum();
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
   
        $newResource = new Forum();
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
   
   /*
    *   Check all the messages, subjects and categories of the forums
    *   and return the ones that have been created or updated.
    */
    private function checkNewContent($node_forum, $date_sync)
    {
        // $date_tmp = $this->userSynchronizedRepo->findUserSynchronized($user);
        // $date_sync = $date_tmp[0]->getLastSynchronization()->getTimestamp();

        $elem_to_sync = array();
            //echo 'Un forum'.'<br/>';
        $current_forum = $this->forumRepo->findOneBy(array('resourceNode' => $node_forum));
        $categories = $this->categoryRepo->findBy(array('forum' => $current_forum));
        $elem_to_sync = $this->checkCategory($categories, $elem_to_sync, $date_sync);

        return $elem_to_sync;

    }    
    
    /*
    *   Check all categories of a list and see if they are new or updated.
    */
    private function checkCategory($categories, $elem_to_sync, $date_sync)
    {
        foreach ($categories as $category) {
            /*
            *   TODO :  Profiter de ce passage pour voir si la category a ete mise a jour
            *           ou si elle est nouvelle.
            */

            if ($category->getModificationDate()->getTimestamp() > $date_sync) {
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
        foreach ($subjects as $subject) {
            /*
            *   TODO :  Profiter de ce passage pour voir si le sujet a ete mis a jour
            *           ou si il est nouveau.
            */
            if ($subject->getModificationDate()->getTimestamp() > $date_sync) {
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
        foreach ($messages as $message) {
            /*
            *   TODO :  Gerer les messages update.
            */
            if ($message->getModificationDate()->getTimestamp() > $date_sync) {
                $elem_to_sync[] = $message;
            }
        }

        return $elem_to_sync;
    }
    
     /*
    *   Add the content of the forum in the Archive.
    */
    private function addForumToArchive($domManifest, $domWorkspace, $forum_content)
    {
        foreach ($forum_content as $element) {
        
            $class_name = ''.get_class($element);

            $this->addContentToManifest($domManifest, $domWorkspace, $element);
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


        switch ($content_type) {
            case SyncConstant::CATE :
                $this->addCategory($domManifest, $content, $domRes);
                break;
            case SyncConstant::SUB :
                $this->addSubject($domManifest, $content, $domRes);
                break;
            case SyncConstant::MSG :
                $this->addMessage($domManifest, $content, $domRes);
                break;
        }

    }
    
    private function addCategory($domManifest, $content, $domRes)
    {
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
    
    }
    
    private function addSubject($domManifest, $content, $domRes)
    {
        $modification_time = $content->getModificationDate()->getTimestamp();
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
    
    }
    
    private function addMessage($domManifest, $content, $domRes)
    {
        $modification_time = $content->getModificationDate()->getTimestamp();
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
    }
    
        /*
    *   Check the content of a forum described in the XML file and
    *   either create or update this content.
    */
    public function checkContent($content)
    {
        $content_type = $content->getAttribute('class');
        switch ($content_type) {
            case SyncConstant::CATE :
                $category = $this->categoryRepo->findOneBy(array('hashName' => $content->getAttribute('hashname')));
                if ($category == null) {
                    $this->createCategory($content);
                } else {
                    $this->updateCategory($content, $category);
                }

                // Update of the Dates
                // $category = $this->categoryRepo->findOneBy(array('hashName' => $content->getAttribute('hashname')));
                // $this->updateDate($category, $content);
                break;

            case SyncConstant::SUB :
                $subject = $this->subjectRepo->findOneBy(array('hashName' => $content->getAttribute('hashname')));
                if ($subject == null) {
                    $this->createSubject($content);
                } else {
                    $this->updateSubject($content, $subject);
                }

                // Update of the Dates
                // $subject = $this->subjectRepo->findOneBy(array('hashName' => $content->getAttribute('hashname')));
                // $this->updateDate($subject, $content);
                break;

            case SyncConstant::MSG :
                $message = $this->messageRepo->findOneBy(array('hashName' => $content->getAttribute('hashname')));
                if ($message == null) {
                    $this->createMessage($content);
                } else {
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
        // echo 'Category created'.'<br/>';

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
        if ($xmlName != $dbName) {
            if ($xmlModificationDate > $dbModificationDate) {
                $this->forumManager->editCategory($category, $dbName, $xmlName);
            }
        }

        // echo 'Category already in DB!'.'<br/>';
    }

    /*
    *   Create a new Forum Subject based on the XML file in the Archive.
    */
    private function createSubject($subject)
    {
        // echo 'Subject created'.'<br/>';

        $category = $this->categoryRepo->findOneBy(array('hashName' => $subject->getAttribute('category')));
        $creator = $this->userRepo->findOneBy(array('exchangeToken' => $subject->getAttribute('creator_id')));
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
        $dbModificationDate = $subject->getModificationDate()->getTimestamp();
        if ($xmlName != $dbName) {
            if ($xmlModificationDate > $dbModificationDate) {
                $this->forumManager->editSubject($subject, $dbName, $xmlName);
                $subject->setIsSticked($xmlSubject->getAttribute('sticked'));
            }
        }

        // echo 'Subject already in DB!'.'<br/>';
    }

    /*
    *   Create a new Forum message based on the XML file in the Archive.
    */
    private function createMessage($message)
    {
        $creation_date = new DateTime();
        $creation_date->setTimestamp($message->getAttribute('creation_date'));
        // Message Creation
        // echo 'Message created'.'<br/>';

        $subject = $this->subjectRepo->findOneBy(array('hashName' => $message->getAttribute('subject')));
        $creator = $this->userRepo->findOneBy(array('exchangeToken' => $message->getAttribute('creator_id')));
        $content = $this->extractCData($message);
        $msg = new Message();
        $msg->setContent($content.'<br/>'.'<strong>Message created during synchronisation at : '.$creation_date->format('d/m/Y H:i:s').'</strong>');
        $msg->setCreator($creator);

        $this->forumManager->createMessage($msg, $subject, $message->getAttribute('hashname'));

    }

    /*
    *   Update a Forum message based on the XML file in the Archive.
    */
    private function updateMessage($xmlMessage, $message)
    {
        $xmlContent = $this->extractCData($xmlMessage);
        $dbContent = $message->getContent();
        $xmlModificationDate = $xmlMessage->getAttribute('update_date');
        $dbModificationDate = $message->getModificationDate()->getTimestamp();
        if ($xmlContent != $dbContent) {
            if ($xmlModificationDate > $dbModificationDate) {
                $this->forumManager->editMessage($message, $dbContent, $xmlContent);
            }
        }

        // echo 'Message already in DB!'.'<br/>';

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
        $forumContent->setModificationDate($modification_date);
        $this->om->persist($forumContent);
        $this->om->endFlushSuite();
    }
}
