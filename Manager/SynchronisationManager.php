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
use Claroline\CoreBundle\Library\Security\PlatformRoles;
use Claroline\CoreBundle\Manager\RoleManager;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\OfflineBundle\Entity\UserSynchronized;
use Claroline\OfflineBundle\Model\DisableListenerI;
use Claroline\OfflineBundle\Model\DisableListener;
use Symfony\Component\DependencyInjection\ContainerInterface;
use JMS\DiExtraBundle\Annotation as DI;
use \DateTime;

/**
 * @DI\Service("claroline.manager.synchronisation_manager")
 */

// This manager implements the global interaction between the online and the offline plateform
// It allows the synchronisation process to restart from where it stops
// For more documentation on the global process see our thesis "Chapter 5 : le processus global"
class SynchronisationManager
{
    private $container;
	private $om;
    private $creationManager;
    private $transferManager;
    private $userSyncManager;
    private $loadingManager;
    private $roleManager;
    private $userSynchronizedRepo;
    private $syncUpDir;
    private $syncDownDir;

    /**
     * Constructor.
     * @DI\InjectParams({     
	 *     "container"      = @DI\Inject("service_container"),
     *      "om"              = @DI\Inject("claroline.persistence.object_manager"),
     *      "creationManager" = @DI\Inject("claroline.manager.creation_manager"),
     *      "loadingManager"  = @DI\Inject("claroline.manager.loading_manager"),
     *      "userSyncManager" = @DI\Inject("claroline.manager.user_sync_manager"),
     *      "transferManager" = @DI\Inject("claroline.manager.transfer_manager"),
     *      "roleManager"     = @DI\Inject("claroline.manager.role_manager"),
     *      "syncUpDir"       = @DI\Inject("%claroline.synchronisation.up_directory%"),
     *      "syncDownDir"     = @DI\Inject("%claroline.synchronisation.down_directory%")
     * })
     */
    public function __construct(
		ContainerInterface   $container,
        ObjectManager $om,
        CreationManager $creationManager,
        LoadingManager $loadingManager,
        UserSyncManager $userSyncManager,
        TransferManager $transferManager,
        RoleManager $roleManager,
        $syncUpDir,
        $syncDownDir
    )
    {
		$this->container = $container;
        $this->om = $om;
        $this->creationManager = $creationManager;
        $this->transferManager = $transferManager;
        $this->userSyncManager = $userSyncManager;
        $this->loadingManager = $loadingManager;
        $this->roleManager = $roleManager;
        $this->userSynchronizedRepo = $om->getRepository('ClarolineOfflineBundle:UserSynchronized');
        $this->syncUpDir = $syncUpDir;
        $this->syncDownDir = $syncDownDir;
    }

    /*
    *   @param User $user
    *   @param UserSynchronized $userSync
    *
    *   This method determine where the execution has to restart.
    *   This is based on the status from the UserSynchronized entity.
    */
    public function synchroniseUser(User $user, UserSynchronized $userSync)
    {
        $infoToDisplay = null;
        $status = $userSync->getStatus();
        switch ($status) {
            // Last synchronisation was well ended.
            case UserSynchronized::SUCCESS_SYNC :
                // restart from the begining
                $infoToDisplay = $this->step1Create($user, $userSync);
                break;
            // Has a synchronisation archive
            case UserSynchronized::STARTED_UPLOAD :
                // Where did we stopped the transmission ?
                $fragmentNumber = $this->transferManager->getLastFragmentUploaded($userSync->getFilename(), $user);
                // Restart uploading from the last stop
                $infoToDisplay = $this->step2Upload($user, $userSync, $userSync->getFilename(), $fragmentNumber+1);
                break;
            // Uploading failed
            case UserSynchronized::FAIL_UPLOAD :
                // Restart all the upload
                $infoToDisplay = $this->step2Upload($user, $userSync, $userSync->getFilename());
                break;
            // Upload finished
            case UserSynchronized::SUCCESS_UPLOAD :
                // Let's download from the online
                $infoToDisplay = $this->step3Download($user, $userSync, $userSync->getFilename());
                break;
            // Download fail
            case UserSynchronized::FAIL_DOWNLOAD :
                // Restart download
                $fragmentNumber = $this->getDownloadStop($userSync->getFilename(), $user);
                $infoToDisplay = $this->step3Download($user, $userSync, $userSync->getFilename(), null, $fragmentNumber);
                break;
            // Download finished
            case UserSynchronized::SUCCESS_DOWNLOAD :
                $toLoad = $this->syncUpDir.$user->getId().'/sync_'.$userSync->getFilename().'.zip';
                // Load the online synchronisation archive on the plateform
                $infoToDisplay = $this->step4Load($user, $userSync, $toLoad);
                break;
        }

        return $infoToDisplay;
    }

    // Method implementing the first step of the global process
    // Creates the synchronisation archive and transfer it to the second step
    private function step1Create(User $user, UserSynchronized $userSync)
    {
        // $toUpload will be the filename of the synchronisation archive created
        $toUpload = $this->creationManager->createSyncZip($user, $userSync->getLastSynchronization()->getTimestamp());
        // Save it in UserSync in case of restart needed
        $userSync->setFilename($toUpload);
        $userSync->setStatus(UserSynchronized::STARTED_UPLOAD);
        // Save the datetime of the end of the creation
        $now = new DateTime();
        $userSync->setSentTime($now);
        $this->userSyncManager->updateUserSync($userSync);
        // Go to step 2
        return $this->step2Upload($user, $userSync, $toUpload);
    }

    // Method implementing the second step of the global process
    // Upload the synchronisation archive to the online plateform
    private function step2Upload(User $user, UserSynchronized $userSync, $filename, $fragmentNumber = 0)
    {
        if ($filename == null) {
            $this->step1Create($user, $userSync);
        } else {
            // $toDownload will be the synchronisation archive of the online plateform.
            // this information is received when the upload is finished.
            $toDownload = $this->transferManager->uploadArchive($filename, $user, $fragmentNumber);
            //Saves informations and update status
            $userSync->setFilename($toDownload['hashname']);
            $userSync->setStatus(UserSynchronized::SUCCESS_UPLOAD);
            $this->userSyncManager->updateUserSync($userSync);
            //Go to step 3
            $syncInfo = $this->step3Download($user, $userSync, $toDownload['hashname'], $toDownload['totalFragments']);
            // Clean the directory when done (online the offline)
            // $this->transferManager->deleteFile($user,substr($filename, strlen($filename)-40, 36), $this->syncUpDir);
            $this->transferManager->deleteFile($user,substr($filename, strlen($filename)-40, 36), 'UP');
            unlink($filename);

            return $syncInfo;
        }
    }

    // Method implementing the third step of the global process
    // Download the synchronisation archive of the online plateform
    private function step3Download(User $user, UserSynchronized $userSync, $filename, $totalFragments = null, $fragmentNumber = 0)
    {
        try {
            if ($totalFragments == null) {
                $totalFragments = $this->transferManager->getNumberOfFragmentsOnline($filename, $user);
            }
            // The file doesn't exist online
            if ($totalFragments == -1) {
                // Erase filename, set status and restart
                $userSync->setFilename(null);
                $userSync->setStatus(UserSynchronized::FAIL_UPLOAD);
                $this->userSyncManager->updateUserSync($userSync);
                $this->synchroniseUser($user, $userSync);
            } else {
                // $toLoad will be the downloaded from the online plateform
                $toLoad = $this->transferManager->downloadArchive($filename, $totalFragments, $fragmentNumber, $user);
                // Update userSync status
                $userSync->setStatus(UserSynchronized::SUCCESS_DOWNLOAD);
                $this->userSyncManager->updateUserSync($userSync);
                // Go to step 4
                $syncInfo = $this->step4Load($user, $userSync, $toLoad);
                // Clean the files when done
                // $this->transferManager->deleteFile($user, $filename, $this->syncDownDir);
                $this->transferManager->deleteFile($user, $filename, 'DOWN');
                unlink($toLoad);

                return $syncInfo;
            }
        } catch (DownloadFailsException $e) {
            $this->userSyncManager->resetSync($user);
            throw new SynchronisationFailsException("The synchronisation fails, can't download file from online server");
        }
    }

    // Method implementing the fourth step of the global process
    // It will load the downloaded from the archive into
    private function step4Load(User $user, UserSynchronized $userSync, $filename)
    {
        // Load synchronisation archive ($filename) in offline database
		// Disable Listener
	$disableListener = DisableListenerI::getInstance();
$disableListener->setDisable(true);
        $this->roleManager->associateUserRole($user, $this->roleManager->getRoleByName(PlatformRoles::ADMIN), false, true);
        $loadArray = $this->loadingManager->loadZip($filename, $user);		
		//Enable Listener
$disableListener->setDisable(false);
        if (!$userSync->isAdmin()) {
            $this->roleManager->dissociateRole($user, $this->roleManager->getRoleByName(PlatformRoles::ADMIN));
        }
        $userSync->setStatus(UserSynchronized::SUCCESS_SYNC);
        $userSync->setLastSynchronization($userSync->getSentTime());
        $this->userSyncManager->updateUserSync($userSync);

        return $loadArray['infoArray'];
    }

    // This method has to return the last fragment uploaded on the online plateform
    // If there is any, return -1
    public function getDownloadStop($filename, $user)
    {
        $stop = true;
        $index = -1;
        while ($stop) {
            $file = $this->syncUpDir.$user->getId().'/'.$filename.'_'.($index + 1);
            if (! file_exists($file)) {
                $stop=false;
            } else {
                $index++;
            }
        }

        return $index;
    }
}
