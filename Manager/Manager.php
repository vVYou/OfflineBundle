<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\OfflineBundle\Manager;

use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\CoreBundle\Pager\PagerFactory;
use Claroline\OfflineBundle\Entity\UserSynchronized;
//use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use JMS\DiExtraBundle\Annotation as DI;
use Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Entity\ResourceNode;
use \ZipArchive;
use \DateTime;

/**
 * @DI\Service("claroline.manager.synchronize_manager")
 */
class Manager
{
    private $om;
    private $pagerFactory;
    private $translator;
    private $userSynchronizedRepo;
    private $resourceManager;

    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "pagerFactory"   = @DI\Inject("claroline.pager.pager_factory"),
     *     "translator"     = @DI\Inject("translator"),
     *     "resourceManager"= @DI\Inject("claroline.manager.resource_manager")
     * })
     */
    public function __construct(
        ObjectManager $om,
        PagerFactory $pagerFactory,
        TranslatorInterface $translator,
        ResourceManager $resourceManager
    )
    {
        $this->om = $om;
        $this->pagerFactory = $pagerFactory;
        $this->userSynchronizedRepo = $om->getRepository('ClarolineOfflineBundle:UserSynchronized');
        $this->translator = $translator;
        $this->resourceManager = $resourceManager;
    }
    
    /**
     * Create a userSynchronized.
     * Its ID and the date of creation.
     *
     * @param \Claroline\CoreBundle\Entity\User $user
     *
     * @return \Claroline\OfflineBundle\Entity\UserSynchronized
     */
    public function createUserSynchronized(User $user)
    {
        $this->om->startFlushSuite();
        
        $userSynchronized = new userSynchronized($user);
        
        $this->om->persist($userSynchronized);
        $this->om->endFlushSuite();

        return $userSynchronized;
    }

    /**
     * Create a the archive based on the user     
     * Attention, if the archive file created is empty, it will not write zip file on disk !
     *
     * @param \Claroline\CoreBundle\Entity\User $user
     *
     */
    public function createSyncZip(User $user){
        $zip = new ZipArchive(); 
        
        //TODO CREATE DYNAMIC NAME
        if($zip->open('zip.zip', ZipArchive::CREATE) === true){
            //echo '&quot;Zip.zip&quot; ouvert<br/>';

            $zip->addFromString('Fichier.txt', 'Je suis le contenu de Fichier.txt !');
            // Add a file
            //$zip->addFile('Fichier.txt');

            $zip->close();
        }else{
            //TODO REPLACE BY EXCEPTION
            echo 'Impossible to open the zip file';
        }
        
        //CONFIRM WITH POP UP
        return $zip;
    }
    
     public function seek(User $user)
    {
        $userRes = array();
        $obso;
        $typeList = array('file', 'text'); // ! PAS OPTIMAL !
        $typeArray = $this->buildTypeArray($typeList);
        //$em = $this->$om->getDoctrine()->getManager();       
        // $typeArray = $this->get('claroline.manager.resource_manager')->getResourceTypeByName('file');
        // $typeArray = $this->get('claroline.manager.resource_manager')->getResourceTypeByName('text');
         
        //$em = $this->getDoctrine()->getManager();
        $userWS = $this->om->getRepository('ClarolineCoreBundle:Workspace\AbstractWorkspace')->findByUser($user);          
 
        foreach($userWS as $element)
        {
            //echo 'First for!';
            //echo count($typeArray);
            foreach($typeArray as $resType)
            {
                //$em_res = $this->getDoctrine()->getManager();
                $userRes = $this->om->getRepository('ClarolineCoreBundle:Resource\ResourceNode')->findByWorkspaceAndResourceType($element, $resType);
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
            'user_courses' => $userWS,
            'user_res' => $userRes
        );
    }
    
    private function buildTypeArray(array $typeList)
    {
        $typeArrayTmp = array();
        foreach($typeList as $element)
        {
            $typeArrayTmp[] = $this->resourceManager->getResourceTypeByName($element);
        }
        //echo count($typeArrayTmp);
        return $typeArrayTmp;
    }
    
        
    private function checkObsolete(array $userRes, User $user)
    {
        //$em = $this->getDoctrine()->getManager();
        $dateSync = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);
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
        return 1;
    }
}
