<?php

namespace Claroline\OfflineBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as EXT;
use JMS\DiExtraBundle\Annotation as DI;
use JMS\SecurityExtraBundle\Annotation as SEC;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Repository;
use Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Entity\ResourceNode;
use Claroline\CoreBundle\Controller\FileController;
use \DateTime;
use \ZipArchive;

/**
 * @DI\Tag("security.secure_service")
 * @SEC\PreAuthorize("hasRole('ROLE_USER')")
 */
class OfflineController extends Controller
{
    private $router;
    
    /**
    * @DI\InjectParams({
    *      "router"             = @DI\Inject("router")
    * })
    */
    public function __construct(
        UrlGeneratorInterface $router
    )
    {
       $this->router = $router;
    }
    
    
    /**
     * Get content by id
     *
     * @EXT\Route(
     *     "/sync",
     *     name="claro_sync"
     * )
     * @EXT\ParamConverter("user", options={"authenticatedUser" = true})
     * 
     * @param User $user
     *
     * @return Response
     */
    public function helloAction(User $user)
    {
        $em = $this->getDoctrine()->getManager();
        $userSynchroDate = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);
         
        $username = $user->getFirstName() . ' ' . $user->getLastName();
        
        if ($userSynchroDate) {
            return $this->render('ClarolineOfflineBundle:Offline:sync.html.twig', array(
                'user' => $username,
                'user_sync_date' => $userSynchroDate[0]->getLastSynchronization()
            ) );
        }else{
            return $this->render('ClarolineOfflineBundle:Offline:first_sync.html.twig', array(
                'user' => $username
            ) );
        }
    }
    
    /**
    *   Create userSyncrhonized entity
    *   
    *   @EXT\Route(
    *       "/sync/load",
    *       name="claro_sync_load"
    *   )
    *
    * @EXT\ParamConverter("user", options={"authenticatedUser" = true})
    * @EXT\Template("ClarolineOfflineBundle:Offline:load.html.twig")
    *
    * @param User $user
    * @return Response
    */
    public function synchronizeAction(User $user)
    {
        //$userSynchro = $this->get('claroline.manager.synchronize_manager')->createUserSynchronized($user);
         
        //$em = $this->getDoctrine()->getManager();
        //$userSynchroDate = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);
         
        //$zip = $this->get('claroline.manager.loading_manager')->loadXML('manifest_test_3.xml');
        $zip = $this->get('claroline.manager.loading_manager')->loadZip('sync_EC7E3655-DE0D-4D7D-9434-304ECD11CBC1.zip');
         
        $username = $user->getFirstName() . ' ' . $user->getLastName();
        return array(
            'user' => $username
         );
    }
    

    /**
    *   Seek and show all the modified courses and ressources
    *   
    *   @EXT\Route(
    *       "/sync/seek",
    *       name="claro_sync_seek"
    *   )
    *
    * @EXT\ParamConverter("user", options={"authenticatedUser" = true})
    * @EXT\Template("ClarolineOfflineBundle:Offline:courses.html.twig")
    *
    * @param User $user
    * @return Response
    */
    public function seekAction(User $user)
    {
        $test = $this->get('claroline.manager.synchronize_manager')->createSyncZip($user);
        $username = $user->getFirstName() . ' ' . $user->getLastName(); 
        echo 'Congratulations '.$username.'! '."<br/>".'You are now synchronized!';
           
        return array(
            'user' => $username,
            //'user_courses' => $test['user_courses'],
            //'user_res' => $test['user_res']
        );
    }
    
    /**
    *  Transfer a file (sync archive) from a computer to another
    *   
    *   @EXT\Route(
    *       "/sync/transfer/{user}",
    *       name="claro_sync_transfer"
    *   )
    *
    * @EXT\ParamConverter("authUser", options={"authenticatedUser" = true})
    * @EXT\Template("ClarolineOfflineBundle:Offline:transfer.html.twig")
    *
    * @param User $user
    * @return Reponse
    */
    public function transferAction($user, User $authUser)
    {
        $transfer = true;
        if($user == $authUser->getId()){
            $test = $this->get('claroline.manager.transfer_manager')->getSyncZip($authUser);
        }else{
            $transfer = false;
        }
        
        $username = $authUser->getFirstName() . ' ' . $authUser->getLastName(); 
        return array(
            'user' => $username,
            'transfer' => $transfer
        );
    }
    
    /**
    *   @EXT\Route(
    *       "/sync/getzip/{user}",
    *       name="claro_sync_get_zip",
    *   )
    *
    *   @EXT\Method("POST")    
    *   @EXT\ParamConverter("authUser", options={"authenticatedUser" = true})
    *
    *   @param User $user
    *   @return Response
    */
    public function getZipAction($user, User $authUser){
    /*
    *   A adapter ici. Au sein de la requete qui appelle on est maintenant sur du POST et non plus sur du GET
    *   la methode recevra avec la requete le zip de l'utilisateur offline
    *   Il faut donc commencer par recevoir le zip du offline
    *   Ensuite le traiter
    *   Generer le zip descendant et le retourner dans la stream reponse
    */
        echo 'user : '.$user;
        echo '  Auth user : '.$authUser->getId();
        
        $request = $this->getRequest();
        //TODO Decouper le travail de la requete dans une action de manager
        echo ' <br/>'.'CECI est la requete !!!! <br/>'.' <br/> '.$request.' <br/>';
        $content = $request->getContent();
        $zipFile = fopen('./synchronize_up/'.$user.'/sync.zip', 'w+');
        $write = fwrite($zipFile, $content);
        
        //TODO verfier securite? => dans FileController il fait un checkAccess....
        $response = new StreamedResponse();
       // $var = $user;
        //SetCallBack voir Symfony/Bundle/Controller/Controller pour les parametres de set callback
        //TODO, protéger plus le zip? Seul le propriétaire devrait avoir accès
        $response->setCallBack(
            function () use ($user) {
                readfile('synchronize_down/'.$user.'/sync.zip');
            }
        );
        
        return $response;

    }
}
