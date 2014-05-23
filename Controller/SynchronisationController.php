<?php

namespace Claroline\OfflineBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as EXT;
use JMS\DiExtraBundle\Annotation as DI;
use JMS\SecurityExtraBundle\Annotation as SEC;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Library\Security\Authenticator;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Repository\UserRepository;
use Claroline\OfflineBundle\SyncConstant;
use \DateTime;
use \ZipArchive;

class SynchronisationController extends Controller
{    

    private $authenticator;
    
     /**
     * @DI\InjectParams({
     *     "authenticator"  = @DI\Inject("claroline.authenticator")
     * })
     */
    public function __construct(
        Authenticator $authenticator
    )
    {
        $this->authenticator = $authenticator;
    }
    // TODO Security voir workspace controller.

    private function getUserFromID($user)
    {
        $em = $this->getDoctrine()->getManager();
        $arrayRepo = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findById($user);
        return $arrayRepo[0];
    }

    /**
    *   @EXT\Route(
    *       "/transfer/uploadzip/{user}",
    *       name="claro_sync_upload_zip",
    *   )
    *
    *   @EXT\Method("POST")    
    *
    *   @return Response
    */
    public function getUploadAction($user)
    {   /*
        *   A adapter ici. Au sein de la requete qui appelle on est maintenant sur du POST et non plus sur du GET
        *   la methode recevra avec la requete le zip de l'utilisateur offline
        *   Il faut donc commencer par recevoir le zip du offline
        *   Ensuite le traiter
        *   Generer le zip descendant et le retourner dans la stream reponse
        */
        
        //TODO verifier l'authentification via token
        
        $content = $this->getRequest()->getContent();
        // echo "CONTENT received : ".$content."<br/>";
        $informationsArray = (array)json_decode($content);
        // echo "Packet Number : ".$informationsArray['packetNum'].'<br/>';
        
        $status = $this->authenticator->authenticateWithToken($informationsArray['username'], $informationsArray['token']) ? 200 : 401;
        // echo "STATUS : ".$status."<br/>";
        $content = array();
        if ($status == 200){
            $content = $this->get('claroline.manager.transfer_manager')->processSyncRequest($informationsArray, true);
            // echo "what s generate by process request? : ".json_encode($content).'<br/>';
            $status = $content['status'];
        }
        return new JsonResponse($content, $status);
    }
    
    
    /**
    *   @EXT\Route(
    *       "/transfer/getzip/{user}",
    *       name="claro_sync_get_zip",
    *   )
    *
    *   @EXT\Method("POST")    
    *
    *   @return Response
    */
    public function getZipAction($user)
    {
        $content = $this->getRequest()->getContent();
        $informationsArray = (array)json_decode($content);
        // echo "Ask Packet Number : ".$informationsArray['packetNum'].'<br/>';
        $status = $this->authenticator->authenticateWithToken($informationsArray['username'], $informationsArray['token']) ? 200 : 401;
        // echo "STATUS : ".$status."<br/>";
        $content = array();
        if($status == 200){
            $fileName = SyncConstant::SYNCHRO_DOWN_DIR.$informationsArray['id'].'/sync_'.$informationsArray['hashname'].'.zip';
            $em = $this->getDoctrine()->getManager();
            $user = $em->getRepository('ClarolineCoreBundle:User')->loadUserByUsername($informationsArray['username']);
            $content = $this->get('claroline.manager.transfer_manager')->getMetadataArray($user, $fileName);
            $content['packetNum']=$informationsArray['packetNum'];
            $data = $this->get('claroline.manager.transfer_manager')->getPacket($informationsArray['packetNum'], $fileName);
            if($data == null){
                $status = 424;
            }else{
                $content['file'] = base64_encode($data);
            }
        }
        return new JsonResponse($content, $status);
    }

    /**
    *   @EXT\Route(
    *       "/sync/user",
    *       name="claro_sync_user",
    *   )
    *
    *   @EXT\Method("POST")    
    *
    *   @return Response
    */
    public function getUserIformations()
    {
        $content = $this->getRequest()->getContent();
        // echo "receive content <br/>";
        $informationsArray = (array)json_decode($content);
        $status = $this->authenticator->authenticate($informationsArray['username'], $informationsArray['password']) ? 200 : 401;        
        // echo "STATUS : ".$status."<br/>";
        $returnContent = array(); 

        if($status == 200){
            // Get User informations and return them
            $em = $this->getDoctrine()->getManager();
            $user = $em->getRepository('ClarolineCoreBundle:User')->loadUserByUsername($informationsArray['username']);
            //TODO ajout du token
            $returnContent = $user->getUserAsTab();
        }
        return new JsonResponse($returnContent, $status);
    }
    
   /**
    *   @EXT\Route(
    *       "/sync/lastUploaded",
    *       name="claro_sync_last_uploaded",
    *   )
    *
    *   @EXT\Method("POST")    
    *
    *   @return Response
    */
    public function getLastUploaded()
    {
        $content = $this->getRequest()->getContent();
        $informationsArray = (array)json_decode($content);
        $status = $this->authenticator->authenticateWithToken($informationsArray['username'], $informationsArray['token']) ? 200 : 401;
        $content = array();
        if($status == 200)
        {
            $filename = SyncConstant::SYNCHRO_UP_DIR.$informationsArray['id'].'/'.$informationsArray['hashname'];
            $em = $this->getDoctrine()->getManager();
            $user = $em->getRepository('ClarolineCoreBundle:User')->loadUserByUsername($informationsArray['username']);
            $lastUp = $this->get('claroline.manager.synchronisation_manager')->getDownloadStop($filename, $user);
            $content = array(
                'hashname' => $informationsArray['hashname'],
                'lastUpload' => $lastUp
            );
        }
        return new JsonResponse($content, $status);
    }
    
   /**
    *   @EXT\Route(
    *       "/sync/numberOfPacketsToDownload",
    *       name="claro_sync_number_of_packets_to_download",
    *   )
    *
    *   @EXT\Method("POST")    
    *
    *   @return Response
    */
    public function getNumberOfPacketsToDownload()
    {
        $content = $this->getRequest()->getContent();
        $informationsArray = (array)json_decode($content);
        $status = $this->authenticator->authenticateWithToken($informationsArray['username'], $informationsArray['token']) ? 200 : 401;
        $content = array();
        if($status == 200)
        {
            $filename = SyncConstant::SYNCHRO_DOWN_DIR.$informationsArray['id'].'/sync_'.$informationsArray['hashname'].".zip";
            $nPackets = $this->get('claroline.manager.transfer_manager')->getNumberOfParts($filename);
            $content = array(
                'hashname' => $informationsArray['hashname'],
                'nPackets' => $nPackets
            );
        }
        return new JsonResponse($content, $status);
    }
    
    /**
    *   @EXT\Route(
    *       "/transfer/confirm/{user}",
    *       name="claro_confirm_sync",
    *   )
    *
    *   @EXT\Method("GET")    
    */
    public function confirmAction($user)
    {
        /*$em = $this->getDoctrine()->getManager();
        $arrayRepo = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findById($user);
        $authUser = $arrayRepo[0];*/
        $authUser = $this->getUserFromID($user);

        //TODO verifier authentification !!!  => SHOULD return false if fails
        $this->get('claroline.manager.user_sync_manager')->updateUserSynchronized($authUser);
        return true;
    }

    /**
    *  Transfert workspace list
    *   
    *   @EXT\Route(
    *       "/transfer/workspace/{user}",
    *       name="claro_sync_transfer"
    *   )
    *
    * @EXT\Method("GET")
    *
    * @return Response
    */
    public function workspaceAction($user)
    {
        // Deprecated, not used anymore
        //TODO Authentification User
        $authUser = $this->getUserFromID($user);
        $toSend = $this->get('claroline.manager.creation_manager')->writeWorspaceList($authUser);
        
        //Send back the online sync zip
        $response = new StreamedResponse();
        //SetCallBack voir Symfony/Bundle/Controller/Controller pour les parametres de set callback
        $response->setCallBack(
            function () use ($toSend) {                
                readfile($toSend);
            }
        );

        return $response;
    }

    //TODO Route pour supprimer les fichiers de synchro
}
