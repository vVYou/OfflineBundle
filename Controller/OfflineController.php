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
     * @EXT\Template("ClarolineOfflineBundle:Offline:content.html.twig")
     * 
     * @param User $user
     *
     * @return Response
     */
    public function helloAction(User $user)
    {
        $username = $user->getFirstName() . ' ' . $user->getLastName();
        return array(
            'user' => $username
        );
    }
    
    /**
    *   Create userSyncrhonized entity
    *   
    *   @EXT\Route(
    *       "/sync/magique",
    *       name="claro_sync_user"
    *   )
    *
    * @EXT\ParamConverter("user", options={"authenticatedUser" = true})
    * @EXT\Template("ClarolineOfflineBundle:Offline:sync.html.twig")
    *
    * @param User $user
    * @return Reponse
    */
    public function synchronizeAction(User $user)
    {
        //$userSynchro = $this->get('claroline.manager.synchronize_manager')->createUserSynchronized($user);
         
        $em = $this->getDoctrine()->getManager();
        $userSynchroDate = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);
         
        $username = $user->getFirstName() . ' ' . $user->getLastName();
        return array(
            'user' => $username,
            'user_sync_date' => $userSynchroDate
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
        //$userSynchro = $this->get('claroline.manager.synchronize_manager')->createUserSynchronized($user);
         
        $em = $this->getDoctrine()->getManager();
        $userCourses = $em->getRepository('ClarolineCoreBundle:Workspace')->findByUser($user);
  
        $username = $user->getFirstName() . ' ' . $user->getLastName();
        return array(
            'user' => $username,
            'user_courses' => $userCourses
         );
    }
}
