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
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Manager\WorkspaceManager;
use Claroline\CoreBundle\Manager\RoleManager;
use Claroline\CoreBundle\Manager\UserManager;
use Claroline\CoreBundle\Library\Workspace\Configuration;
use Symfony\Component\DependencyInjection\ContainerInterface;
use JMS\DiExtraBundle\Annotation as DI;
use \DateTime;

/**
 * @DI\Service("claroline_offline.offline.workspace")
 * @DI\Tag("claroline_offline.offline")
 */
class OfflineWorkspace extends OfflineElement
{
    private $roleRepo;
    private $templateDir;
    private $workspaceManager;
    private $roleManager;

    /**
     * Constructor.
     *
     * @DI\InjectParams({
	 *     "container"      = @DI\Inject("service_container"),
     *     "templateDir"    = @DI\Inject("%claroline.param.templates_directory%")
     * })
     */
    public function __construct(
        ContainerInterface   $container,
        $templateDir
    )
    {
        $this->container = $container;
        $this->templateDir = $templateDir;
    }

    // Return the type of resource supported by this service
    public function getType()
    {
        return 'workspace';
    }

    /**
     * Add informations of a specific workspace in the manifest.
     *
     * @param \Claroline\CoreBundle\Entity\Workspace\Workspace $workspace
     * @param \Claroline\CoreBundle\Entity\User                $user
     */
    public function addWorkspaceToManifest($domManifest, $sectManifest, Workspace $workspace, User $user)
    {
        $this->om = $this->container->get('claroline.persistence.object_manager');
        $this->resourceNodeRepo = $this->om->getRepository('ClarolineCoreBundle:Resource\ResourceNode');
        $this->roleRepo = $this->om->getRepository('ClarolineCoreBundle:Role');

        $myRole = $this->roleRepo->findByUserAndWorkspace($user, $workspace);

        // $myResNode = $this->userSynchronizedRepo->findResourceNodeByWorkspace($workspace);
        $myResNode = $this->resourceNodeRepo->findOneBy(array('workspace' => $workspace));
        $creationTime = $myResNode->getCreationDate()->getTimestamp();
        $modificationTime = $myResNode->getModificationDate()->getTimestamp();

        $domWorkspace = $domManifest->createElement('workspace');
        $sectManifest->appendChild($domWorkspace);

        $type = $domManifest->createAttribute('type');
        $type->value = get_class($workspace);
        $domWorkspace->appendChild($type);
        $role = $domManifest->createAttribute('role');
        $role->value = $myRole[0]->getName();
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
        $hashnameNode = $domManifest->createAttribute('hashname_node');
        $hashnameNode->value = $myResNode->getNodeHashName();
        $domWorkspace->appendChild($hashnameNode);
        $creationDate = $domManifest->createAttribute('creation_date');
        $creationDate->value = $creationTime;
        $domWorkspace->appendChild($creationDate);
        $modificationDate = $domManifest->createAttribute('modification_date');
        $modificationDate->value = $modificationTime;
        $domWorkspace->appendChild($modificationDate);
        $domWorkspace = $this->addCreatorInformations($domManifest, $domWorkspace, $workspace->getCreator());

        return $domWorkspace;
    }

    /**
     * Create and return a new workspace detailed in the XML file.
     *
     * @param \Claroline\CoreBundle\Entity\User $user
     *
     * @return \Claroline\CoreBundle\Entity\Workspace\Workspace
     */
    public function createWorkspace($workspace, User $user)
    {
        $this->om = $this->container->get('claroline.persistence.object_manager');
        $this->userManager = $this->container->get('claroline.manager.user_manager');
        $this->workspaceManager = $this->container->get('claroline.manager.workspace_manager');
        $this->roleManager = $this->container->get('claroline.manager.role_manager');
        $this->userRepo = $this->om->getRepository('ClarolineCoreBundle:User');
        $this->resourceNodeRepo = $this->om->getRepository('ClarolineCoreBundle:Resource\ResourceNode');
        $this->resourceManager = $this->container->get('claroline.manager.resource_manager');

        // Use the create method from WorkspaceManager.
        $creationDate = new DateTime();
        $modificationDate = new DateTime();
        $creationDate->setTimestamp($workspace->getAttribute('creation_date'));
        $modificationDate->setTimestamp($workspace->getAttribute('modification_date'));
        $ds = DIRECTORY_SEPARATOR;

        $config = Configuration::fromTemplate(
            $this->templateDir . $ds . 'default.zip'
        );

        $creator = $this->getCreator($workspace);
        $config->setWorkspaceName($workspace->getAttribute('name'));
        $config->setWorkspaceCode($workspace->getAttribute('code'));
        $config->setDisplayable($workspace->getAttribute('displayable'));
        $config->setSelfRegistration($workspace->getAttribute('selfregistration'));
        $config->setSelfUnregistration($workspace->getAttribute('selfunregistration'));
        $config->setWorkspaceDescription($workspace->getAttribute('description'));
        $config->setGuid($workspace->getAttribute('guid'));
        $myWs = $this->workspaceManager->create($config, $creator);
        $nodeWorkspace = $this->resourceNodeRepo->findOneBy(array('workspace' => $myWs));

        $this->changeDate($nodeWorkspace, $creationDate, $modificationDate, $this->resourceManager);

        // Need to change the hashname of the node corresponding to the workspace.
        $this->om->startFlushSuite();
        $nodeWorkspace->setNodeHashName($workspace->getAttribute('hashname_node'));
        $this->om->endFlushSuite();

        return $myWs;
    }
}
