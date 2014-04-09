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
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\OfflineBundle\SyncConstant;
use \DateTime;
use \ZipArchive;

class SynchronisationController extends Controller
{    

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
        
        $request = $this->getRequest();
        //TODO verifier l'authentification
        
        //Catch the sync zip sent via POST request
        $uploadedSync = $this->get('claroline.manager.transfer_manager')->processSyncRequest($request, $user);
        
        //TODO verfier securite? => dans FileController il fait un checkAccess....

        //Identify User
        $em = $this->getDoctrine()->getManager();
        $arrayRepo = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findById($user);
        $authUser = $arrayRepo[0];
        
        //Load the archive
        $this->get('claroline.manager.loading_manager')->loadZip($uploadedSync, $authUser);
        
        //Compute the answer
        $toSend = $this->get('claroline.manager.synchronize_manager')->createSyncZip($authUser);
        
        // echo "je prepare la reponse".$toSend."<br/>";
        //$userSynchro = $this->get('claroline.manager.user_sync_manager')->updateUserSynchronized($authUser);
        $this->get('claroline.manager.user_sync_manager')->updateSentTime($authUser);

        //Send back the online sync zip
        $response = new StreamedResponse();
        //SetCallBack voir Symfony/Bundle/Controller/Controller pour les parametres de set callback
        $response->setCallBack(
            //function () use ($user) {
                //readfile(SyncConstant::SYNCHRO_DOWN_DIR.$user.'/sync_2CCDD72F-C788-41B8-8AA4-B407E8FD9193.zip');
            function () use ($toSend) {                
                readfile($toSend);
            }
        );

        return $response;
    }

    /**
    *   @EXT\Route(
    *       "/transfer/confirm/{user}",
    *       name="claro_confirm_sync",
    *   )
    *
    *   @EXT\Method("GET")    
    *
    *   @return Response
    */
    public function confirmAction($user)
    {
        $em = $this->getDoctrine()->getManager();
        $arrayRepo = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findById($user);
        $authUser = $arrayRepo[0];

        //TODO verifier authentification !!!
        $this->get('claroline.manager.user_sync_manager')->updateUserSynchronized($authUser);
        return true;
    }
}
