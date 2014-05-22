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
use Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\CoreBundle\Library\Utilities\ClaroUtilities;
use Claroline\OfflineBundle\Entity\UserSynchronized;
use Claroline\OfflineBundle\Manager\LoadingManager;
use Claroline\OfflineBundle\Manager\CreationManager;
use Claroline\OfflineBundle\Manager\UserSyncManager;
use Claroline\OfflineBundle\SyncConstant;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use symfony\Component\Routing\Generator\UrlGeneratorInterface;
use JMS\DiExtraBundle\Annotation as DI;
use \ZipArchive;
use \DateTime;
use \Buzz\Browser;
use \Buzz\Client\Curl;
use \Buzz\Client\FileGetContents;
use \Guzzle\Http\Client;
use \Guzzle\Http\Post\PostFile;
use \Guzzle\Http\EntityBody;
use \Guzzle\Http\EntityBodyInterface;
use \Guzzle\Http\Post\PostBodyInterface;


/**
 * @DI\Service("claroline.manager.transfer_manager")
 */

class TransferManager
{
    private $om;
    private $translator;
    private $userSynchronizedRepo;
    private $userRepo;
    private $loadingManager;
    private $resourceManager;
    private $syncManager;
    private $userSynchronizedManager;
    private $ut;
    
    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "translator"     = @DI\Inject("translator"),
     *     "resourceManager"= @DI\Inject("claroline.manager.resource_manager"),
     *     "syncManager"    = @DI\Inject("claroline.manager.creation_manager"),
     *     "loadingManager" = @DI\Inject("claroline.manager.loading_manager"),
     *     "userSyncManager" = @DI\Inject("claroline.manager.user_sync_manager"),
     *     "ut"            = @DI\Inject("claroline.utilities.misc")
     * })
     */
    public function __construct(
        ObjectManager $om,
        TranslatorInterface $translator,
        ResourceManager $resourceManager,
        CreationManager $syncManager,
        LoadingManager $loadingManager,
        UserSyncManager $userSyncManager,
        ClaroUtilities $ut
    )
    {
        $this->om = $om;
        $this->userSynchronizedRepo = $om->getRepository('ClarolineOfflineBundle:UserSynchronized');
        $this->userRepo = $om->getRepository('ClarolineCoreBundle:User');
        $this->translator = $translator;
        $this->resourceManager = $resourceManager;
        $this->syncManager = $syncManager;
        $this->loadingManager = $loadingManager;
        $this->userSynchronizedManager = $userSyncManager;
        $this->ut = $ut;
    }
    
    
    /*
    *   @param User $user
    */
    public function uploadZip($toTransfer, User $user)    
    {  /*
        *   L'objectif de cette fonction est de transférer le fichier en paramètre à la plateforme dans l'URL est sauvegardée dans les constantes
        *   Ce transfer s'effectue en plusieurs paquets
        */
        // ATTENTION, droits d'ecriture de fichier
        //PROCEDURE D'envoi complet du packet, à améliorer en sauvegardant l'etat et reprendre là ou on en etait si nécessaire...
        //TODO, checkin password !
        
        $browser = $this->getBrowser();
        $requestContent = $this->getMetadataArray($user, $toTransfer);
        $packetNumber = 0;
        $numberOfPackets = $requestContent['nPackets'];
        $responseContent = "";
        
        while($packetNumber < $numberOfPackets)
        {
            $requestContent['file'] = base64_encode($this->getPacket($packetNumber, $toTransfer));
            $requestContent['packetNum'] = $packetNumber;
            // echo "le tableau que j'envoie : ".json_encode($requestContent)."<br/>";
            
            //TODO identifier une erreur de transfert (analyse du status)
            //Utilisation de la methode POST de HTML et non la methode GET pour pouvoir injecter des données en même temps.
            $reponse = $browser->post(SyncConstant::PLATEFORM_URL.'/transfer/uploadzip/'.$user->getId(), array(), json_encode($requestContent));    
            $responseContent = $reponse->getContent();
            echo 'CONTENT : <br/>'.$responseContent.'<br/>';
            $responseContent = (array)json_decode($responseContent);
            //control response, if ok
            $packetNumber ++;
            //else do not increment packetNumber, so it will send again the same packet
        }
        
        return $responseContent;
    }
    
    public function getSyncZip($hashToGet, $numPackets, $user)
    {
        $packetNum = 0;
        $browser = $this->getBrowser();
        $requestContent = array(
            'id' => $user->getId(),
            'username' => $user->getUsername(), 
            'token' => $user->getExchangeToken(),
            'hashname' => $hashToGet,
            'nPackets' => $numPackets,
            'packetNum' => 0);
        // echo "SENDING TAB : ".json_encode($requestContent)."<br/>";
        
        while($packetNum < $numPackets){
            echo 'doing packet '.$packetNum.'<br/>';
            $requestContent['packetNum'] = $packetNum;
            $reponse = $browser->post(SyncConstant::PLATEFORM_URL.'/transfer/getzip/'.$user->getId(), array(), json_encode($requestContent));
            $content = $reponse->getContent();
            echo "CONTENT received : ".$content."<br/>";
            $this->processSyncRequest((array)json_decode($content), false);
            $packetNum++;
        }
        return "suceed";
    }
    
    public function getMetadataArray($user, $filename)
    {
        return array(
            'id' => $user->getId(),
            'username' => $user->getUsername(), 
            'token' => $user->getExchangeToken(),
            'hashname' => substr($filename, strlen($filename)-40, 36),
            'nPackets' => (int)(filesize($filename)/SyncConstant::MAX_PACKET_SIZE)+1,
            'checksum' => hash_file( "sha256", $filename),
            'file' => "", 
            'packetNum' => 0,
            'message' => "");
    }
    
    public function getPacket($packetNumber, $filename)
    {
        $handle = fopen($filename, 'r');
        $fileSize = filesize($filename);
        $position = $packetNumber*SyncConstant::MAX_PACKET_SIZE;
        fseek($handle, $position);
        if($fileSize > $position+SyncConstant::MAX_PACKET_SIZE){
            $data = fread($handle, SyncConstant::MAX_PACKET_SIZE);
        }else{
            $data = fread($handle, $fileSize-$position);
        }
        fclose($handle);
        //TODO control file closure
        return $data;
    }
  
  
    public function processSyncRequest($content, $createSync)
    {
        //TODO Verifier le fichier entrant (dependency injections)
        //TODO, verification de l'existance du dossier
        $partName = SyncConstant::SYNCHRO_UP_DIR.$content['id'].'/'.$content['hashname'].'_'.$content['packetNum'];
        // echo "PART NAME : ".$partName."<br/>";
        $partFile = fopen($partName, 'w+');
        $write = fwrite($partFile, base64_decode($content['file']));
        //TODO control writing errors
        fclose($partFile);
        if($content['packetNum'] == ($content['nPackets']-1)){
            return $this->endExchangeProcess($content, $createSync);
        }
        return array();
    }
    
    public function endExchangeProcess($content, $createSync){
        $zipName = $this->assembleParts($content);
        if($zipName != null){
            //Load archive
            // echo "LOAD USER <br/>";
            $user = $this->userRepo->loadUserByUsername($content['username']);
            //TODO LOAD when patch
            //$this->loadingManager->loadZip($zipName, $user);
            if($createSync){
                //Create synchronisation
                $toSend = $this->syncManager->createSyncZip($user);
                $this->userSynchronizedManager->updateUserSynchronized($user);
                return $this->getMetadataArray($user, $toSend);
            }else{
                return array(
                    'message' => 'loading complete'
                );
            }
        }else{
            return array(
                'message' => 'error assemble parts',
                'nPackets' => 0,
                'hashname' => 'null'
            );
        }
    }
    
    public function assembleParts($content)
    {
        $zipName = SyncConstant::SYNCHRO_UP_DIR.$content['id'].'/sync_'.$content['hashname'].'.zip';
        $zipFile = fopen($zipName, 'w+');
        for($i = 0; $i<$content['nPackets']; $i++){
            //TODO control writing errors
            $partName = SyncConstant::SYNCHRO_UP_DIR.$content['id'].'/'.$content['hashname'].'_'.$i;
            $partFile = fopen($partName, 'r');
            $write = fwrite($zipFile, fread($partFile, filesize($partName)));
            fclose($partFile);
        }
        fclose($zipFile);
        if(hash_file( "sha256", $zipName) == $content['checksum']){
            echo "CHECKSUM SUCCEED <br/>";
            return $zipName;
        }else{
            echo "CHECKSUM FAIL <br/>";
            return null;
        }
    }

    public function confirmRequest($user)
    {
        $browser = $this->getBrowser();

        $reponse = $browser->get(SyncConstant::PLATEFORM_URL.'/transfer/confirm/'.$user->getId()); 
        if ($reponse)       
        {
            echo "HE CONFIRM RECEIVE !<br/>";
        }
    }
    
    public function getUserInfo($username, $password)
    {
        // The given password has to be clear, without any encryption, the security is made by the HTTPS communication
        $browser = $this->getBrowser();
        
        //TODO remove hardcode
        $contentArray = array(
            'username' => $username,
            'password' => $password
        );
        echo "content array : ".json_encode($contentArray).'<br/>';
        $reponse = $browser->post(SyncConstant::PLATEFORM_URL.'/sync/user', array(), json_encode($contentArray)); 
        //TODO charge user
        echo "trop cool : ".$reponse->getContent()."<br/>";
    }
    
    private function getBrowser()
    {
        $client = new Curl();
        $client->setTimeout(60);
        $browser = new Browser($client);
        return $browser;
    }
}
