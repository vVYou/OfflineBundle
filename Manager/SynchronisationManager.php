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

use \DateTime;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\OfflineBundle\SyncConstant;
use Claroline\OfflineBundle\Entity\UserSynchronized;
use Claroline\OfflineBundle\Manager\LoadingManager;
use Claroline\OfflineBundle\Manager\CreationManager;
use Claroline\OfflineBundle\Manager\UserSyncManager;
use JMS\DiExtraBundle\Annotation as DI;

/**
 * @DI\Service("claroline.manager.synchronisation_manager")
 */

class SynchronisationManager
{
    private $om;
    private $creationManager;
    private $transferManager;
    private $userSyncManager;
    private $loadingManager;

    /**
     * Constructor.
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "creationManager"    = @DI\Inject("claroline.manager.creation_manager"),
     *     "loadingManager" = @DI\Inject("claroline.manager.loading_manager"),
     *     "userSyncManager" = @DI\Inject("claroline.manager.user_sync_manager"),
     *     "transferManager" = @DI\Inject("claroline.manager.transfer_manager")
     * })
     */
    public function __construct(
        ObjectManager $om,
        CreationManager $creationManager,
        LoadingManager $loadingManager,
        UserSyncManager $userSyncManager,
        TransferManager $transferManager
    )
    {
        $this->om = $om;
        $this->creationManager = $creationManager;
        $this->transferManager = $transferManager;
        $this->userSyncManager = $userSyncManager;
        $this->loadingManager = $loadingManager;
    }
    
    /*
    *   @param User $user
    *   @param UserSynchronized $userSync
    */
    public function synchroniseUser(User $user, UserSynchronized $userSync)
    {
        $status = $userSync->getStatus();
        echo "begin with the switch, status is ".$status."<br/>";
        switch($status){
            case UserSynchronized::SUCCESS_SYNC :
                $this->step1Create($user, $userSync);
                break;
            case UserSynchronized::STARTED_UPLOAD :
                $packetNum = $this->transferManager->getLastPacketUploaded($userSync->getFilename(), $user);
                echo "last uploaded : ".$packetNum."<br/>";
                $this->step2Upload($user, $userSync, $userSync->getFilename(), $packetNum+1);
                break;
            case UserSynchronized::FAIL_UPLOAD :
                $this->step2Upload($user, $userSync, $userSync->getFilename());
                break;
            case UserSynchronized::SUCCESS_UPLOAD :
                $this->step3Download($user, $userSync, $userSync->getFilename());
                break;
            case UserSynchronized::FAIL_DOWNLOAD : 
                $packetNum = $this->getDownloadStop($userSync->getFilename(), $user);
                $this->step3Download($user, $userSync, $userSync->getFilename(), null, $packetNum);
                break;
            case UserSynchronized::SUCCESS_DOWNLOAD :
                $toLoad = SyncConstant::SYNCHRO_UP_DIR.$user->getId().'/sync_'.$userSync->getFilename().'.zip';
                $this->step4Load($user, $userSync, $toLoad);
                break;
        }
    }
    
    public function step1Create(User $user, UserSynchronized $userSync)
    {
        echo "I 'm at step 1<br/>";
        $toUpload = $this->creationManager->createSyncZip($user);
        $userSync->setFilename($toUpload);
        $userSync->setStatus(UserSynchronized::STARTED_UPLOAD);
        $this->userSyncManager->updateUserSync($userSync);
        echo "j'ai créé ceci ".$toUpload."<br/>";
        $this->step2Upload($user, $userSync, $toUpload);
    }
    
    public function step2Upload(User $user, UserSynchronized $userSync, $filename, $packetNum = 0)
    {
        echo "I 'm at step 2 ".$packetNum."<br/>";
        $toDownload = $this->transferManager->uploadZip($filename, $user, $packetNum);
        $userSync->setFilename($toDownload['hashname']);
        $userSync->setStatus(UserSynchronized::SUCCESS_UPLOAD);
        $this->userSyncManager->updateUserSync($userSync);
        echo "je vais telecharger ceci ".$toDownload['hashname']."<br/>";
        $this->step3Download($user, $userSync, $toDownload['hashname'], $toDownload['nPackets']);
    }
    
    public function step3Download(User $user, UserSynchronized $userSync, $filename, $nPackets = null, $packetNum = 0)
    {
        echo "I 'm at step 3<br/>";
        if($nPackets == null){
            echo "nPackets null <br/>";
            $nPackets = $this->transferManager->getOnlineNumberOfPackets($filename, $user);
            //TODO if nPackets = -1; status=FAIL; $this->synchronise
            echo "nPackets = ".$nPackets."<br/>";
        }
        $toLoad = $this->transferManager->getSyncZip($filename, $nPackets, $packetNum, $user);
        $userSync->setStatus(UserSynchronized::SUCCESS_DOWNLOAD);
        $this->userSyncManager->updateUserSync($userSync);
        echo "il me reste donc ceci a charger ".$toLoad."<br/>";
        $this->step4Load($user, $userSync, $toLoad);
    }
    
    public function step4Load(User $user, UserSynchronized $userSync, $filename)
    {
        echo "I 'm at step 4<br/> with filename ".$filename.'<br/>';
        $this->loadingManager->loadZip($filename, $user);
        $userSync->setStatus(UserSynchronized::SUCCESS_SYNC);
        $now = new DateTime();
        $userSync->setLastSynchronization($now);
        $this->userSyncManager->updateUserSync($userSync);
    }
    
    public function getDownloadStop($filename, $user)
    {  /*
        * Cette fonction doit retourner le dernier paquet téléchargé sur l'ordinateur;
        * Si aucun fichier n'est trouvé avec le nom envoyé, -1 est retourné
        */
        
        $stop = true;
        $index = -1;
        while($stop)
        {
            $file = SyncConstant::SYNCHRO_UP_DIR.$user->getId().'/'.$filename.'_'.($index + 1);
            if(! file_exists($file)){
                $stop=false;
            }else{
                $index++;
            }
        }
        return $index;
    }
}