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
         
        $username = $user->getFirstName() . ' ' . $user->getLastName();
        
        if ($userSynchroDate) {
            return $this->render('ClarolineOfflineBundle:Offline:sync.html.twig', array(
                'user' => $username,
                'user_sync_date' => $userSynchroDate
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
    *       "/sync/magique",
    *       name="claro_sync_user"
    *   )
    *
    * @EXT\ParamConverter("user", options={"authenticatedUser" = true})
    * @EXT\Template("ClarolineOfflineBundle:Offline:zip.html.twig")
    *
    * @param User $user
    * @return Reponse
    */
    public function synchronizeAction(User $user)
    {
        //$userSynchro = $this->get('claroline.manager.synchronize_manager')->createUserSynchronized($user);
         
        //$em = $this->getDoctrine()->getManager();
        //$userSynchroDate = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);
         
        $zip = $this->get('claroline.manager.synchronize_manager')->createSyncZip($user);
         
        $username = $user->getFirstName() . ' ' . $user->getLastName();
        return array(
            'user' => $username,
        //    'user_sync_date' => $userSynchro
            'zip' => $zip
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
        $userRes = array();
        $obso;
        $typeList = array('file', 'text'); // ! PAS OPTIMAL !
        $typeArray = $this->buildTypeArray($typeList);
        $em = $this->getDoctrine()->getManager();       
        // $typeArray = $this->get('claroline.manager.resource_manager')->getResourceTypeByName('file');
        // $typeArray = $this->get('claroline.manager.resource_manager')->getResourceTypeByName('text');
         
        //$em = $this->getDoctrine()->getManager();
        $userWS = $em->getRepository('ClarolineCoreBundle:Workspace\AbstractWorkspace')->findByUser($user);
        $username = $user->getFirstName() . ' ' . $user->getLastName();      
 
        foreach($userWS as $element)
        {
            //echo 'First for!';
            //echo count($typeArray);
            foreach($typeArray as $resType)
            {
                //$em_res = $this->getDoctrine()->getManager();
                $userRes = $em->getRepository('ClarolineCoreBundle:Resource\ResourceNode')->findByWorkspaceAndResourceType($element, $resType);
                if(count($userRes) >= 1)
                {
                    $obso = $this->checkObsolete($userRes, $user);  // Remove all the resources not modified.
                    //Ajouter le resultat dans l'archive Zip
                    //this->download_sync($obso, $archive); ou qqch comme ça.
                    //echo "<br/>".count($obso)."<br/>";
                }
            }
        }             
        
        return array(
            'user' => $username,
            'user_courses' => $userWS,
            'user_res' => $userRes
        );
    }
    
    private function buildTypeArray(array $typeList)
    {
        $typeArrayTmp = array();
        foreach($typeList as $element)
        {
            $typeArrayTmp[] = $this->get('claroline.manager.resource_manager')->getResourceTypeByName($element);
        }
        //echo count($typeArrayTmp);
        return $typeArrayTmp;
    }
    
        
    private function checkObsolete(array $userRes, User $user)
    {
        $em = $this->getDoctrine()->getManager();
        $dateSync = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);
        $user_tmp = $dateSync[0]->getLastSynchronization();
        $date_user = $user_tmp->getTimestamp();
        $new_res = array();
        
        foreach($userRes as $resource)
        {
            echo 'La date de mon cours :';
            echo $resource->getModificationDate()->format('Y-m-d') . "<br/>";
            $res_tmp = $resource->getModificationDate();
            $date_res = $res_tmp->getTimestamp();
            $interval = $date_res - $date_user;
            
            if($interval > 0)
            {
                echo 'Name file : ';
                echo $resource->getName() . "<br/>";
                echo 'This file has been modified' . "<br/>";
                $new_res[] = $resource;
            }
            
            else
            {
                echo 'Name file : ';
                echo $resource->getName() . "<br/>";
                echo 'File not modified' . "<br/>";
            }
            
        }
                
        echo 'Ma date à moi :';
        echo $dateSync[0]->getLastSynchronization()->format('Y-m-d') . "<br/>";
        return $resource;
        
    }
    
    private function download_sync(array $obso, ZipArchive $archive)
    {
    
    }
 
  
}
