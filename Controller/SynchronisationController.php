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
use \DateTime;
use \ZipArchive;

class SynchronisationController extends Controller
{
    /**
    *   @EXT\Route(
    *       "/sync/getzip/{user}",
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
        
      //  $request = $this->getRequest();
        //TODO Decouper le travail de la requete dans une action de manager
      //  $content = $request->getContent();
        //TODO Verifier le fichier entrant
        
        //TODO Gestion dynamique du nom du fichier arrivant
        /*
        $zipFile = fopen('./synchronize_up/'.$user.'/sync_F8673788-EB93-4F78-85C3-4C7ACAB1802F.zip', 'w+');
        $write = fwrite($zipFile, $content);
        fclose($zipFile);
        */
        //TODO verfier securite? => dans FileController il fait un checkAccess....
        //TODO gestion dynamique du fichier retourne

        $response = new StreamedResponse();
        //$var = $user;
        //SetCallBack voir Symfony/Bundle/Controller/Controller pour les parametres de set callback
        $response->setCallBack(
            function () use ($user) {
                readfile('./synchronize_down/'.$user.'/sync_2CCDD72F-C788-41B8-8AA4-B407E8FD9193.zip');
            }
        );
        
        return $response;

    }
}
