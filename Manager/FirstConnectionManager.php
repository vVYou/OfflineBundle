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
use Claroline\ForumBundle\Manager\Manager;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Library\Utilities\ClaroUtilities;
use Claroline\CoreBundle\Library\Security\Utilities;
use Claroline\CoreBundle\Library\Security\TokenUpdater;
use Claroline\CoreBundle\Event\StrictDispatcher;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\CoreBundle\Pager\PagerFactory;
use Claroline\OfflineBundle\SyncConstant;
use Claroline\OfflineBundle\SyncInfo;
use Claroline\OfflineBundle\Entity\UserSynchronized;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use \ZipArchive;
use \DOMDocument;
use \DOMElement;
use \DateTime;

/**
 * @DI\Service("claroline.manager.first_connection_manager")
 */

class FirstConnectionManager
{
    private $om;
    private $pagerFactory;
    private $translator;
    private $userSynchronizedRepo;
    private $userRepo;
    private $templateDir;
    private $user;
    private $ut;
    private $dispatcher;
    private $path;
    private $security;
    private $tokenUpdater;

    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "pagerFactory"   = @DI\Inject("claroline.pager.pager_factory"),
     *     "translator"     = @DI\Inject("translator"),
     *     "templateDir"    = @DI\Inject("%claroline.param.templates_directory%"),
     *     "ut"            = @DI\Inject("claroline.utilities.misc"),
     *     "dispatcher"      = @DI\Inject("claroline.event.event_dispatcher"),
     *     "security"           = @DI\Inject("security.context"),
     *     "tokenUpdater"       = @DI\Inject("claroline.security.token_updater")
     * })
     */
    public function __construct(
        ObjectManager $om,
        PagerFactory $pagerFactory,
        TranslatorInterface $translator,
        $templateDir,
        ClaroUtilities $ut,
        StrictDispatcher $dispatcher,
        SecurityContextInterface $security,
        TokenUpdater $tokenUpdater
    )
    {
        $this->om = $om;
        $this->pagerFactory = $pagerFactory;
        $this->userSynchronizedRepo = $om->getRepository('ClarolineOfflineBundle:UserSynchronized');
        $this->userRepo = $om->getRepository('ClarolineCoreBundle:User');
        $this->translator = $translator;
        $this->templateDir = $templateDir;
        $this->ut = $ut;
        $this->dispatcher = $dispatcher;
        $this->security = $security;
        
    }
    
    /*
    *   This method try to catch and create the profil of a user present in the online
    *   database.
    */
    public function retrieveProfil($username, $password)
    {   
        $new_user = new User();
        $new_user->setFirstName($profil['first_name']);
        $new_user->setLastName($profil['last_name']);
        $new_user->setUsername($profil['username']);
        $new_user->setPlainPassword($password);
        $this->userManager->createUser($new_user);
        $my_user = $this->userRepo->findOneBy(array('username' => $username));
        $this->om->startFlushSuite();
        $my_user->setExchangeToken($profil['token']);
        $this->om->endFlushSuite();      
        return true;
    }
    
}