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
use Claroline\CoreBundle\Entity\Resource\ResourceNode;
use Claroline\CoreBundle\Entity\Workspace\Workspace;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\CoreBundle\Library\Utilities\ClaroUtilities;
use Claroline\OfflineBundle\Entity\UserSynchronized;
use Claroline\OfflineBundle\Manager\Exception\AuthenticationException;
use Claroline\OfflineBundle\Manager\Exception\ProcessSyncException;
use Claroline\OfflineBundle\Manager\Exception\ServeurException;
use Claroline\OfflineBundle\Manager\Exception\PageNotFoundException;
use Claroline\OfflineBundle\Manager\Exception\SynchronisationFailsException;
use Claroline\CoreBundle\Manager\UserManager;
use Claroline\OfflineBundle\SyncConstant;
use Symfony\Component\Translation\TranslatorInterface;
use JMS\DiExtraBundle\Annotation as DI;
use \Buzz\Browser;
use \Buzz\Client\Curl;
use \Buzz\Exception\ClientException;

/**
 * @DI\Service("claroline.manager.transfer_manager")
 */
// This Manager handle the treatment of the requests between the online and the offline plateform
class TransferManager
{
    private $om;
    private $translator;
    private $userSynchronizedRepo;
    private $resourceNodeRepo;
    private $userRepo;
    private $loadingManager;
    private $resourceManager;
    private $creationManager;
    private $userSyncManager;
    private $userManager;
    private $ut;

    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "om"                 = @DI\Inject("claroline.persistence.object_manager"),
     *     "translator"         = @DI\Inject("translator"),
     *     "resourceManager"    = @DI\Inject("claroline.manager.resource_manager"),
     *     "creationManager"    = @DI\Inject("claroline.manager.creation_manager"),
     *     "loadingManager"     = @DI\Inject("claroline.manager.loading_manager"),
     *     "userManager"        = @DI\Inject("claroline.manager.user_manager"),
     *     "userSyncManager"    = @DI\Inject("claroline.manager.user_sync_manager"),
     *     "ut"                 = @DI\Inject("claroline.utilities.misc")
     * })
     */
    public function __construct(
        ObjectManager $om,
        TranslatorInterface $translator,
        ResourceManager $resourceManager,
        CreationManager $creationManager,
        LoadingManager $loadingManager,
        UserManager $userManager,
        UserSyncManager $userSyncManager,
        ClaroUtilities $ut
    )
    {
        $this->om = $om;
        $this->userSynchronizedRepo = $om->getRepository('ClarolineOfflineBundle:UserSynchronized');
        $this->userRepo = $om->getRepository('ClarolineCoreBundle:User');
        $this->resourceNodeRepo = $om->getRepository('ClarolineCoreBundle:Resource\ResourceNode');
        $this->translator = $translator;
        $this->resourceManager = $resourceManager;
        $this->userManager = $userManager;
        $this->creationManager = $creationManager;
        $this->loadingManager = $loadingManager;
        $this->userSyncManager = $userSyncManager;
        $this->ut = $ut;
    }

    /*
    *******   METHODS EXECUTED OFFLINE *******
    */
    
    /*
    *   @param User $user
    *   @param $toTransfer is the filname of the archive to upload on the online plateform
    *
    *   This method will upload the file given in $toTransfer on the online plateform from $fragmentNumber to the end of the file
    *   @return hashname of the online synchronisation archive.
    *   When the archive is completely uploaded online, it is loaded on the online plateform.
    *   Then the online plateform create it's own synchronisation archive and give back its name so it can be downloaded later
    */
    public function uploadArchive($toTransfer, User $user, $fragmentNumber, $firstTime = true)
    {  // ATTENTION, droits d'ecriture de fichier >> TEST LINUX

        $browser = $this->getBrowser();
        $metadatas = $this->getMetadataArray($user, $toTransfer);
        $totalFragments = $metadatas['nPackets'];
        $responseContent = "";
        $status = 200;

        try {
            while ($fragmentNumber < $totalFragments && $status == 200) {
                $metadatas['file'] = base64_encode($this->getFragment($fragmentNumber, $toTransfer, $user));
                $metadatas['packetNum'] = $fragmentNumber;
                // Execute the post request sending informations online
                $reponse = $browser->post(SyncConstant::PLATEFORM_URL.'/transfer/uploadArchive', array(), json_encode($metadatas));
                $responseContent = $reponse->getContent();
                echo "CONTENT received : ".$responseContent."<br/>";
                $status = $reponse->getStatusCode();
                $responseContent = (array) json_decode($responseContent);
                $fragmentNumber ++;
            }
            // Control result of the requests
            $this->analyseStatusCode($status);

            return $responseContent;
        } catch (ClientException $e) {
            // In case of timeout try again once
            if (($e->getCode() == CURLE_OPERATION_TIMEDOUT) && $firstTime) {
                $this->uploadArchive($toTransfer, $user, $fragmentNumber, false);
            } else {
                throw $e;
            }
        }
    }
    
    // This method will download the file with the name $hashToGet from the online plateform
    // It will begin at $fragmentNumber and go up to the end of the file
    public function downloadArchive($hashToGet, $totalFragments, $fragmentNumber, $user, $firstTime = true)
    {
        $browser = $this->getBrowser();
        $metadatas = array(
            'token' => $user->getExchangeToken(),
            'hashname' => $hashToGet,
            'nPackets' => $totalFragments,
            'packetNum' => 0);
        $processContent = null;
        $status = 200;
        try {
            while ($fragmentNumber < $totalFragments && $status == 200) {
                // echo 'doing packet '.$fragmentNumber.'<br/>';
                $metadatas['packetNum'] = $fragmentNumber;
                $reponse = $browser->post(SyncConstant::PLATEFORM_URL.'/transfer/getzip', array(), json_encode($metadatas));
                $content = $reponse->getContent();
                echo "CONTENT received : ".$content."<br/>";
                $status = $reponse->getStatusCode();
                $processContent = $this->processSyncRequest((array) json_decode($content), false);
                $fragmentNumber++;
            }
            $this->analyseStatusCode($status);

            return $processContent['zip_name'];
        } catch (ClientException $e) {
            if (($e->getCode() == CURLE_OPERATION_TIMEDOUT) && $firstTime) {
                $this->downloadArchive($hashToGet, $totalFragments, $fragmentNumber, $user, false);
            } else {
                throw $e;
            }
        }
    } 
    
    // Method to analyse the status received after a request on the online server
    // @return throw exception matching with the errors status
    //      If no error (or default) return true
    private function analyseStatusCode($status)
    {   
        switch ($status) {
            case 200:
                return true;
            case 401:
                throw new AuthenticationException();
                return false;
            case 404:
                throw new PageNotFoundException();
            case 424:
                throw new ProcessSyncException();
                return false;
            case 500:
                throw new ServeurException();
                return false;
            default:
                return true;
        }
    }
    
    /*
    *******   METHODS EXECUTED OFFLINE *******
    */
    
    
    /*
    *******   METHODS USED IN BOTH SIDES *******
    */

    public function getTotalFragments($filename)
    {
        if (! file_exists($filename)) {
            return -1;
        } else {
            return (int) (filesize($filename)/SyncConstant::MAX_PACKET_SIZE)+1;
        }
    }

    public function getMetadataArray($user, $filename)
    {
        if (!file_exists($filename)) {
            $this->userSyncManager->resetSync($user);
            throw new SynchronisationFailsException();
        } else {
            return array(
                'token' => $user->getExchangeToken(),
                'hashname' => substr($filename, strlen($filename)-40, 36),
                'nPackets' => $this->getTotalFragments($filename),
                'checksum' => hash_file( "sha256", $filename)
            );
        }
    }

    public function getFragment($fragmentNumber, $filename, $user)
    {
        if (!file_exists($filename)) {
            $this->userSyncManager->resetSync($user);
            throw new SynchronisationFailsException();
        } else {
            $fileSize = filesize($filename);
            $handle = fopen($filename, 'r');
            if ($fragmentNumber*SyncConstant::MAX_PACKET_SIZE > $fileSize || !$handle) {
                return null;
            } else {
                $position = $fragmentNumber*SyncConstant::MAX_PACKET_SIZE;
                fseek($handle, $position);
                if ($fileSize > $position+SyncConstant::MAX_PACKET_SIZE) {
                    $data = fread($handle, SyncConstant::MAX_PACKET_SIZE);
                } else {
                    $data = fread($handle, $fileSize-$position);
                }
                if(!fclose($handle)) return null;

                return $data;
            }
        }
    }

    public function processSyncRequest($content, $createSync)
    {
        //TODO Verifier le fichier entrant (dependency injections)
        //TODO verification de l'existance du dossier
        $user = $this->userRepo->findOneBy(array('exchangeToken' => $content['token']));
        $dir = SyncConstant::SYNCHRO_UP_DIR.$user->getId();
        if (!is_dir($dir)) {
            mkdir($dir, 0777);
        }
        $partName = $dir.'/'.$content['hashname'].'_'.$content['packetNum'];
        $partFile = fopen($partName, 'w+');
        if(!$partFile) return array("status" => 424);
        $write = fwrite($partFile, base64_decode($content['file']));
        if($write === false) return array("status" => 424);
        if(!fclose($partFile)) return array("status" => 424);
        if ($content['packetNum'] == ($content['nPackets']-1)) {
            return $this->endExchangeProcess($content, $createSync);
        }

        return array(
            "status" => 200
        );
    }

    public function endExchangeProcess($content, $createSync)
    {
        $zipName = $this->assembleParts($content);
        if ($zipName != null) {
            //Load archive
            $user = $this->userRepo->findOneBy(array('exchangeToken' => $content['token'])); //loadUserByUsername($content['username']);
            $loadingResponse = $this->loadingManager->loadZip($zipName, $user);
            if ($createSync) {
                //Create synchronisation
                $toSend = $this->creationManager->createSyncZip($user, $loadingResponse['synchronizationDate']);
                // $this->userSyncManager->updateUserSynchronized($user);
                $metaDataArray = $this->getMetadataArray($user, $toSend);
                $metaDataArray["status"] = 200;

                return $metaDataArray;
            } else {
                return array(
                    "zip_name" => $zipName,
                    "status" => 200
                );
            }
        } else {
            return array(
                'status' => 424
            );
        }
    }

    public function assembleParts($content)
    {
        $user = $this->userRepo->findOneBy(array('exchangeToken' => $content['token']));
        $zipName = SyncConstant::SYNCHRO_UP_DIR.$user->getId().'/sync_'.$content['hashname'].'.zip';
        $zipFile = fopen($zipName, 'w+');
        if(!$zipFile) return null;
        for ($i = 0; $i<$content['nPackets']; $i++) {
            $partName = SyncConstant::SYNCHRO_UP_DIR.$user->getId().'/'.$content['hashname'].'_'.$i;
            $partFile = fopen($partName, 'r');
            if(!$partFile) return null;
            $write = fwrite($zipFile, fread($partFile, filesize($partName)));
            if($write === false) return null;
            if(!fclose($partFile)) return null;
            unlink($partName);
        }
        if(!fclose($zipFile))return null;
        if (hash_file( "sha256", $zipName) == $content['checksum']) {
            // echo "CHECKSUM SUCCEED <br/>";
            return $zipName;
        } else {
            // echo "CHECKSUM FAIL <br/>";
            return null;
        }
    }

    public function confirmRequest($user)
    {
        $browser = $this->getBrowser();

        $reponse = $browser->get(SyncConstant::PLATEFORM_URL.'/transfer/confirm/'.$user->getId());
        if ($reponse) {
            echo "HE CONFIRM RECEIVE !<br/>";
        }
    }

    public function getUserInfo($username, $password, $firstTime = true)
    {
        // The given password has to be clear, without any encryption, the security is made by the HTTPS communication
        echo $username."<br/>";
        echo $password."<br/>";
        $browser = $this->getBrowser();

        //TODO remove hardcode
        $contentArray = array(
            'username' => $username,
            'password' => $password
        );
        // echo "content array : ".json_encode($contentArray).'<br/>';
        try {
            $response = $browser->post(SyncConstant::PLATEFORM_URL.'/sync/user', array(), json_encode($contentArray));
            $status = $this->analyseStatusCode($response->getStatusCode());
            $result = (array) json_decode($response->getContent());
            echo sizeof($result).'<br/>';
            echo $response->getStatusCode().'<br/>';
            echo $status.'<br/>';
            // if (sizeof($result) > 1) {
            $this->retrieveProfil($username, $password, $result);
            // echo $result['ws_resnode'];
            // foreach($result as $elem)
            // {
                // echo $elem.'</br>';
            // }
                // return true;
            // }
            // else{
                // return false;
            // }
        } catch (ClientException $e) {
            if (($e->getCode() == CURLE_OPERATION_TIMEDOUT) && $firstTime) {
                // echo "Oh mon dieu, un timeout";
                $this->getUserInfo($username, $password, false);
            } else {
                throw $e;
            }
        }
    }

    /*
    *   This method try to catch and create the profil of a user present in the online
    *   database.
    */
    public function retrieveProfil($username, $password, $result)
    {
        $new_user = new User();
        $new_user->setFirstName($result['first_name']);
        $new_user->setLastName($result['last_name']);
        $new_user->setUsername($result['username']);
        $new_user->setMail($result['mail']);
        $new_user->setPlainPassword($password);
        $this->userManager->createUser($new_user);
        $my_user = $this->userRepo->findOneBy(array('username' => $username));
        $ws_perso = $my_user->getPersonalWorkspace();
        $user_ws_rn = $this->resourceNodeRepo->findOneBy(array('workspace' => $ws_perso, 'parent' => NULL));
        $this->om->startFlushSuite();
        $my_user->setExchangeToken($result['token']);
        $ws_perso->setGuid($result['ws_perso']);
        $user_ws_rn->setNodeHashName($result['ws_resnode']);
        $this->om->endFlushSuite();
        $this->userSyncManager->createUserSynchronized($my_user);

        // Creation of the file inside the offline database
        file_put_contents(SyncConstant::PLAT_CONF, $result['username']);

    }

    private function getBrowser()
    {
        $client = new Curl();
        $client->setTimeout(60);
        $browser = new Browser($client);

        return $browser;
    }

    public function getLastPacketUploaded($filename, $user)
    {
        $browser = $this->getBrowser();
        $contentArray = array(
            'username' => $user->getUsername(),
            'id' => $user->getId(),
            'token' => $user->getExchangeToken(),
            'hashname' => substr($filename, strlen($filename)-40, 36)
        );
        $response = $browser->post(SyncConstant::PLATEFORM_URL.'/sync/lastUploaded', array(), json_encode($contentArray));
        // echo "CONTENT received : ".$response->getContent()."<br/>";
        if ($this->analyseStatusCode($response->getStatusCode())) {
            $responseArray = (array) json_decode($response->getContent());

            return $responseArray['lastUpload'];
        } else {
            return -1;
        }
    }

    public function getOnlineNumberOfPackets($filename, $user)
    {
        $browser = $this->getBrowser();
        $contentArray = array(
            // 'username' => $user->getUsername(),
            // 'id' => $user->getId(),
            'token' => $user->getExchangeToken(),
            'hashname' => $filename
        );
        $response = $browser->post(SyncConstant::PLATEFORM_URL.'/sync/numberOfPacketsToDownload', array(), json_encode($contentArray));
        echo "Content received <br/>".$response->getContent()."<br/>";
        $this->analyseStatusCode($response->getStatusCode());
        $responseArray = (array) json_decode($response->getContent());

        return $responseArray['nPackets'];
    }

    public function unlinkSynchronisationFile($content, $user)
    {
        // echo 'je delete : '.$content['dir'].$user->getId().'/sync_'.$content['hashname'].'.zip<br/>';
        unlink($content['dir'].$user->getId().'/sync_'.$content['hashname'].'.zip');
        $content['status'] = 200;

        return $content;
    }



    public function deleteFile($user, $filename, $dir, $firstTime=true)
    {
        $browser = $this->getBrowser();
        $contentArray = array(
            'token' => $user->getExchangeToken(),
            'hashname' => $filename,
            'dir' => $dir
        );
        try {
            // echo "Je tente de delete ".$filename." dans ".$dir." pour ".$user->getExchangeToken()."<br/>";
            $response = $browser->post(SyncConstant::PLATEFORM_URL.'/sync/unlink', array(), json_encode($contentArray));
            $this->analyseStatusCode($response->getStatusCode());
            // echo "Here is my response ".$response->getContent().'<br/>';
        } catch (ClientException $e) {
            if (($e->getCode() == CURLE_OPERATION_TIMEDOUT) && $firstTime) {
                // echo "Oh mon dieu, un timeout";
                $this->deleteFile($user, $filename, $dir, false);
            } else {
                throw $e;
            }
        }
    }
}
