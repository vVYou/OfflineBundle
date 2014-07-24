<?php

namespace Claroline\OfflineBundle\Manager;

use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\OfflineBundle\Entity\UserSynchronized;
use JMS\DiExtraBundle\Annotation as DI;
use \DateTime;

/**
 * @DI\Service("claroline.manager.user_sync_manager")
 */
class UserSyncManager
{
    private $om;
    private $userSynchronizedRepo;
    private $resourceManager;

    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "resourceManager"= @DI\Inject("claroline.manager.resource_manager")
     * })
     */
    public function __construct(
        ObjectManager $om,
        ResourceManager $resourceManager
    )
    {
        $this->om = $om;
        $this->userSynchronizedRepo = $om->getRepository('ClarolineOfflineBundle:UserSynchronized');
        $this->resourceManager = $resourceManager;
    }

    /**
     * Create a userSynchronized.
     * Its ID and the date of creation.
     *
     * @param \Claroline\CoreBundle\Entity\User $user
     *
     * @return \Claroline\OfflineBundle\Entity\UserSynchronized
     */
    public function createUserSynchronized(User $user)
    {
        $this->om->startFlushSuite();

        $userSynchronized = new userSynchronized($user);

        $this->om->persist($userSynchronized);
        $this->om->endFlushSuite();

        return $userSynchronized;
    }

    /*
     * @param \Claroline\CoreBundle\Entity\User $user
     */
    public function updateUserSynchronized(User $user)
    {
        $userSyncTab = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);
        $this->om->startFlushSuite();
        $userSync = $userSyncTab[0];
        $userSync->setLastSynchronization($userSync->getSentTime());
        $this->om->persist($userSync);
        $this->om->endFlushSuite();

        return $userSync;
    }

    /*
     * @param \Claroline\CoreBundle\Entity\User $user
     */
    public function updateSentTime(User $user)
    {
        $userSyncTab = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);
        $this->om->startFlushSuite();
        $userSync = $userSyncTab[0];
        $now = new DateTime();
        $userSync->setSentTime($now);
        $this->om->persist($userSync);
        $this->om->endFlushSuite();

        return $userSync;
    }

    /*
     * @param \Claroline\OfflineBundle\Entity\UserSynchronized $userSync
     */
    public function updateUserSync(UserSynchronized $userSync)
    {
        $this->om->startFlushSuite();
        $this->om->persist($userSync);
        $this->om->endFlushSuite();
    }

    public function resetSync($user)
    {
        $userSyncTab = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);
        $this->om->startFlushSuite();
        $userSync = $userSyncTab[0];
        $userSync->setStatus(UserSynchronized::SUCCESS_SYNC);
        $this->om->persist($userSync);
        $this->om->endFlushSuite();
    }
}
