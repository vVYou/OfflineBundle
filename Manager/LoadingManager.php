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
use Claroline\CoreBundle\Manager\RoleManager;
use Claroline\CoreBundle\Manager\UserManager;
use Claroline\ForumBundle\Manager\Manager;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Entity\Resource\File;
use Claroline\CoreBundle\Entity\Resource\Directory;
use Claroline\CoreBundle\Entity\Resource\Text;
use Claroline\CoreBundle\Entity\Resource\Revision;
use Claroline\CoreBundle\Entity\Workspace\Workspace;
use Claroline\CoreBundle\Library\Workspace\Configuration;
use Claroline\CoreBundle\Library\Utilities\ClaroUtilities;
use Claroline\CoreBundle\Library\Security\Utilities;
use Claroline\CoreBundle\Library\Security\TokenUpdater;
use Claroline\CoreBundle\Listener\TimestampableListener;
use Claroline\CoreBundle\Event\StrictDispatcher;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\CoreBundle\Pager\PagerFactory;
use Claroline\ForumBundle\Entity\Forum;
use Claroline\ForumBundle\Entity\Category;
use Claroline\ForumBundle\Entity\Subject;
use Claroline\ForumBundle\Entity\Message;
use Claroline\OfflineBundle\Model\SyncConstant;
use Claroline\OfflineBundle\Model\SyncInfo;
use Claroline\OfflineBundle\Entity\UserSynchronized;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Doctrine\ORM\EntityManager;
use \ZipArchive;
use \DOMDocument;
use \DateTime;
use Claroline\OfflineBundle\Model\Resource\OfflineResource;

/**
 * @DI\Service("claroline.manager.loading_manager")
 */

class LoadingManager
{
    private $om;
    private $pagerFactory;
    private $translator;
    private $userSynchronizedRepo;
    private $workspaceRepo;
    private $resourceNodeRepo;
    private $userRepo;
    private $revisionRepo;
    private $categoryRepo;
    private $subjectRepo;
    private $messageRepo;
    private $forumRepo;
    private $roleRepo;
    private $resourceManager;
    private $workspaceManager;
    private $roleManager;
    private $forumManager;
    private $userManager;
    private $templateDir;
    private $user;
    private $synchronizationDate;
    private $ut;
    private $dispatcher;
    private $path;
    private $security;
    private $tokenUpdater;
    private $syncInfoArray;
    private $offline;
    private $evm;

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
     *     "roleManager"    =   @DI\Inject("claroline.manager.role_manager"),
     *     "forumManager"   = @DI\Inject("claroline.manager.forum_manager"),
     *     "userManager"    = @DI\Inject("claroline.manager.user_manager"),
     *     "templateDir"    = @DI\Inject("%claroline.param.templates_directory%"),
     *     "ut"            = @DI\Inject("claroline.utilities.misc"),
     *     "dispatcher"      = @DI\Inject("claroline.event.event_dispatcher"),
     *     "security"           = @DI\Inject("security.context"),
     *     "tokenUpdater"       = @DI\Inject("claroline.security.token_updater"),
     *     "evm"            = @DI\Inject("doctrine.orm.entity_manager")
     * })
     */
    public function __construct(
        ObjectManager $om,
        PagerFactory $pagerFactory,
        TranslatorInterface $translator,
        WorkspaceManager $wsManager,
        ResourceManager $resourceManager,
        WorkspaceManager $workspaceManager,
        RoleManager $roleManager,
        Manager $forumManager,
        UserManager $userManager,
        $templateDir,
        ClaroUtilities $ut,
        StrictDispatcher $dispatcher,
        SecurityContextInterface $security,
        TokenUpdater $tokenUpdater,
        EntityManager $evm
    )
    {
        $this->om = $om;
        $this->pagerFactory = $pagerFactory;
        $this->userSynchronizedRepo = $om->getRepository('ClarolineOfflineBundle:UserSynchronized');
        $this->workspaceRepo = $om->getRepository('ClarolineCoreBundle:Workspace\Workspace');
        $this->resourceNodeRepo = $om->getRepository('ClarolineCoreBundle:Resource\ResourceNode');
        $this->userRepo = $om->getRepository('ClarolineCoreBundle:User');
        $this->revisionRepo = $om->getRepository('ClarolineCoreBundle:Resource\Revision');
        $this->categoryRepo = $om->getRepository('ClarolineForumBundle:Category');
        $this->subjectRepo = $om->getRepository('ClarolineForumBundle:Subject');
        $this->messageRepo = $om->getRepository('ClarolineForumBundle:Message');
        $this->forumRepo = $om->getRepository('ClarolineForumBundle:Forum');
        $this->roleRepo = $om->getRepository('ClarolineCoreBundle:Role');
        $this->translator = $translator;
        $this->wsManager = $wsManager;
        $this->resourceManager = $resourceManager;
        $this->workspaceManager = $workspaceManager;
        $this->roleManager = $roleManager;
        $this->forumManager = $forumManager;
        $this->userManager = $userManager;
        $this->templateDir = $templateDir;
        $this->ut = $ut;
        $this->dispatcher = $dispatcher;
        $this->security = $security;
        $this->tokenUpdater = $tokenUpdater;
        $this->syncInfoArray = array();
        $this->offline = array();
        $this->evm = $evm;

    }
    
    public function addOffline(OfflineResource  $offline)
    {
        $this->offline[$offline->getType()] = $offline;
    } 
    
    /**
     * This method open the zip file, call the loadXML function and
     * destroy the zip file while everything is done.
     *
     * @param \Claroline\CoreBundle\Entity\User $user
     */
    public function loadZip($zipPath, User $user)
    {
        $this->user = $user;
        //Extract the Zip
        $archive = new ZipArchive();
        if ($archive->open($zipPath)) {
            //Extract the Hashname of the ZIP from the path (length of hashname = 32 char).
            $zip_hashname = substr($zipPath, strlen($zipPath)-40, 36);
            $this->path = SyncConstant::DIRZIP.'/'.$zip_hashname.'/';
            // echo 'J extrait dans ce path : '.$this->path.'<br/>';
            $tmpdirectory = $archive->extractTo($this->path);

            //Call LoadXML
            $this->loadXML($this->path.SyncConstant::MANIFEST.'_'.$user->getUsername().'.xml');

        } else {
            throw new \Exception('Impossible to load the zip file');
        }

        return array(
            'infoArray' => $this->syncInfoArray,
            'synchronizationDate' => $this->synchronizationDate
        );
    }

    /**
     * This method will load and parse the manifest XML file
     */
    public function loadXML($xmlFilePath)
    {
        $xmlDocument = new DOMDocument();
        $xmlDocument->load($xmlFilePath);

        $this->importDescription($xmlDocument);
        $this->importWorkspaces($xmlDocument);

    }

    /**
     * This method is used to work on the different fields inside the
     * <description> tags in the XML file.
     */
    private function importDescription($xmlDocument)
    {
        $descriptions = $xmlDocument->getElementsByTagName("description");
        foreach ($descriptions as $description) {
            // $this->user = $this->userRepo->findOneBy(array('username' => $description->getAttribute('username'), 'mail' => $description->getAttribute('user_mail')));
            $this->synchronizationDate = $description->getAttribute('synchronization_date');
        }
    }

    /**
     * This method is used to work on the different workspaces inside the
     * <workspace> tags in the XML file.
     */
    private function importWorkspaces($xmlDocument)
    {
        $workspace_list = $xmlDocument->getElementsByTagName("workspace");

        foreach ($workspace_list as $work) {
            /**
            *   Check if a workspace with the given guid already exists.
            *   - if it doesn't exist then it will be created
            *   - then proceed to the resources (no matter if we have to create the workspace previously)
            */           
            $workspace = $this->workspaceRepo->findOneBy(array('guid' => $work->getAttribute('guid')));

            if ($workspace == NULL) {
                $workspace = $this->createWorkspace($work, $this->user);
            }
            $info = $this->importWorkspace($work->childNodes, $workspace, $work);
            $this->syncInfoArray[] = $info;

        }
    }

    /**
     * Visit all the 'resource' field in the 'workspace' inside the XML file and
     * either create or update the corresponding resources.
     *
     * @param \Claroline\CoreBundle\Entity\Workspace\Workspace $workspace
     */
    private function importWorkspace($resourceList, Workspace $workspace, $work)
    {
        $wsInfo = new SyncInfo();
        $wsInfo->setWorkspace($workspace->getName().' ('.$workspace->getCode().')');

        $resourceDirectory = $work->getElementsByTagName("resource-directory");
        
        foreach($resourceDirectory as $resource)
        {
            $node = $this->resourceNodeRepo->findOneBy(array('hashName' => $resource->getAttribute('hashname_node')));
            if (count($node) >= 1) {
                $wsInfo = $this->offline['directory']->updateResource($resource, $node, $workspace, $this->user, $wsInfo, $this->path);
            } 
            else {
                $wsInfo = $this->offline['directory']->createResource($resource, $workspace, $this->user, $wsInfo, $this->path);
            }
        }
        
        for ($i=0; $i<$resourceList->length; $i++) {
            $res = $resourceList->item($i);
            if ((strpos($res->nodeName,'resource') !== false) && $res->nodeName != "resource-directory") {
                $node = $this->resourceNodeRepo->findOneBy(array('hashName' => $res->getAttribute('hashname_node')));
                if (count($node) >= 1) {
                    $wsInfo = $this->offline[$res->getAttribute('type')]->updateResource($res, $node, $workspace, $this->user, $wsInfo, $this->path);
                } 
                else {
                    $wsInfo = $this->offline[$res->getAttribute('type')]->createResource($res, $workspace, $this->user, $wsInfo, $this->path);
                }
            }
            if ($res->nodeName == 'forum') {
                // Check the content of a forum described in the XML file.
                $this->offline['claroline_forum']->checkContent($res, $this->synchronizationDate);
            }
        }
        return $wsInfo;
    }

    /**
     * Create and return a new workspace detailed in the XML file.
     *
     * @param \Claroline\CoreBundle\Entity\User $user
     */
    private function createWorkspace($workspace, User $user)
    {
        // Use the create method from WorkspaceManager.
        // echo 'Je cree mon Workspace!'.'<br/>';
        $creation_date = new DateTime();
        $modification_date = new DateTime();
        // $creator = $this->om->getRepository('ClarolineCoreBundle:User')->findOneBy(array('exchangeToken' => $workspace->getAttribute('creator')));
        $ds = DIRECTORY_SEPARATOR;

        // $type = Configuration::TYPE_SIMPLE;
        $config = Configuration::fromTemplate(
            $this->templateDir . $ds . 'default.zip'
        );
        
        $creator = $this->getCreator($workspace);
        
        // $config->setWorkspaceType($type);
        $config->setWorkspaceName($workspace->getAttribute('name'));
        $config->setWorkspaceCode($workspace->getAttribute('code'));
        $config->setDisplayable($workspace->getAttribute('displayable'));
        $config->setSelfRegistration($workspace->getAttribute('selfregistration'));
        $config->setSelfUnregistration($workspace->getAttribute('selfunregistration'));
        $config->setWorkspaceDescription($workspace->getAttribute('description'));
        $config->setGuid($workspace->getAttribute('guid'));
        // $user = $this->security->getToken()->getUser();

        $my_ws = $this->workspaceManager->create($config, $creator);
        // $my_ws = $this->workspaceManager->create($config, $user);
        // $this->tokenUpdater->update($this->security->getToken());
        //$route = $this->router->generate('claro_workspace_list');

        // if ($workspace->getAttribute('creator_username') != $user->getUsername()) {
            // $role = $this->roleRepo->findByUserAndWorkspace($user, $my_ws);
            // $this->roleManager->dissociateUserRole($user, $role);
            // $role = $this->roleRepo->findOneBy(array('name' => $workspace->getAttribute('role')));
            // $this->roleManager->associateUserRole($user, $role);
        // }
        $this->roleManager->associateUserRole($user, $this->roleManager->getRoleByName($workspace->getAttribute('role')));

        $NodeWorkspace = $this->resourceNodeRepo->findOneBy(array('workspace' => $my_ws));

        $this->om->startFlushSuite();
        $creation_date->setTimestamp($workspace->getAttribute('creation_date'));
        $modification_date->setTimestamp($workspace->getAttribute('modification_date'));

        $NodeWorkspace->setCreator($user);
        $NodeWorkspace->setCreationDate($creation_date);
        $NodeWorkspace->setModificationDate($modification_date);
        $NodeWorkspace->setNodeHashName($workspace->getAttribute('hashname_node'));
        $this->om->endFlushSuite();

        return $my_ws;

    }
    
    private function getCreator($domNode)
    {
        $creator = $this->userRepo->findOneBy(array('username' => $domNode->getAttribute('creator_username')));
        if($creator == null) {
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
    private function createRandomUser($username, $firstname, $lastname, $mail)
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
    public function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }
    
    private function getTimestampListener()
    {
        $em = $this->evm->getEventManager();

        foreach ($em->getListeners() as $listenersByEvent) {
            foreach ($listenersByEvent as $listener) {
                if ($listener instanceof TimestampableListener) {
                    return $listener;
                }
            }
        }

        throw new \Exception('Cannot found timestamp listener');
    }

}
