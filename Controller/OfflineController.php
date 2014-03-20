<?php

namespace Claroline\OfflineBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
    *      "router" = @DI\Inject("router")
    *   })
    **/
    public function _construct(
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
         
        $zip = $this->get('claroline.manager.loading_manager')->loadXML('manifest_test_3.xml');
         
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
    */
    public function loadAction()
    {
        
    }
    
    /**
    *  Transfer a file (sync archive) from a computer to another
    *   
    *   @EXT\Route(
    *       "/sync/transfer",
    *       name="claro_sync_transfer"
    *   )
    *
    * @EXT\ParamConverter("user", options={"authenticatedUser" = true})
    * @EXT\Template("ClarolineOfflineBundle:Offline:transfer.html.twig")
    *
    * @param User $user
    * @return Reponse
    */
    public function transferAction(User $user)
    {
        $test = $this->get('claroline.manager.transfer_manager')->getSyncZip();
        $username = $user->getFirstName() . ' ' . $user->getLastName(); 
           
        return array(
            'user' => $username
        );
    }
    
    /**
    *   @EXT\Route(
    *       "/sync/getzip",
    *       name="claro_sync_get_zip",
    *   )
    
    *   @EXT\Method("GET")
    *
    *   @return Response
    */
    public function getZipAction(){
    
        //TODO verfier securite? => dans FileController il fait un checkAccess....
        $zip = new ZipArchive();
        if($zip->open('archive_1395158553.zip') == TRUE){
            $response = new StreamedResponse();
            
            $response->setCallBack(
                function () use ($zip) {
                    readfile('archive_1395158553.zip');
                }
            );
            
            return $response;
        }else{
            $route = $this->router->generate('claro_sync');
            return new RedirectResponse($route);
        }
    }
}
