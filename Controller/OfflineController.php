<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 
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
use Claroline\OfflineBundle\SyncConstant;
use \DateTime;
use \ZipArchive;
use \Buzz\Browser;
use \Buzz\Client\Curl;
use \Buzz\Client\FileGetContents;

/**
 * @DI\Tag("security.secure_service")
 * @SEC\PreAuthorize("hasRole('ROLE_USER')")
 */
class OfflineController extends Controller
{
    private $router;
    private $request;
    
    /**
    * @DI\InjectParams({
    *      "router"             = @DI\Inject("router"),
    *     "request"            = @DI\Inject("request")
    * })
    */
    public function __construct(
        UrlGeneratorInterface $router,
        Request $request
    )
    {
       $this->router = $router;
       $this->request = $request;
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
        // $em = $this->getDoctrine()->getManager();
        // $userSync = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);
         
        // $username = $user->getFirstName() . ' ' . $user->getLastName();
        
        // if ($userSync) {
        // TODO Liens vers la route de synchronisation
            // return $this->render('ClarolineOfflineBundle:Offline:sync.html.twig', array(
                // 'user' => $username,
                // 'user_sync_date' => $userSync[0]->getLastSynchronization()
            // ) );
        // }else{
        // TODO Methode d'installation
            // return $this->render('ClarolineOfflineBundle:Offline:first_sync.html.twig', array(
                // 'user' => $username
            // ) );
        // }
        
        
        
        
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
    public function loadAction(User $user)
    {
        //$userSynchro = $this->get('claroline.manager.user_sync_manager')->createUserSynchronized($user);
         
        //$em = $this->getDoctrine()->getManager();
        //$userSynchroDate = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);
         
        //$zip = $this->get('claroline.manager.loading_manager')->loadXML('manifest_test_3.xml');
        /*        
        $em = $this->getDoctrine()->getManager();
        $lol = $em->getEventManager();
        $tmp = array();
        $tmp = $lol->getListener();
        
        foreach($tmp as $listener)
        {
            echo 'Listener found';
        }*/
        /*
        $dispatcher = $this->get('event_dispatcher');
        $tmp = array();
        $tmp = $dispatcher->getListeners();
        foreach($tmp as $listener)
        {
            echo 'Boulou'.'<br/>';
        }
        */
       // $em = this->getDoctrine->
        
        //$bool = $dispatcher->hasListeners('Gedmo\Timestampable');
        
        //echo 'Bool : '.$bool.'<br/>';
        
        $zip = $this->get('claroline.manager.loading_manager')->loadZip('sync_D2DF8F72-D0E5-4E7A-A48D-08379822500D.zip', $user);
         
        $username = $user->getFirstName() . ' ' . $user->getLastName();
        return array(
            'user' => $username
         );
    }
    
    /**
    *   Create userSyncrhonized entity
    *   
    *   @EXT\Route(
    *       "/sync/exchange/{user}",
    *       name="claro_sync_exchange"
    *   )
    *
    * @EXT\ParamConverter("authUser", options={"authenticatedUser" = true})
    * @EXT\Template("ClarolineOfflineBundle:Offline:load.html.twig")
    *
    * @param User $authUser
    * @return Response
    */
    public function syncAction($user, User $authUser)
    {  /** 
        *   TODO MODIFY return with render different twig donc redirect plutot que le boolean true false
        */
        
        if($user != $authUser->getId())
        {
            $username = $authUser->getFirstName() . ' ' . $authUser->getLastName();
            return array(
                'user' => $username,
                'succeed' => false
            );
        }
        else
        {
            $em = $this->getDoctrine()->getManager();
            $userSync = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($authUser);
            $this->get('claroline.manager.synchronisation_manager')->synchroniseUser($authUser, $userSync[0]);
            // $i = $this->get('claroline.manager.synchronisation_manager')->getDownloadStop("24F0DCDC-3B64-4019-8D6A-80FBCEA68AF9", $authUser);
            // echo "last download : ".$i."<br/>";
            
            //Format the view
            $username = $authUser->getFirstName() . ' ' . $authUser->getLastName();
            return array(
                'user' => $username,
                'succeed' => true
             );
        }
    }

    /**
    *
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
        $em = $this->getDoctrine()->getManager();
        $userSyncTab = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);
        $test = $this->get('claroline.manager.creation_manager')->createSyncZip($user, ''.$userSyncTab[0]->getlastSynchronization()->getTimestamp());
        $username = $user->getFirstName() . ' ' . $user->getLastName(); 
        echo 'Congratulations '.$username.'! '."<br/>".'You are now synchronized!';
        echo ''.$test;   
        return array(
            'user' => $username,
            //'user_courses' => $test['user_courses'],
            //'user_res' => $test['user_res']
        );
    }
    
    /**
    *
    *  Transfer a file (sync archive) from a computer to another
    *   
    *   @EXT\Route(
    *       "/sync/transfer/{user}",
    *       name="claro_sync_transfertest"
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
            $toTransfer = './synchronize_down/3/sync_0252D476-FD7D-4E39-9285-A53EDEFCAC90.zip';
            $test = $this->get('claroline.manager.transfer_manager')->uploadZip($toTransfer, $authUser, 0);
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
    *
    *  Transfer a file (sync archive) from a computer to another
    *   
    *   @EXT\Route(
    *       "/sync/getsync/{user}",
    *       name="claro_sync_gettest"
    *   )
    *
    * @EXT\ParamConverter("authUser", options={"authenticatedUser" = true})
    * @EXT\Template("ClarolineOfflineBundle:Offline:transfer.html.twig")
    *
    * @param User $user
    * @return Reponse
    */
    public function getSyncAction($user, User $authUser)
    {
        $transfer = true;
        if($user == $authUser->getId()){
            $hashToGet = '1A7BE8A0-EE83-4853-93A4-63BABB8B8B84';
            $numPackets = 3;
            $test = $this->get('claroline.manager.transfer_manager')->getSyncZip($hashToGet, $numPackets, 0, $authUser);
            echo $test."<br/>";
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
    *       "/sync/loadWorkspaces",
    *       name="claro_sync_load_workspace"
    *   )
    *
    * @EXT\ParamConverter("user", options={"authenticatedUser" = true})
    * @EXT\Template("ClarolineOfflineBundle:Offline:load.html.twig")
    *
    * @param User $user
    * @return Response
    */
    public function loadWorkspacesAction(User $user)
    {       
        $zip = $this->get('claroline.manager.loading_manager')->loadPublicWorkspaceList(SyncConstant::SYNCHRO_UP_DIR.$user->getId().'/all_workspaces.xml');
         
        $username = $user->getFirstName() . ' ' . $user->getLastName();
        return array(
            'user' => $username
         );
    }
    
    
    /**
    *   @EXT\Route(
    *       "/sync/getuser",
    *       name="claro_sync_getuser"
    *   )
    *
    * @EXT\ParamConverter("user", options={"authenticatedUser" = true})
    * @EXT\Template("ClarolineOfflineBundle:Offline:load.html.twig")
    *
    * @param User $user
    * @return Response
    */
    public function getUserAction(User $user)
    {       
        //TODO change "password", with a window getting password in clear
        $this->get('claroline.manager.transfer_manager')->getUserInfo($user->getUsername(), "password");
         
        $username = $user->getFirstName() . ' ' . $user->getLastName();
        return array(
            'user' => $username
         );
    }
}
