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
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\CoreBundle\Pager\PagerFactory;
use Claroline\CoreBundle\Library\Utilities\ClaroUtilities;
use Claroline\OfflineBundle\Entity\UserSynchronized;
use Claroline\OfflineBundle\Manager\LoadingManager;
use Claroline\OfflineBundle\Manager\CreationManager;
use Claroline\OfflineBundle\SyncConstant;
//use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use symfony\Component\Routing\Generator\UrlGeneratorInterface;
use JMS\DiExtraBundle\Annotation as DI;
use Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Entity\Resource\ResourceType;
use Claroline\CoreBundle\Entity\Resource\ResourceNode;
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
    private $pagerFactory;
    private $translator;
    private $userSynchronizedRepo;
    private $userRepo;
    private $loadingManager;
    private $resourceManager;
    private $syncManager;
    private $ut;
    
    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "pagerFactory"   = @DI\Inject("claroline.pager.pager_factory"),
     *     "translator"     = @DI\Inject("translator"),
     *     "resourceManager"= @DI\Inject("claroline.manager.resource_manager"),
     *     "syncManager"= @DI\Inject("claroline.manager.synchronize_manager"),
     *     "loadingManager" = @DI\Inject("claroline.manager.loading_manager"),
     *     "ut"            = @DI\Inject("claroline.utilities.misc")
     * })
     */
    public function __construct(
        ObjectManager $om,
        PagerFactory $pagerFactory,
        TranslatorInterface $translator,
        ResourceManager $resourceManager,
        CreationManager $syncManager,
        LoadingManager $loadingManager,
        ClaroUtilities $ut
    )
    {
        $this->om = $om;
        $this->pagerFactory = $pagerFactory;
        $this->userSynchronizedRepo = $om->getRepository('ClarolineOfflineBundle:UserSynchronized');
        $this->userRepo = $om->getRepository('ClarolineCoreBundle:User');
        $this->translator = $translator;
        $this->resourceManager = $resourceManager;
        $this->syncManager = $syncManager;
        $this->loadingManager = $loadingManager;
        $this->ut = $ut;
    }
    
    
    /*
    *   @param User $user
    */
    public function transferZip($toTransfer, User $user)    
    {  
        /*
        *   L'objectif de cette fonction est de transférer le fichier en paramètre à la plateforme dans l'URL est sauvegardée dans les constantes
        *   Ce transfer s'effectue en plusieurs paquets
        */
        
        // ATTENTION, droits d'ecriture de fichier
        
        //Declaration du client HTML Buzz
        $client = new Curl();
        $client->setTimeout(60);
        $browser = new Browser($client);
        
        //Browser post signature                public function post($url, $headers = array(), $content = '')
        //Utilisation de la methode POST de HTML et non la methode GET pour pouvoir injecter des données en même temps.
        
        //TODO dynamique zip file name - constante repertoire sync_up et sync_down
        //PROCEDURE D'envoi complet du packet, à améliorer en sauvegardant l'etat et reprendre là ou on en etait si nécessaire...
        //TODO, checkin password !
        $requestContent = $this->getMetadataArray($user, $toTransfer);
        
        $packetNumber = 0;
        $numberOfPackets = $requestContent['nPackets'];
        $handle = fopen($toTransfer, 'r');
        
        while($packetNumber < $numberOfPackets)
        {
            //TODO D'autres elements a encoder en base 64?
            $requestContent['file'] = base64_encode($this->getPacket($packetNumber, $handle, filesize($toTransfer)));
            $requestContent['packetNum'] = $packetNumber;
            
            echo "le tableau que j'envoie : ".json_encode($requestContent)."<br/>";
            
            //TODO identifier une erreur de transfert (analyse du status)
            $reponse = $browser->post(SyncConstant::PLATEFORM_URL.'/transfer/getzip/'.$user->getId(), array(), json_encode($requestContent));    
            $content = $reponse->getContent();
            // echo $browser->getLastRequest().'<br/>';
            echo 'CONTENT : <br/>'.$content.'<br/>';
            
            //control response, if ok
            $packetNumber ++;
            //else do not increment packetNumber, so it will send again the same packet
        }
        
        fclose($handle); //return boolean
        //TODO control file closure 
        
        //Changer la fin
        //return reussite ou echec
        
        echo "Status : ";
        return $content;
       
        //TODO ADAPT end of procedure
        // $hashname = $this->ut->generateGuid();
        // $dir = SyncConstant::SYNCHRO_UP_DIR.$user->getId().'/';
        // if(!is_dir($dir)){
            // mkdir($dir);
        // }
        // $zip_path = $dir.'sync_'.$hashname.'.zip';
        //TODO Check ouverture du fichier
        // $zipFile = fopen($zip_path, 'w+');
        // $write = fwrite($zipFile, $content);
        // if(!$write){
        //SHOULD RETURN ERROR
            // echo 'An ERROR happen re-writing zip file at reception<br/>';
        // }
        // if (!fclose($zipFile)){
            //TODO SHOULD be an exception
            // echo 'An ERROR happened closing archive file at reception<br/>';
        // }
        // echo 'TRANSFER PASS !';
        
        // return $zip_path;
    }
    
    public function getMetadataArray($user, $filename)
    {
        return array(
            'id' => $user->getId(),
            'username' => $user->getUsername(), 
            'password' => "password",
            'token' => $user->getExchangeToken(),
            'hashname' => substr($filename, strlen($filename)-40, 36),
            'nPackets' => (int)(filesize($filename)/SyncConstant::MAX_PACKET_SIZE)+1,
            'checksum' => hash_file( "sha256", $filename),
            'file' => "", 
            'packetNum' => 0,
            'message' => "");
    }
    
    public function getPacket($packetNumber, $handle, $fileSize)
    {
        $position = $packetNumber*SyncConstant::MAX_PACKET_SIZE;
        fseek($handle, $position);
        if($fileSize > $position+SyncConstant::MAX_PACKET_SIZE)
        {
            $data = fread($handle, SyncConstant::MAX_PACKET_SIZE);
        }else{
            $data = fread($handle, $fileSize-$position);
        }
        return $data;
    }
  
  
    public function processSyncRequest($content)
    {
        //TODO Verifier le fichier entrant (dependency injections)
        
        //TODO, verification de l'existance du dossier
        $partName = SyncConstant::SYNCHRO_UP_DIR.$content['id'].'/'.$content['hashname'].'_'.$content['packetNum'];
        $partFile = fopen($partName, 'w+');
        $write = fwrite($partFile, base64_decode($content['file']));
        //TODO control writing errors
        fclose($partFile);
        if($content['packetNum'] == ($content['nPackets']-1)){
            return $this->endExchangeProcess($content);
        }
        return array();
    }
    
    public function endExchangeProcess($content){
        $zipName = $this->assembleParts($content);
        if($zipName != null){
            //Load archive
            $user = $this->userRepo->loadUserByUsername($content['username']);
            //TODO LOAD when patch
            //$this->loadingManager->loadZip($zipName, $user);
            //Create synchronisation
            $toSend = $this->syncManager->createSyncZip($user);
            return $this->getMetadataArray($user, $toSend);
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
            return $zipName;
        }else{
            return null;
        }
    }

    public function confirmRequest($user)
    {
        $client = new Curl();
        $client->setTimeout(60);
        $browser = new Browser($client);

        $reponse = $browser->get(SyncConstant::PLATEFORM_URL.'/transfer/confirm/'.$user->getId()); 
        if ($reponse)       
        {
            echo "HE CONFIRM RECEIVE !<br/>";
        }
    }
    
    public function getUserInfo($user)
    {
        $client = new Curl();
        $client->setTimeout(60);
        $browser = new Browser($client);
        
        // $hash2 = hash("sha256", $user->getUsername().$user->getCreationDate()->format('Y-m-d H:i:s').rand());
        // echo " token : ".$hash2.'<br/>';
        //TODO remove hardcode
        $contentArray = array(
            'username' => 'ket',
            'password' => 'password'
        );
        echo "content array : ".json_encode($contentArray).'<br/>';
        $reponse = $browser->post(SyncConstant::PLATEFORM_URL.'/sync/user', array(), json_encode($contentArray)); 
        //TODO charge user
        echo "trop cool : ".$reponse->getContent()."<br/>";
    }
}
