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
use Claroline\CoreBundle\Entity\Resource\ResourceNode;
use Claroline\CoreBundle\Entity\Workspace\Workspace;
use Claroline\CoreBundle\Library\Utilities\ClaroUtilities;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Manager\UserManager;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\ForumBundle\Entity\Forum;
use Claroline\ForumBundle\Entity\Category;
use Claroline\ForumBundle\Entity\Subject;
use Claroline\ForumBundle\Entity\Message;
use Claroline\ForumBundle\Manager\Manager;
use Claroline\OfflineBundle\Model\SyncConstant;
use Claroline\OfflineBundle\Model\SyncInfo;
use JMS\DiExtraBundle\Annotation as DI;
use Doctrine\ORM\EntityManager;
use \DateTime;
use \ZipArchive;

/**
 * @DI\Service("claroline_offline.offline.forum")
 * @DI\Tag("claroline_offline.offline")
 */
class OfflineForum extends OfflineResource
{
    private $forumManager;
    private $subjectRepo;
    private $messageRepo;
    private $forumRepo;
    private $categoryRepo;
    private $ut;

    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "resourceManager"= @DI\Inject("claroline.manager.resource_manager"),
     *     "userManager"    = @DI\Inject("claroline.manager.user_manager"),
     *     "forumManager"   = @DI\Inject("claroline.manager.forum_manager"),
     *     "ut"             = @DI\Inject("claroline.utilities.misc"),
     *     "em"             = @DI\Inject("doctrine.orm.entity_manager")
     * })
     */
    public function __construct(
        ObjectManager $om,
        ResourceManager $resourceManager,
        UserManager $userManager,
        Manager $forumManager,
        ClaroUtilities $ut,
        EntityManager $em

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
        $this->userManager = $userManager;
        $this->forumManager = $forumManager;
        $this->ut = $ut;
        $this->em = $em;
    }

    // Return the type of resource supported by this service
    public function getType()
    {
        return 'claroline_forum';
    }

    /**
     * Add informations required to check and recreated a resource if necessary.
     *
     * @param \Claroline\CoreBundle\Entity\Resource\ResourceNode $resToAdd
     * @param \ZipArchive                                        $archive
     */
    public function addResourceToManifest($domManifest, $domWorkspace, ResourceNode $resToAdd, ZipArchive $archive, $date)
    {
        $domRes = parent::addNodeToManifest($domManifest, $this->getType(), $domWorkspace, $resToAdd);
        $forumContent = $this->checkNewContent($resToAdd, $date);
        $this->addForumToArchive($domManifest, $domWorkspace, $forumContent);

        return $domManifest;
    }

    /**
     * Create a resource of the type supported by the service based on the XML file.
     *
     * @param \Claroline\CoreBundle\Entity\Workspace\Workspace $workspace
     * @param \Claroline\CoreBundle\Entity\User                $user
     * @param \Claroline\OfflineBundle\Model\SyncInfo          $wsInfo
     * @param string                                           $path
     *
     * @return \Claroline\OfflineBundle\Model\SyncInfo
     */
    public function createResource($resource, Workspace $workspace, User $user, SyncInfo $wsInfo, $path)
    {
        $newResource = new Forum();
        $creationDate = new DateTime();
        $modificationDate = new DateTime();
        $creationDate->setTimestamp($resource->getAttribute('creation_date'));
        $modificationDate->setTimestamp($resource->getAttribute('modification_date'));

        $type = $this->resourceManager->getResourceTypeByName($resource->getAttribute('type'));
        // $creator = $this->userRepo->findOneBy(array('exchangeToken' => $resource->getAttribute('creator')));
        $creator = $this->getCreator($resource);
        $parentNode = $this->resourceNodeRepo->findOneBy(array('hashName' => $resource->getAttribute('hashname_parent')));

        if (count($parentNode) < 1) {
            // If the parent node doesn't exist anymore, workspace will be the parent.
            $parentNode  = $this->resourceNodeRepo->findOneBy(array('workspace' => $workspace));
        }

        $newResource->setName($resource->getAttribute('name'));
        $newResource->setMimeType($resource->getAttribute('mimetype'));

        $this->resourceManager->create($newResource, $type, $creator, $workspace, $parentNode, null, array(), $resource->getAttribute('hashname_node'));
        $wsInfo->addToCreate($resource->getAttribute('name'));

        $node = $newResource->getResourceNode();
        $this->changeDate($node, $creationDate, $modificationDate);

        return $wsInfo;
    }

    /**
     * Update a resource of the type supported by the service based on the XML file.
     *
     * @param \Claroline\CoreBundle\Entity\Resource\ResourceNode $node
     * @param \Claroline\CoreBundle\Entity\Workspace\Workspace   $workspace
     * @param \Claroline\CoreBundle\Entity\User                  $user
     * @param \Claroline\OfflineBundle\Model\SyncInfo            $wsInfo
     * @param string                                             $path
     *
     * @return \Claroline\OfflineBundle\Model\SyncInfo
     *
     */
    public function updateResource($resource, ResourceNode $node, Workspace $workspace, User $user, SyncInfo $wsInfo, $path)
    {
        $type = $this->resourceManager->getResourceTypeByName($resource->getAttribute('type'));
        $modificationDate = $resource->getAttribute('modification_date');
        $creationDate = $resource->getAttribute('creation_date');
        $nodeModifDate = $node->getModificationDate()->getTimestamp();

        if ($nodeModifDate < $modificationDate) {
            $this->resourceManager->rename($node, $resource->getAttribute('name'));
            $wsInfo->addToUpdate($resource->getAttribute('name'));
            $creation = new DateTime();
            $creation->setTimeStamp($creationDate);
            $modif = new DateTime();
            $modif->setTimeStamp($modificationDate);
            $this->changeDate($node, $creation, $modif);
        }

        return $wsInfo;
    }

    /**
     * Create a copy of the resource in case of conflict (e.g. if a ressource has been modified both offline
     * and online)
     *
     * @param \Claroline\CoreBundle\Entity\Workspace\Workspace   $workspace
     * @param \Claroline\CoreBundle\Entity\Resource\ResourceNode $node
     * @param string                                             $path
     */
    public function createDoublon($resource, Workspace $workspace, ResourceNode $node, $path)
    {
        return;
    }

    /**
     * Check all the messages, subjects and categories of the forums and return the ones that have been created or updated.
     *
     * @param \Claroline\CoreBundle\Entity\Resource\ResourceNode $nodeForum
     *
     * @return array()
     */
    private function checkNewContent(ResourceNode $nodeForum, $dateSync)
    {
        $elemToSync = array();
        $currentForum = $this->forumRepo->findOneBy(array('resourceNode' => $nodeForum));
        $categories = $this->categoryRepo->findBy(array('forum' => $currentForum));
        $elemToSync = $this->checkCategory($categories, $elemToSync, $dateSync);

        return $elemToSync;

    }

    /**
     * Check all categories of a list and see if they are new or updated.
     *
     *
     * @return array()
     */
    private function checkCategory($categories, $elemToSync, $dateSync)
    {
        foreach ($categories as $category) {
            if ($category->getModificationDate()->getTimestamp() > $dateSync) {
                echo 'ma date de modif '.$category->getModificationDate()->getTimestamp().' '.'ma date de sync'.$dateSync.'<br/>';
                $tot = new DateTime();
                echo 'ma date de modif '.$category->getModificationDate()->format('d/m/Y H:i:s').' '.'ma date de sync'.$tot->setTimeStamp($dateSync)->format('d/m/Y H:i:s').'<br/>';
                 $elemToSync[] = $category;
            }
            $subjects = $this->subjectRepo->findBy(array('category' => $category));
            $elemToSync = $this->checkSubject($subjects, $elemToSync, $dateSync);
        }

        return $elemToSync;

    }


    /**
     * Check all subjects of a list and see if they are new or updated.
     *
     *
     * @return array()
     */
    private function checkSubject($subjects, $elemToSync, $dateSync)
    {
        foreach ($subjects as $subject) {
            if ($subject->getModificationDate()->getTimestamp() > $dateSync) {
            echo 'ma date de modif '.$subject->getModificationDate()->getTimestamp().' '.'ma date de sync'.$dateSync.'<br/>';
            $tot = new DateTime();
            echo 'ma date de modif '.$subject->getModificationDate()->format('d/m/Y H:i:s').' '.'ma date de sync'.$tot->setTimeStamp($dateSync)->format('d/m/Y H:i:s').'<br/>';
                 $elemToSync[] = $subject;
            }

            $messages = $this->messageRepo->findBySubject($subject);
            $elemToSync = $this->checkMessage($messages, $elemToSync, $dateSync);
        }

        return $elemToSync;

    }


    /**
     *   Check all message of a list and see if they are new or updated.
     *
     *
     * @return array()
     */
    private function checkMessage($messages, $elemToSync, $dateSync)
    {
        foreach ($messages as $message) {
            if ($message->getModificationDate()->getTimestamp() > $dateSync) {
            echo 'ma date de modif '.$message->getModificationDate()->getTimestamp().' '.'ma date de sync'.$dateSync.'<br/>';
            $tot = new DateTime();
            echo 'ma date de modif '.$message->getModificationDate()->format('d/m/Y H:i:s').' '.'ma date de sync'.$tot->setTimeStamp($dateSync)->format('d/m/Y H:i:s').'<br/>';
            $elemToSync[] = $message;
            }
        }

        return $elemToSync;
    }

    /**
     * Add the content of the forum in the Archive.
     */
    private function addForumToArchive($domManifest, $domWorkspace, $forumContent)
    {
        foreach ($forumContent as $element) {

            $this->addContentToManifest($domManifest, $domWorkspace, $element);
        }
    }

    /**
     * Add the content of a forum to the Manifest.
     */
    private function addContentToManifest($domManifest, $domWorkspace, $content)
    {

        $creationTime = $content->getCreationDate()->getTimestamp();
        $contentType = get_class($content);
        $modificationTime = $content->getModificationDate()->getTimestamp();

        $domRes = $domManifest->createElement('forum');
        $domWorkspace->appendChild($domRes);

        $class = $domManifest->createAttribute('class');
        $class->value = $contentType;
        $domRes->appendChild($class);
        $hashname = $domManifest->createAttribute('hashname');
        $hashname->value = $content->getHashName();
        $domRes->appendChild($hashname);
        $creationDate = $domManifest->createAttribute('creation_date');
        $creationDate->value = $creationTime ;
        $domRes->appendChild($creationDate);
        $updateDate = $domManifest->createAttribute('update_date');
        $updateDate->value = $modificationTime;
        $domRes->appendChild($updateDate);


        switch ($contentType) {
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

        $nodeForum = $content->getForum()->getResourceNode();
        $forumNode = $domManifest->createAttribute('forum_node');
        $forumNode->value = $nodeForum->getNodeHashName();
        $domRes->appendChild($forumNode);
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
        $categoryHash = $content->getCategory()->getHashName();
        $category = $domManifest->createAttribute('category');
        $category->value = $categoryHash;
        $domRes->appendChild($category);
        $title = $domManifest->createAttribute('title');
        $title->value = $content->getTitle();
        $domRes->appendChild($title);
        $closed = $domManifest->createAttribute('closed');
        $closed->value = $content->isClosed();
        $domRes->appendChild($closed);
        $sticked = $domManifest->createAttribute('sticked');
        $sticked->value = $content->isSticked();
        $domRes->appendChild($sticked);
        $domRes = $this->addCreatorInformations($domManifest, $domRes, $content->getCreator());
    }

    /**
     * Add a specific Message to the Manifest.
     *
     * @param \Claroline\ForumBundle\Entity\Message $content
     */
    private function addMessage($domManifest, Message $content, $domRes)
    {
        $modificationTime = $content->getModificationDate()->getTimestamp();
        $subjectHash = $content->getSubject()->getHashName();

        $updateDate = $domManifest->createAttribute('update_date');
        $updateDate->value = $modificationTime;
        $domRes->appendChild($updateDate);
        $subject = $domManifest->createAttribute('subject');
        $subject->value = $subjectHash;
        $domRes->appendChild($subject);
        $cdata = $domManifest->createCDATASection($content->getContent());
        $domRes->appendChild($cdata);
        $domRes = $this->addCreatorInformations($domManifest, $domRes, $content->getCreator());

    }

    /**
     * Check the content of a forum described in the XML file and either create or update this content.
     */
    public function checkContent($content, $date)
    {
        $contentType = $content->getAttribute('class');
        switch ($contentType) {
            case SyncConstant::CATE :
                $category = $this->categoryRepo->findOneBy(array('hashName' => $content->getAttribute('hashname')));
                if ($category == null) {
                    $this->createCategory($content);
                } else {
                    $this->updateCategory($content, $category);
                }
                break;

            case SyncConstant::SUB :
                $subject = $this->subjectRepo->findOneBy(array('hashName' => $content->getAttribute('hashname')));
                if ($subject == null) {
                    $this->createSubject($content);
                } else {
                    $this->updateSubject($content, $subject);
                }
                break;

            case SyncConstant::MSG :
                $message = $this->messageRepo->findOneBy(array('hashName' => $content->getAttribute('hashname')));
                if ($message == null) {
                    $this->createMessage($content);
                } else {
                    $this->updateMessage($content, $message, $date);
                }
                break;
        }

    }

    /**
     * Create a new Forum Category based on the XML file in the Archive.
     */
    private function createCategory($xmlCategory)
    {
        $nodeForum = $this->resourceNodeRepo->findOneBy(array('hashName' => $xmlCategory->getAttribute('forum_node')));
        $forum = $this->resourceManager->getResourceFromNode($nodeForum);

        $category_name = $xmlCategory->getAttribute('name');

        $newCategory = $this->forumManager->createCategory($forum, $category_name, true, $xmlCategory->getAttribute('hashname'));

        // Update of the Dates
        $this->updateDate($newCategory, $xmlCategory);
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
                // Update of the Dates
                $this->updateDate($category, $xmlCategory);
            }
        }
    }

    /**
     * Create a new Forum Subject based on the XML file in the Archive.
     */
    private function createSubject($xmlSubject)
    {
        $category = $this->categoryRepo->findOneBy(array('hashName' => $xmlSubject->getAttribute('category')));
        // $creator = $this->userRepo->findOneBy(array('exchangeToken' => $subject->getAttribute('creator_id')));
        $creator = $this->getCreator($xmlSubject);
        $sub = new Subject();
        $sub->setTitle($xmlSubject->getAttribute('title'));
        $sub->setCategory($category);
        $sub->setCreator($creator);
        $sub->setIsSticked($xmlSubject->getAttribute('sticked'));
        $sub->setIsClosed($xmlSubject->getAttribute('closed'));

        $this->forumManager->createSubject($sub, $xmlSubject->getAttribute('hashname'));

        // Update of the Dates
        $this->updateDate($sub, $xmlSubject);
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
                $subject->setIsClosed($xmlSubject->getAttribute('closed'));

                // Update of the Dates
                $this->updateDate($subject, $xmlSubject);
            }
        }
    }

    /**
     * Create a new Forum message based on the XML file in the Archive.
     */
    private function createMessage($xmlMessage)
    {
        $creationDate = new DateTime();
        $creationDate->setTimestamp($xmlMessage->getAttribute('creation_date'));

        $subject = $this->subjectRepo->findOneBy(array('hashName' => $xmlMessage->getAttribute('subject')));
        // $creator = $this->userRepo->findOneBy(array('exchangeToken' => $message->getAttribute('creator_id')));
        $creator = $this->getCreator($xmlMessage);
        $content = $this->extractCData($xmlMessage);
        $msg = new Message();
        $msg->setContent($content.'<br/>'.'<strong>Message created during synchronisation at : '.$creationDate->format('d/m/Y H:i:s').'</strong>');
        $msg->setCreator($creator);

        $this->forumManager->createMessage($msg, $subject, $xmlMessage->getAttribute('hashname'));
        // Update of the Dates
        $this->updateDate($msg, $xmlMessage);

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
                // Update of the Dates
                $this->updateDate($message, $xmlMessage);
            } else {
                $this->createMessageDoublon($xmlMessage, $message, $date);
            }
        }

    }

    /**
     * Create a doublon for a Forum Message based on the XML file in the Archive.
     *
     * @param \Claroline\ForumBundle\Entity\Message $subject
     */
    private function createMessageDoublon($xmlMessage, Message $message, $date)
    {
        $new_hashname = $this->ut->generateGuid();
        $this->om->startFlushSuite();
        $message->setHashName($new_hashname);
        $this->om->endFlushSuite();

        $this->createMessage($xmlMessage);
    }

    /**
     * Update the creation and modification/update dates of a category, subject or message.
     */
    private function updateDate($forumContent, $xmlContent)
    {
        $creationDate = new DateTime();
        $creationDate->setTimestamp($xmlContent->getAttribute('creation_date'));
        $modificationDate = new DateTime();
        $modificationDate->setTimestamp($xmlContent->getAttribute('update_date'));

        $listener = $this->getTimestampListener();
        $listener->forceTime($creationDate);
        $forumContent->setCreationDate($creationDate);
        $listener = $this->getTimestampListener();
        $listener->forceTime($modificationDate);
        $forumContent->setModificationDate($modificationDate);
        $this->om->persist($forumContent);
        // $this->forumManager->logChangeSet($forumContent);
        $this->om->flush();
    }
}
