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
use Claroline\CoreBundle\Entity\Workspace\Workspace;
use Claroline\CoreBundle\Library\Utilities\ClaroUtilities;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Pager\PagerFactory;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\OfflineBundle\Entity\UserSynchronized;
use Claroline\OfflineBundle\Model\SyncConstant;
use JMS\DiExtraBundle\Annotation as DI;
//use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Translation\TranslatorInterface;
use \ZipArchive;
use \DOMDocument;
use \DateTime;
use Claroline\OfflineBundle\Model\Resource\OfflineResource;

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
    private $offline;

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
        $this->workspaceRepo = $om->getRepository('ClarolineCoreBundle:Workspace\Workspace');
        $this->roleRepo = $om->getRepository('ClarolineCoreBundle:Role');
        $this->translator = $translator;
        $this->resourceManager = $resourceManager;
        $this->ut = $ut;
        $this->offline = array();
    }

    public function addOffline(OfflineResource  $offline)
    {
        $this->offline[$offline->getType()] = $offline;
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

        // Create the Directory if it does not exists.
        if (!is_dir($dir)) {
            mkdir($dir, 0777);
        }

        $hashname_zip = $this->ut->generateGuid();
        $fileName = $dir.'/sync_'.$hashname_zip.'.zip';

        $userWS = $this->workspaceRepo->findByUser($user);
        $types = array_keys($this->offline);

        if ($archive->open($fileName, ZipArchive::CREATE) === true) {
            $this->fillSyncZip($userWS, $domManifest, $sectManifest, $types, $user, $archive, $date);
        } else {
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

    /**
     * Add all the informations required to synchronized the resources in the Manifest and add
     * in the archive the file required for the synchronization
     *
     * @param \Claroline\CoreBundle\Entity\User $user
     * @param \ZipArchive                       $archive
     */
    public function fillSyncZip($userWS, $domManifest, $sectManifest, $types, User $user, ZipArchive $archive, $date)
    {
        foreach ($userWS as $element) {

            $domWorkspace = $this->addWorkspaceToManifest($domManifest, $sectManifest, $element, $user);
            $dateTimeStamp = new DateTime();
            $dateTimeStamp->setTimeStamp($date);
            $ressourcesToSync = $this->findResourceToSync($element, $types, $dateTimeStamp);// Remove all the resources not modified.

            if (count($ressourcesToSync) >= 1) {

                foreach ($ressourcesToSync as $res) {

                    $domManifest = $this->offline[$res->getResourceType()->getName()]->addResourceToManifest($domManifest, $domWorkspace, $res, $archive, $date);
                }
            }
        }
    }

    /**
     * Filter all the resources based on the user's last synchronization and
     * check which one need to be synchronized.
     *
     * @param \Claroline\CoreBundle\Entity\Workspace\Workspace $workspace
     */
    private function findResourceToSync(Workspace $workspace, $types, $date)
    {
        $query = $this->resourceNodeRepo->createQueryBuilder('res')
            ->join('res.resourceType', 'type')
            ->where('res.workspace = :workspace')
            ->andWhere('res.modificationDate > :date')
            ->andWhere('type.name IN (:types)')
            ->setParameter('workspace', $workspace)
            ->setParameter('types', $types)
            ->setParameter('date', $date)
            ->getQuery();

        return $query->getResult();

    }

    /************************************************************
    *   Here figure all methods used to manipulate the xml file. *
    *************************************************************/

    /**
     * Add informations of a specific workspace in the manifest.
     *
     * @param \Claroline\CoreBundle\Entity\Workspace\Workspace $workspace
     * @param \Claroline\CoreBundle\Entity\User                $user
     */
    private function addWorkspaceToManifest($domManifest, $sectManifest, Workspace $workspace, User $user)
    {
        $my_role = $this->roleRepo->findByUserAndWorkspace($user, $workspace);

        $my_res_node = $this->userSynchronizedRepo->findResourceNodeByWorkspace($workspace);
        $creation_time = $my_res_node[0]->getCreationDate()->getTimestamp();
        $modification_time = $my_res_node[0]->getModificationDate()->getTimestamp();

        $domWorkspace = $domManifest->createElement('workspace');
        $sectManifest->appendChild($domWorkspace);

        $type = $domManifest->createAttribute('type');
        $type->value = get_class($workspace);
        $domWorkspace->appendChild($type);
        $creator = $domManifest->createAttribute('creator_username');
        $creator->value = $workspace->getCreator()->getUsername();
        $domWorkspace->appendChild($creator);
        $creatorFirstname = $domManifest->createAttribute('creator_firstname');
        $creatorFirstname->value = $workspace->getCreator()->getFirstName();
        $domWorkspace->appendChild($creatorFirstname);
        $creatorLastname = $domManifest->createAttribute('creator_lastname');
        $creatorLastname->value = $workspace->getCreator()->getLastName();
        $domWorkspace->appendChild($creatorLastname);
        $creatorMail = $domManifest->createAttribute('creator_mail');
        $creatorMail->value = $workspace->getCreator()->getMail();
        $domWorkspace->appendChild($creatorMail);
        $role = $domManifest->createAttribute('role');
        $role->value = $my_role[0]->getName();
        $domWorkspace->appendChild($role);
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
        $description = $domManifest->createAttribute('description');
        $description->value = $workspace->getDescription();
        $domWorkspace->appendChild($description);
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

    /**
     * Create the description of the manifest.
     *
     * @param \Claroline\CoreBundle\Entity\User $user
     */
    private function writeManifestDescription($domManifest, $sectManifest, User $user, $date)
    {
        $sectDescription = $domManifest->createElement('description');
        $sectManifest->appendChild($sectDescription);

        $descCreation = $domManifest->createAttribute('creation_date');
        $descCreation->value = time();
        $sectDescription->appendChild($descCreation);

        $descReference = $domManifest->createAttribute('synchronization_date');
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
