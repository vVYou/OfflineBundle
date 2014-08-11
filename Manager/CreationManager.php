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
use Claroline\OfflineBundle\Model\Resource\OfflineElement;
use Doctrine\ORM\EntityManager;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\Translation\TranslatorInterface;
use \ZipArchive;
use \DOMDocument;
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
    private $offline;
    private $manifestName;
    private $syncDownDir;
	private $em;

    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "om"              = @DI\Inject("claroline.persistence.object_manager"),
     *     "pagerFactory"    = @DI\Inject("claroline.pager.pager_factory"),
     *     "translator"      = @DI\Inject("translator"),
     *     "resourceManager" = @DI\Inject("claroline.manager.resource_manager"),
     *     "ut"              = @DI\Inject("claroline.utilities.misc"),
     *     "manifestName"    = @DI\Inject("%claroline.synchronisation.manifest%"),
     *     "syncDownDir"     = @DI\Inject("%claroline.synchronisation.down_directory%"),
     *     "em"              = @DI\Inject("doctrine.orm.entity_manager")
     * })
     */
    public function __construct(
        ObjectManager $om,
        PagerFactory $pagerFactory,
        TranslatorInterface $translator,
        ResourceManager $resourceManager,
        ClaroUtilities $ut,
        $manifestName,
        $syncDownDir,
        EntityManager $em
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
        $this->manifestName = $manifestName;
        $this->syncDownDir = $syncDownDir;
		$this->em = $em;
    }

    public function addOffline(OfflineElement  $offline)
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
        $manifestName = $this->manifestName.'_'.$user->getUsername().'.xml';

        // Manifest section
        $sectManifest = $domManifest->createElement('manifest');
        $domManifest->appendChild($sectManifest);

        //Description section
        $this->writeManifestDescription($domManifest, $sectManifest, $user, $date);

        $dir = $this->syncDownDir.$user->getId();
        // Create the Directory if it does not exists.
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $hashname_zip = $this->ut->generateGuid();
        $fileName = $dir.'/sync_'.$hashname_zip.'.zip';

        $userWS = $this->workspaceRepo->findByUser($user);
        $types = array_keys($this->offline);

        if ($archive->open($fileName, ZipArchive::CREATE) === true) {
            $this->fillSyncZip($userWS, $domManifest, $sectManifest, $types, $user, $archive, $date);
            $this->addCompleteResourceList($domManifest, $sectManifest, $user, $types);
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
    private function fillSyncZip($userWS, $domManifest, $sectManifest, $types, User $user, ZipArchive $archive, $date)
    {
        foreach ($userWS as $element) {
            $domWorkspace = $this->offline['workspace']->addWorkspaceToManifest($domManifest, $sectManifest, $element, $user);
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
    
    private function addCompleteResourceList($domManifest, $sectManifest, $user, $types)
    {
        $sectResources = $domManifest->createElement('resources');
        $sectManifest->appendChild($sectResources);
        $allResUser = $this->getUserRessources($user, $types);
        foreach($allResUser as $res){
            $this->addResourceAndId($domManifest, $res, $sectResources);
        }
    }
    
    public function addResourceAndId($domManifest, $res, $resourcesSec)
    {
        $domRes = $domManifest->createElement('res');
        $resourcesSec->appendChild($domRes);
        $hashname_node = $domManifest->createAttribute('hashname_node');
        $hashname_node->value = $res->getNodeHashName();
        $domRes->appendChild($hashname_node);
        // return $domRes;
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
    
    private function getUserRessources(User $user, $types)
	{
        
		$query = $this->em->createQuery('
			SELECT res FROM Claroline\CoreBundle\Entity\Resource\ResourceNode res 
			JOIN res.workspace w
            JOIN res.resourceType type
			JOIN w.roles r
			JOIN r.users u
			WHERE u.id = :user
            AND type.name IN (:types)
			');
		$query->setParameter('user', $user);
        $query->setParameter('types', $types);

        return $query->getResult();
	}

    /************************************************************
    *   Here figure all methods used to manipulate the xml file. *
    *************************************************************/

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
