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
use Claroline\OfflineBundle\Model\SyncConstant;
use Claroline\OfflineBundle\Model\SyncInfo;
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
    private $ut;
    
    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "resourceManager"= @DI\Inject("claroline.manager.resource_manager"),
     *     "forumManager"   = @DI\Inject("claroline.manager.forum_manager"),
     *     "ut"            = @DI\Inject("claroline.utilities.misc")
     * })
     */
    public function __construct(
        ObjectManager $om,
        ResourceManager $resourceManager,
        Manager $forumManager,
        ClaroUtilities $ut

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
        $this->ut = $ut;
    }
    
    // Return the type of resource supported by this service
    public function getType(){
        return 'claroline_forum';
    }
    
    /**
     * Add informations required to check and recreated a resource if necessary.
     *
     * @param \Claroline\CoreBundle\Entity\Resource\ResourceNode $resToAdd
     * @param \ZipArchive $archive
     * @param \DateTime $date
     */
    public function addResourceToManifest($domManifest, $domWorkspace, ResourceNode $resToAdd, ZipArchive $archive, DateTime $date)
    {
        $domRes = parent::addNodeToManifest($domManifest, $this->getType(), $domWorkspace, $resToAdd);
        $forum_content = $this->checkNewContent($resToAdd, $date);
        $this->addForumToArchive($domManifest, $domWorkspace, $forum_content);
        return $domManifest;            
    }
   
    /**
     * Create a resource of the type supported by the service based on the XML file.
     *
     * @param \Claroline\CoreBundle\Entity\Workspace\Workspace $workspace
     * @param \Claroline\CoreBundle\Entity\User $user
     * @param \Claroline\OfflineBundle\Model\SyncInfo $wsInfo
     * @param string $path
     *
     * @return \Claroline\OfflineBundle\Model\SyncInfo
     */
    public function createResource($resource, Workspace $workspace, User $user, SyncInfo $wsInfo, $path)
    { 
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
   
    /**
     * Update a resource of the type supported by the service based on the XML file.
     *
     * @param \Claroline\CoreBundle\Entity\Resource\ResourceNode $node
     * @param \Claroline\CoreBundle\Entity\Workspace\Workspace $workspace
     * @param \Claroline\CoreBundle\Entity\User $user
     * @param \Claroline\OfflineBundle\Model\SyncInfo $wsInfo
     * @param string $path
     *
     * @return \Claroline\OfflineBundle\Model\SyncInfo
     *
     */
    public function updateResource($resource, ResourceNode $node, Workspace $workspace, User $user, SyncInfo $wsInfo, $path)
    {   
        $type = $this->resourceManager->getResourceTypeByName($resource->getAttribute('type'));
        $modif_date = $resource->getAttribute('modification_date');
        $creation_date = $resource->getAttribute('creation_date'); //USELESS?
        $node_modif_date = $node->getModificationDate()->getTimestamp();
        $user_sync = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);
        
        if ($node_modif_date < $modif_date) {
            $this->resourceManager->rename($node, $resource->getAttribute('name'));
            $wsInfo->addToUpdate($resource->getAttribute('name'));
        }
        return $wsInfo;
    }
   
    /**
     * Create a copy of the resource in case of conflict (e.g. if a ressource has been modified both offline
     * and online)
     *
     * @param \Claroline\CoreBundle\Entity\Workspace\Workspace $workspace
     * @param \Claroline\CoreBundle\Entity\Resource\ResourceNode $node     
     * @param string $path
     */
    public function createDoublon($resource, Workspace $workspace, ResourceNode $node, $path)
    {
        return;
    }
   
    /**
     * Check all the messages, subjects and categories of the forums and return the ones that have been created or updated.
     *
     * @param \Claroline\CoreBundle\Entity\Resource\ResourceNode $node_forum
     * @param \DateTime $date_sync
     *
     * @return array()
     */
    private function checkNewContent(ResourceNode $node_forum, DateTime $date_sync)
    {
        $elem_to_sync = array();
        $current_forum = $this->forumRepo->findOneBy(array('resourceNode' => $node_forum));
        $categories = $this->categoryRepo->findBy(array('forum' => $current_forum));
        $elem_to_sync = $this->checkCategory($categories, $elem_to_sync, $date_sync);

        return $elem_to_sync;

    }    
    
    /**
     * Check all categories of a list and see if they are new or updated.
     *
     * @param \DateTime $date_sync
     *
     * @return array()
     */
    private function checkCategory($categories, $elem_to_sync, DateTime $date_sync)
    {
        foreach ($categories as $category) {
            if ($category->getModificationDate()->getTimestamp() > $date_sync) {
                 $elem_to_sync[] = $category;
            }
            $subjects = $this->subjectRepo->findBy(array('category' => $category));
            $elem_to_sync = $this->checkSubject($subjects, $elem_to_sync, $date_sync);
        }

        return $elem_to_sync;

    }


    /**
     * Check all subjects of a list and see if they are new or updated.
     *
     * @param \DateTime $date_sync
     *
     * @return array()
     */
    private function checkSubject($subjects, $elem_to_sync, DateTime $date_sync)
    {
        foreach ($subjects as $subject) {
            if ($subject->getModificationDate()->getTimestamp() > $date_sync) {
                 $elem_to_sync[] = $subject;
            }

            $messages = $this->messageRepo->findBySubject($subject);
            $elem_to_sync = $this->checkMessage($messages, $elem_to_sync, $date_sync);
        }

        return $elem_to_sync;

    }


    /**
     *   Check all message of a list and see if they are new or updated.
     *
     * @param \DateTime $date_sync
     *
     * @return array()
     */
    private function checkMessage($messages, $elem_to_sync, $date_sync)
    {
        foreach ($messages as $message) {
            if ($message->getModificationDate()->getTimestamp() > $date_sync) {
                $elem_to_sync[] = $message;
            }
        }

        return $elem_to_sync;
    }
    
    /**
     * Add the content of the forum in the Archive.
     */
    private function addForumToArchive($domManifest, $domWorkspace, $forum_content)
    {
        foreach ($forum_content as $element) {

            $this->addContentToManifest($domManifest, $domWorkspace, $element);
        }
    }
    
    /**
     * Add the content of a forum to the Manifest.
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
    
    /**
     * Add a specific Category to the Manifest.
     *
     * @param \Claroline\ForumBundle\Entity\Category $content
     */
    private function addCategory($domManifest, Category $content, $domRes)
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
    
    /**
     * Add a specific Subject to the Manifest.
     *
     * @param \Claroline\ForumBundle\Entity\Subject $content
     */
    private function addSubject($domManifest, Subject $content, $domRes)
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
    
    /**
     * Add a specific Message to the Manifest.
     *
     * @param \Claroline\ForumBundle\Entity\Message $content
     */
    private function addMessage($domManifest, Message $content, $domRes)
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
    
    /**
     * Check the content of a forum described in the XML file and either create or update this content. 
     */
    public function checkContent($content, $date)
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
                    $this->updateMessage($content, $message, $date);
                }

                // Update of the Dates
                // $message = $this->messageRepo->findOneBy(array('hashName' => $content->getAttribute('hashname')));
                // $this->updateDate($message, $content);
                break;
        }

    }

    /**
     * Create a new Forum Category based on the XML file in the Archive.
     */
    private function createCategory($category)
    {
        $node_forum = $this->resourceNodeRepo->findOneBy(array('hashName' => $category->getAttribute('forum_node')));
        $forum = $this->resourceManager->getResourceFromNode($node_forum);

        $category_name = $category->getAttribute('name');

        $this->forumManager->createCategory($forum, $category_name, true, $category->getAttribute('hashname'));
    }

    /**
     * Update a Forum Category based on the XML file in the Archive.
     *
     * @param \Claroline\ForumBundle\Entity\Category $category
     */
    private function updateCategory($xmlCategory, Category $category)
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
        else{

        echo 'Category already in DB!'.'<br/>';
        }
    }

    /**
     * Create a new Forum Subject based on the XML file in the Archive.
     */
    private function createSubject($subject)
    {
        $category = $this->categoryRepo->findOneBy(array('hashName' => $subject->getAttribute('category')));
        $creator = $this->userRepo->findOneBy(array('exchangeToken' => $subject->getAttribute('creator_id')));
        $sub = new Subject();
        $sub->setTitle($subject->getAttribute('title'));
        $sub->setCategory($category);
        $sub->setCreator($creator);
        $sub->setIsSticked($subject->getAttribute('sticked'));

        $this->forumManager->createSubject($sub, $subject->getAttribute('hashname'));
    }

    /**
     * Update a Forum Subject based on the XML file in the Archive.
     *
     * @param \Claroline\ForumBundle\Entity\Subject $subject
     */
    private function updateSubject($xmlSubject, Subject $subject)
    {
        $xmlName = $xmlSubject->getAttribute('title');
        $dbName = $subject->getTitle();
        $xmlModificationDate = $xmlSubject->getAttribute('update_date');
        $dbModificationDate = $subject->getModificationDate()->getTimestamp();
        if ($xmlName != $dbName) {
            if ($xmlModificationDate >= $dbModificationDate) {
                $this->forumManager->editSubject($subject, $dbName, $xmlName);
                $subject->setIsSticked($xmlSubject->getAttribute('sticked'));
            }
        }

        else{
        echo 'Subject already in DB!'.'<br/>';
        }
    }

    /**
     * Create a new Forum message based on the XML file in the Archive.
     */
    private function createMessage($message)
    {
        $creation_date = new DateTime();
        $creation_date->setTimestamp($message->getAttribute('creation_date'));
        
        $subject = $this->subjectRepo->findOneBy(array('hashName' => $message->getAttribute('subject')));
        $creator = $this->userRepo->findOneBy(array('exchangeToken' => $message->getAttribute('creator_id')));
        $content = $this->extractCData($message);
        $msg = new Message();
        $msg->setContent($content.'<br/>'.'<strong>Message created during synchronisation at : '.$creation_date->format('d/m/Y H:i:s').'</strong>');
        $msg->setCreator($creator);

        $this->forumManager->createMessage($msg, $subject, $message->getAttribute('hashname'));

    }

    /**
     * Update a Forum Message based on the XML file in the Archive.
     *
     * @param \Claroline\ForumBundle\Entity\Message $subject
     */
    private function updateMessage($xmlMessage, Message $message, $date)
    {
        $xmlContent = $this->extractCData($xmlMessage);
        $dbContent = $message->getContent();
        $xmlModificationDate = $xmlMessage->getAttribute('update_date');
        $dbModificationDate = $message->getModificationDate()->getTimestamp();
        if ($xmlContent != $dbContent) {
            if ($dbModificationDate < $date) {
                $this->forumManager->editMessage($message, $dbContent, $xmlContent);
            }
            else{
                $this->createMessageDoublon($xmlMessage, $message, $date);
            }
        }

        else{
        echo 'Message already in DB!'.'<br/>';
        }

    }
    
    /**
     * Create a doublon for a Forum Message based on the XML file in the Archive.
     *
     * @param \Claroline\ForumBundle\Entity\Message $subject
     */
    private function createMessageDoublon($xmlMessage, Message $message, $date){
        $new_hashname = $this->ut->generateGuid();
        $this->om->startFlushSuite();
        $message->setHashName($new_hashname);
        $this->om->endFlushSuite();
        
        $this->createMessage($xmlMessage);
    }

    /**
     * Update the creation and modification/update dates of a category, subject or message.
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
