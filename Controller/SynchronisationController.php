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
    *       "/transfer/getzip/{user}",
    *       name="claro_sync_get_zip",
    *   )
    *
    *   @EXT\Method("POST")    
    *
    *   @return Response
    */
    public function getZipAction($user)
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
        $informationTable = (array)json_decode($content);
        echo "Packet Number : ".$informationTable['packetNum'].'<br/>';
        
        $status = $this->authenticator->authenticateWithToken($informationTable['username'], $informationTable['token']) ? 200 : 403;
        echo "STATUS : ".$status."<br/>";
        // $status = $this->authenticator->authenticate($informationTable['username'], $informationTable['password']) ? 200 : 403;
        // echo "STATUS : ".$status."<br/>";
        $content = $this->get('claroline.manager.transfer_manager')->processSyncRequest($informationTable);
        return new JsonResponse($content, $status);
    }
        //Catch the sync zip sent via POST request
        // $uploadedSync = $this->get('claroline.manager.transfer_manager')->processSyncRequest($request['file'], $request['zipHashname'], $user);
        
        //TODO verfier securite? => dans FileController il fait un checkAccess....

        //Identify User
       /* $em = $this->getDoctrine()->getManager();
        $arrayRepo = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findById($user);
        $authUser = $arrayRepo[0];*/
        //$authUser = $this->getUserFromID($user);
        
        //Load the archive
        //$this->get('claroline.manager.loading_manager')->loadZip($uploadedSync, $authUser);
        
        //Compute the answer
        //$toSend = $this->get('claroline.manager.synchronize_manager')->createSyncZip($authUser);
        
        // echo "je prepare la reponse".$toSend."<br/>";
        //$userSynchro = $this->get('claroline.manager.user_sync_manager')->updateUserSynchronized($authUser);
       // $this->get('claroline.manager.user_sync_manager')->updateSentTime($authUser);

        //Send back the online sync zip
        // $response = new StreamedResponse();
        //SetCallBack voir Symfony/Bundle/Controller/Controller pour les parametres de set callback
        // $response->setCallBack(
            // function () use ($user) {
                // readfile(SyncConstant::SYNCHRO_DOWN_DIR.$user.'/sync_D17FAF3F-9737-4148-A012-71AEA4309A03.zip');
            // function () use ($toSend) {                
                // readfile($toSend);
            // }
        // );

        // return $response;
    // }

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
        //TODO imposer ceci en HTTPS !
        $content = $this->getRequest()->getContent();
        echo "receive content <br/>";
        $informationTable = (array)json_decode($content);
        $status = $this->authenticator->authenticate($informationTable['username'], $informationTable['password']) ? 200 : 403;        
        echo "STATUS : ".$status."<br/>";
        $returnContent = array(); 

        if($status == 200){
            // Get User informations and return them
            $em = $this->getDoctrine()->getManager();
            $user = $em->getRepository('ClarolineCoreBundle:User')->loadUserByUsername($informationTable['username']);
            //TODO ajout du token
            $returnContent = $user->getUserAsTab();
        }
        
        return new JsonResponse($returnContent, $status);
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
        //TODO Authentification User
        $authUser = $this->getUserFromID($user);
        $toSend = $this->get('claroline.manager.synchronize_manager')->writeWorspaceList($authUser);
        
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
    //TODO Routes pour les echanges de fichier en multiples morceaux
    //TODO gerer l'authentification partout !
    
    
    /***
    *         $status = $this->authenticator->authenticate($username, $password) ? 200 : 403;
        $content = ($status === 403) ?
            array('message' => $this->translator->trans('login_failure', array(), 'platform')) :
            array();
    */
}
