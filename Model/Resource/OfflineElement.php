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

use Claroline\CoreBundle\Listener\TimestampableListener;
use Claroline\CoreBundle\Entity\Resource\ResourceNode;
use Claroline\CoreBundle\Entity\Workspace\Workspace;
use Claroline\CoreBundle\Entity\User;
use \DateTime;

abstract class OfflineElement
{
    protected $om;
    protected $resourceManager;
    protected $em;
    protected $userRepo;
    protected $resourceNodeRepo;
    protected $userManager;

    /**
     * Extract the text contains in the CDATA section of the XML file.
     */
    protected function extractCData($resource)
    {
        foreach ($resource->childNodes as $child) {
            if ($child->nodeType == XML_CDATA_SECTION_NODE) {
                return $child->textContent;
            }
        }
    }

    /**
     * Change the creation and modification dates of a node.
     *
     * @param \Claroline\CoreBundle\Entity\Resource\ResourceNode $node
     * @param \DateTime                                          $creationDate
     * @param \DateTime                                          $modificationDate
     *
     * @return \Claroline\CoreBundle\Entity\Resource\ResourceNode
     */
    protected function changeDate(ResourceNode $node, $creationDate, $modificationDate)
    {
        $listener = $this->getTimestampListener();
        $listener->forceTime($creationDate);
        $node->setCreationDate($creationDate);
        $listener = $this->getTimestampListener();
        $listener->forceTime($modificationDate);
        $node->setModificationDate($modificationDate);
        $this->om->persist($node);
        $this->resourceManager->logChangeSet($node);
        $this->om->flush();

        return $node;
    }

    /**
     * Catch the listener responsible for the auto-update of the Date in the database.
     */
    protected function getTimestampListener()
    {
        $evm = $this->em->getEventManager();

        foreach ($evm->getListeners() as $listenersByEvent) {
            foreach ($listenersByEvent as $listener) {
                if ($listener instanceof TimestampableListener) {
                    return $listener;
                }
            }
        }

        throw new \Exception('Cannot found timestamp listener');
    }

    /**
     * Method that returns the creator from the domObject
     */
    protected function getCreator($domNode)
    {
        $creator = $this->userRepo->findOneBy(array('username' => $domNode->getAttribute('creator_username')));
        if ($creator == null) {
            $creator = $this->createRandomUser(
                $domNode->getAttribute('creator_username'),
                $domNode->getAttribute('creator_firstname'),
                $domNode->getAttribute('creator_lastname'),
                $domNode->getAttribute('creator_mail')
            );
        }

        return $creator;
    }

    /**
     * Create a fake user account to symbolise the creator of a workspace or a resource.
     *
     * @return \Claroline\CoreBundle\Entity\User
     */
    protected function createRandomUser($username, $firstname, $lastname, $mail)
    {
        $user = new User();
        $user->setFirstName($firstname);
        $user->setLastName($lastname);
        $user->setUserName($username);
        $user->setMail($mail);
        // Generate the password randomly.
        $user->setPassword($this->generateRandomString());
        $this->userManager->createUser($user);

        return $user;
    }

    // Taken from http://stackoverflow.com/questions/4356289/php-random-string-generator
    protected function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $randomString;
    }

    /**
     * Add informations about the creator of the resource, workspace, message or subjet.
     *
     * @param \Claroline\CoreBundle\Entity\User $creator
     */
    protected function addCreatorInformations($domManifest, $domRes, User $creator)
    {
        $creatorUserName = $domManifest->createAttribute('creator_username');
        $creatorUserName->value = $creator->getUsername();
        $domRes->appendChild($creatorUserName);
        $creatorFirstname = $domManifest->createAttribute('creator_firstname');
        $creatorFirstname->value = $creator->getFirstName();
        $domRes->appendChild($creatorFirstname);
        $creatorLastname = $domManifest->createAttribute('creator_lastname');
        $creatorLastname->value = $creator->getLastName();
        $domRes->appendChild($creatorLastname);
        $creatorMail = $domManifest->createAttribute('creator_mail');
        $creatorMail->value = $creator->getMail();
        $domRes->appendChild($creatorMail);

        return $domRes;

    }
}
