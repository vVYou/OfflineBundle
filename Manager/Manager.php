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
use Claroline\CoreBundle\Entity\Resource\ResourceType;
use Claroline\CoreBundle\Entity\Resource\ResourceNode;
use \ZipArchive;
use \DateTime;

/**
 * @DI\Service("claroline.manager.synchronize_manager")
 */
 
CONST FILE = 1;
CONST TEXT = 3;

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
    
     public function createSyncZip(User $user)
    {
        $archive = new ZipArchive(); 
        
        $userRes = array();
        $typeList = array('file', 'text'); // ! PAS OPTIMAL !
        $typeArray = $this->buildTypeArray($typeList);
        $userWS = $this->om->getRepository('ClarolineCoreBundle:Workspace\AbstractWorkspace')->findByUser($user);

        $manifest_name = 'manifest_'.time().'.xml';
        $manifest = fopen($manifest_name, 'w');
        fputs($manifest,'<manifest>');
        fputs($manifest, $this->writeManifestDescription($user));
        //echo get_resource_type($manifest).'<br/>';
 
        if($archive->open('archive.zip', ZipArchive::CREATE) === true)
        {
        fputs($manifest,'
    <plateform>');
            foreach($userWS as $element)
            {
                fputs($manifest, '
        <workspace id="'.$element->getId().'">');
                foreach($typeArray as $resType)
                {
                    $ressourcesToSync = array();
                    //$em_res = $this->getDoctrine()->getManager();
                    $userRes = $this->om->getRepository('ClarolineCoreBundle:Resource\ResourceNode')->findByWorkspaceAndResourceType($element, $resType);
                    if(count($userRes) >= 1)
                    {
                        $ressourcesToSync[] = $this->checkObsolete($userRes, $user);  // Remove all the resources not modified.
                        //echo get_class($ressourcesToSync);//Ajouter le resultat dans l'archive Zip
                        fputs($manifest, $this->add_archive($ressourcesToSync, $archive, $resType));
                        //echo "<br/>".count($ressourcesToSync)."<br/>";
                    }
                }
                fputs($manifest, '
        </workspace>');
            }
        fputs($manifest,'
    </plateform>');
           
            /*return array(
                'user_courses' => $userWS,
                'user_res' => $userRes
            );*/
        }
        else
        {
            //TODO REPLACE BY EXCEPTION
            echo 'Impossible to open the zip file';
        }
        fputs($manifest,'
</manifest>');
        fclose($manifest);
        $archive->addFile($manifest_name);
        
        $archive->close();
        return $archive;
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
            //echo 'La date de mon cours :';
            //echo $resource->getModificationDate()->format('Y-m-d') . "<br/>";
            $res_tmp = $resource->getModificationDate();
            $date_res = $res_tmp->getTimestamp();
            $interval = $date_res - $date_user;
            
            if($interval > 0)
            {
                //echo 'Name file : ';
                //echo $resource->getName() . "<br/>";
                //echo 'This file has been modified' . "<br/>";
                $new_res[] = $resource;
            }
            
        }
                
        //echo 'Ma date à moi :';
        //echo $dateSync[0]->getLastSynchronization()->format('Y-m-d') . "<br/>";
        return $resource;
        
    }
    
    /**
     * Create a the archive based on the user     
     * Attention, if the archive file created is empty, it will not write zip file on disk !
     *
     * @param \Claroline\CoreBundle\Entity\Resource\ResourceType $resType
     *
     */
     
    private function add_archive(array $obso, ZipArchive $archive, ResourceType $resType)
    {
        $workspace_resources = '';
        foreach($obso as $element)
        {
            switch($resType->getId())
            {
                case FILE :
                    $my_res = $this->resourceManager->getResourceFromNode($element);
                    
                    echo 'Le fichier : '. $element->getName() . "<br/>";
                    echo 'Add to the Archive' . "<br/>";
                    $archive->addFile('../files/'.$my_res->getHashName());
                    $workspace_resources = $workspace_resources.'
            <resource type="file" />';
                    break;
                case TEXT :
                    echo 'Le fichier : '. $element->getName() . "<br/>";
                    echo 'Work In Progress'. "<br/>";
                    $workspace_resources = $workspace_resources.'
            <resource type="text" />';
                    break;
            }
        }
        return $workspace_resources;
    }
    
   
    private function writeManifestDescription(User $user)
    {
        $dateSync = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);
        $user_tmp = $dateSync[0]->getLastSynchronization(); 
        $sync_timestamp = $user_tmp->getTimestamp();
            
        $current_time = time();
        //$current_timestamp = $current_time->getTimestamp();
        
        $description = '
    <description>
        <creation_date>'.$current_time.'</creation_date>
        <reference_date>'.$sync_timestamp.'</reference_date>
        <user>'.$user->getUsername().'</user>
        <user_id>'.$user->getId().'</user_id>
    </description>
        ';
        return $description;
    }
}
