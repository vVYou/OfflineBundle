<?php

namespace Claroline\OfflineBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as EXT;
use JMS\DiExtraBundle\Annotation as DI;
use JMS\SecurityExtraBundle\Annotation as SEC;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Repository;
use Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Entity\ResourceNode;
use \DateTime;

/**
 * @DI\Tag("security.secure_service")
 * @SEC\PreAuthorize("hasRole('ROLE_USER')")
 */
class OfflineController extends Controller
{
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
        //Pourquoi n'a-t-on plus la date dans la vue ???
         
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
    * @return Reponse
    */
    public function synchronizeAction(User $user)
    {
        //$userSynchro = $this->get('claroline.manager.synchronize_manager')->createUserSynchronized($user);
         
        //$em = $this->getDoctrine()->getManager();
        //$userSynchroDate = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);
         
        $zip = $this->get('claroline.manager.loading_manager')->loadXML('manifest_test_2.xml');
         
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
    * @return Reponse
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
}
