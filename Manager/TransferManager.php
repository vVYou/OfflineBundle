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
use Claroline\CoreBundle\Manager\RoleManager;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\CoreBundle\Library\Utilities\ClaroUtilities;
use Claroline\CoreBundle\Library\Security\PlatformRoles;
use Claroline\OfflineBundle\Entity\UserSynchronized;
use Claroline\OfflineBundle\Manager\Exception\AuthenticationException;
use Claroline\OfflineBundle\Manager\Exception\ProcessSyncException;
use Claroline\OfflineBundle\Manager\Exception\ServeurException;
use Claroline\OfflineBundle\Manager\Exception\PageNotFoundException;
use Claroline\OfflineBundle\Manager\Exception\SynchronisationFailsException;
use Claroline\CoreBundle\Manager\UserManager;
use Claroline\OfflineBundle\Model\SyncConstant;
use Symfony\Component\Translation\TranslatorInterface;
use JMS\DiExtraBundle\Annotation as DI;
use \Buzz\Browser;
use \Buzz\Client\Curl;
use \Buzz\Exception\ClientException;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Parser;

/**
 * @DI\Service("claroline.manager.transfer_manager")
 */
// This Manager handle the process of the requests between the online and the offline plateform
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
    private $roleManager;
    private $ut;
    private $yaml_dump;
    private $yaml_parser;

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
     *     "roleManager"        = @DI\Inject("claroline.manager.role_manager"),
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
        RoleManager $roleManager,
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
        $this->roleManager = $roleManager;
        $this->ut = $ut;
        $this->yaml_dump = new Dumper();
        $this->yaml_parser = new Parser();
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

        $url = $this->getUserUrl($user);
        $browser = $this->getBrowser();
        $metadatas = $this->getMetadataArray($user, $toTransfer);
        $totalFragments = $metadatas['totalFragments'];
        $responseContent = "";
        $status = 200;

        try {
            ini_set('max_execution_time', 0);
            while ($fragmentNumber < $totalFragments && $status == 200) {
                $metadatas['file'] = base64_encode($this->getFragment($fragmentNumber, $toTransfer, $user));
                $metadatas['fragmentNumber'] = $fragmentNumber;
                // Execute the post request sending informations online
                $reponse = $browser->post($url.'/transfer/uploadArchive', array(), json_encode($metadatas));
                $responseContent = $reponse->getContent();
                // echo "Content <br/>".$responseContent."<br/>";
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
        $url = $this->getUserUrl($user);
        $browser = $this->getBrowser();
        $metadatas = array(
            'token' => $user->getExchangeToken(),
            'hashname' => $hashToGet,
            'totalFragments' => $totalFragments,
            'fragmentNumber' => 0);
        $processContent = null;
        $status = 200;
        try {
            ini_set('max_execution_time', 0);
            while ($fragmentNumber < $totalFragments && $status == 200) {
                $metadatas['fragmentNumber'] = $fragmentNumber;
                $reponse = $browser->post($url.'/transfer/getzip', array(), json_encode($metadatas));
                $content = $reponse->getContent();
                // echo "Content <br/>".$content."<br/>";
                $status = $reponse->getStatusCode();
                $this->analyseStatusCode($status);
                $processContent = $this->processSyncRequest((array) json_decode($content), false);
                $status = $processContent['status'];
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
            case $status/100 == 2:
                return true;
            case 401:
                throw new AuthenticationException();
                break;
            case 404:
                throw new PageNotFoundException();
                break;
            case 424:
                throw new ProcessSyncException();
                break;
            case 500:
                throw new ServeurException();
                break;
            default:
                //TODO Personalise error message with status
                throw new SynchronisationFailsException();
                return false;
        }
    }

    // This Method returns the number of the last fragment of the file $filename uploaded on the online plateform
    // If the file was not yet uploaded it returns -1
    public function getLastFragmentUploaded($filename, $user)
    {
        $url = $this->getUserUrl($user);
        $browser = $this->getBrowser();
        $contentArray = array(
            'token' => $user->getExchangeToken(),
            'hashname' => substr($filename, strlen($filename)-40, 36)
        );
        $response = $browser->post($url.'/sync/lastUploaded', array(), json_encode($contentArray));
        if ($this->analyseStatusCode($response->getStatusCode())) {
            $responseArray = (array) json_decode($response->getContent());

            return $responseArray['lastUpload'];
        } else {
            return -1;
        }
    }

    // This method contact the online plateform and returns the number of fragment requier to download $filename
    // If the file doesn't exists it returns -1
    public function getNumberOfFragmentsOnline($filename, $user)
    {
        $url = $this->getUserUrl($user);
        $browser = $this->getBrowser();
        $contentArray = array(
            'token' => $user->getExchangeToken(),
            'hashname' => $filename
        );
        $response = $browser->post($url.'/sync/numberOfPacketsToDownload', array(), json_encode($contentArray));
        $this->analyseStatusCode($response->getStatusCode());
        $responseArray = (array) json_decode($response->getContent());

        return $responseArray['totalFragments'];
    }

    // This method is used to contact the online plateform and request to delete $filename
    public function deleteFile($user, $filename, $dir, $firstTime=true)
    {
        $url = $this->getUserUrl($user);
        $browser = $this->getBrowser();
        $contentArray = array(
            'token' => $user->getExchangeToken(),
            'hashname' => $filename,
            'dir' => $dir
        );
        try {
            $response = $browser->post($url.'/sync/unlink', array(), json_encode($contentArray));
            $this->analyseStatusCode($response->getStatusCode());
        } catch (ClientException $e) {
            if (($e->getCode() == CURLE_OPERATION_TIMEDOUT) && $firstTime) {
                $this->deleteFile($user, $filename, $dir, false);
            } else {
                throw $e;
            }
        }
    }

    // Retruns the informations of the user in order to retrieve his profile.
    public function getUserInfo($username, $password, $url, $firstTime = true)
    {
        $browser = $this->getBrowser();
        $contentArray = array(
            'username' => $username,
            'password' => $password
        );
        try {
            $response = $browser->post($url.'/sync/user', array(), json_encode($contentArray));
            $status = $this->analyseStatusCode($response->getStatusCode());
            $result = (array) json_decode($response->getContent());
            $this->retrieveProfil($username, $password, $result, $url);
        } catch (ClientException $e) {
            if (($e->getCode() == CURLE_OPERATION_TIMEDOUT) && $firstTime) {
                $this->getUserInfo($username, $password, $url, false);
            } else {
                throw $e;
            }
        }
    }

    //  This method try to catch and create the profile of a user present in the online database.
    public function retrieveProfil($username, $password, $result, $url)
    {
        $new_user = new User();
        $new_user->setFirstName($result['first_name']);
        $new_user->setLastName($result['last_name']);
        $new_user->setUsername($result['username']);
        $new_user->setMail($result['mail']);
        $new_user->setPlainPassword($password);

        $this->userManager->createUser($new_user);
        if ($result['admin']) {
            $this->roleManager->associateUserRole($new_user, $this->roleManager->getRoleByName(PlatformRoles::ADMIN), false, true);
        }
        $my_user = $this->userRepo->findOneBy(array('username' => $username));
        $ws_perso = $my_user->getPersonalWorkspace();
        $user_ws_rn = $this->resourceNodeRepo->findOneBy(array('workspace' => $ws_perso, 'parent' => NULL));
        $this->om->startFlushSuite();
        $my_user->setExchangeToken($result['token']);
        $ws_perso->setGuid($result['ws_perso']);
        $user_ws_rn->setNodeHashName($result['ws_resnode']);
        $this->om->endFlushSuite();
        $this->userSyncManager->createUserSynchronized($my_user);

        // Creation of sync_config file
        $this->createSyncConfigFile($result, $url);

    }

    /*
    *******   METHODS EXECUTED ONLINE *******
    */

    // This method is used to delete files on the online plateform
    public function unlinkSynchronisationFile($content, $user)
    {
        unlink($content['dir'].$user->getId().'/sync_'.$content['hashname'].'.zip');
        $content['status'] = 200;

        return $content;
    }

    /*
    *******   METHODS USED IN BOTH SIDES *******
    */

    // The Browser is the Curl client used to create the HTTP request
    private function getBrowser()
    {
        $client = new Curl();
        $client->setTimeout(60);
        $browser = new Browser($client);

        return $browser;
    }

    // Method returning a table of the metadatas that are sent with the post requests
    public function getMetadataArray($user, $filename)
    {
        if (!file_exists($filename)) {
            $this->userSyncManager->resetSync($user);
            throw new SynchronisationFailsException();
        } else {
            return array(
                'token' => $user->getExchangeToken(),
                'hashname' => substr($filename, strlen($filename)-40, 36),
                'totalFragments' => $this->getTotalFragments($filename),
                'checksum' => hash_file( "sha256", $filename)
            );
        }
    }

    // Method returning the number of fragment of a file
    // If file doesn't exist it returns -1
    public function getTotalFragments($filename)
    {
        if (! file_exists($filename)) {
            return -1;
        } else {
            return (int) (filesize($filename)/SyncConstant::MAX_FRAG_SIZE)+1;
        }
    }

    // Method returning the fragment number $fragmentNumber of the file $filename
    // If file doesn't exists throw a SynchronisationFailsException and reset the UserSynchronized entity of $user
    public function getFragment($fragmentNumber, $filename, $user)
    {
        if (!file_exists($filename)) {
            $this->userSyncManager->resetSync($user);
            throw new SynchronisationFailsException();
        } else {
            $fileSize = filesize($filename);
            $handle = fopen($filename, 'r');
            // Control that fragment exists
            if ($fragmentNumber*SyncConstant::MAX_FRAG_SIZE > $fileSize || !$handle) {
                return null;
            } else {
                $position = $fragmentNumber*SyncConstant::MAX_FRAG_SIZE;
                fseek($handle, $position);
                if ($fileSize > $position+SyncConstant::MAX_FRAG_SIZE) {
                    $data = fread($handle, SyncConstant::MAX_FRAG_SIZE);
                } else {
                    $data = fread($handle, $fileSize-$position);
                }
                if(!fclose($handle)) return null;

                return $data;
            }
        }
    }

    // Method used to analyse request and determining if all fragment are received
    // It returns an array containing the status code of the procedure
    public function processSyncRequest($content, $createSync)
    {
        $user = $this->userRepo->findOneBy(array('exchangeToken' => $content['token']));
        $dir = SyncConstant::SYNCHRO_UP_DIR.$user->getId();
        // If directory doesn't exists create it
        if (!is_dir($dir)) {
            mkdir($dir, 0777);
        }
        // Save the fragment received
        $fragmentName = $dir.'/'.$content['hashname'].'_'.$content['fragmentNumber'];
        $partFile = fopen($fragmentName, 'w+');
        if(!$partFile) return array("status" => 424);
        $write = fwrite($partFile, base64_decode($content['file']));
        if($write === false) return array("status" => 424);
        if(!fclose($partFile)) return array("status" => 424);
        // Control if all fragments are received
        if ($content['fragmentNumber'] == ($content['totalFragments']-1)) {
            return $this->endExchangeProcess($content, $createSync);
        }

        return array(
            "status" => 200
        );
    }

    // This method is in charge of the processing when all fragments are transfered
    private function endExchangeProcess($content, $createSync)
    {
        $zipName = $this->assembleParts($content);
        if ($zipName != null) {
			//If online, load and create archive.
            if ($createSync) {
                //Create synchronisation archive (when online)
				$user = $this->userRepo->findOneBy(array('exchangeToken' => $content['token']));
				$loadingResponse = $this->loadingManager->loadZip($zipName, $user);
                $toSend = $this->creationManager->createSyncZip($user, $loadingResponse['synchronizationDate']);
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

    //  This method is used to write the complete zip in one file from all the fragments
    private function assembleParts($content)
    {
        $user = $this->userRepo->findOneBy(array('exchangeToken' => $content['token']));
        $zipName = SyncConstant::SYNCHRO_UP_DIR.$user->getId().'/sync_'.$content['hashname'].'.zip';
        $zipFile = fopen($zipName, 'w+');
        if(!$zipFile) return null;
        for ($i = 0; $i<$content['totalFragments']; $i++) {
            $fragmentName = SyncConstant::SYNCHRO_UP_DIR.$user->getId().'/'.$content['hashname'].'_'.$i;
            $partFile = fopen($fragmentName, 'r');
            if(!$partFile) return null;
            $write = fwrite($zipFile, fread($partFile, filesize($fragmentName)));
            if($write === false) return null;
            if(!fclose($partFile)) return null;
            unlink($fragmentName);
        }
        if(!fclose($zipFile))return null;
        if (hash_file( "sha256", $zipName) == $content['checksum']) {
            return $zipName;
        } else {
            return null;
        }
    }

    // Create and/or add a new User profil in the synchronisation file.
    public function createSyncConfigFile($result, $url)
    {
        if (!(file_exists(SyncConstant::PLAT_CONF))) {
            $yaml_array = array();
            $sync_config = array(
                'username' => $result['username'],
                'mail' => $result['mail'],
                'url' => $url
            );
            $yaml_array[] = $sync_config;

            $yaml = $this->yaml_dump->dump($yaml_array);
            file_put_contents(SyncConstant::PLAT_CONF, $yaml);

        } else {
            $value = $this->yaml_parser->parse(file_get_contents(SyncConstant::PLAT_CONF));
            $sync_config = array(
                'username' => $result['username'],
                'mail' => $result['mail'],
                'url' => $url
            );
            $value[] = $sync_config;

            $yaml = $this->yaml_dump->dump($value);
            file_put_contents(SyncConstant::PLAT_CONF, $yaml);
        }
    }

    // Return the URL specify for the given User in the synchronisation file.
    public function getUserUrl(User $user)
    {
        $value = $this->yaml_parser->parse(file_get_contents(SyncConstant::PLAT_CONF));
        foreach ($value as $elem) {
            if ($elem['username'] == $user->getUserName() && $elem['mail'] == $user->getMail()) {
                return $elem['url'];
            }
        }

        return NULL;
    }
}
