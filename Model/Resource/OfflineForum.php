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

interface OfflineForum implements OfflineForum
{
   public function addResourceToManifest($domManifest, $domWorkspace, $resToAdd){
       parent::addResourceToManifest($domManifest, $domWorkspace, $resToAdd);
       $forum_content = $this->checkNewContent($userRes, $user, $date);
       $this->addForumToArchive($domManifest, $domWorkspace, $forum_content);
                   
   }
   
   public function createResource(){
   }
   
   public function updateResource(){
   }
   
   public function createDoublon(){
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
        foreach ($userRes as $node_forum) {
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
        foreach ($categories as $category) {
            /*
            *   TODO :  Profiter de ce passage pour voir si la category a ete mise a jour
            *           ou si elle est nouvelle.
            */

            if ($category->getModificationDate()->getTimestamp() > $date_sync) {
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
        foreach ($subjects as $subject) {
            /*
            *   TODO :  Profiter de ce passage pour voir si le sujet a ete mis a jour
            *           ou si il est nouveau.
            */
            if ($subject->getUpdate()->getTimestamp() > $date_sync) {
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
        foreach ($messages as $message) {
            /*
            *   TODO :  Gerer les messages update.
            */
            echo 'Un message'.'<br/>';
            if ($message->getUpdate()->getTimestamp() > $date_sync) {
                echo 'Le message est nouveau'.'<br/>';
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
}
