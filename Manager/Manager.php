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
use Claroline\OfflineBundle\SyncConstant;
use Claroline\CoreBundle\Library\Utilities\ClaroUtilities;
use \ZipArchive;
use \DateTime;

/**
 * @DI\Service("claroline.manager.synchronize_manager")
 */
 
//CONST FILE = 1;
//CONST DIR = 2;
//CONST TEXT = 3;
//CONST FORUM = 9;

class Manager
{
    private $om;
    private $pagerFactory;
    private $translator;
    private $userSynchronizedRepo;
    private $revisionRepo;
    private $resourceManager;
    private $ut;

    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "pagerFactory"   = @DI\Inject("claroline.pager.pager_factory"),
     *     "translator"     = @DI\Inject("translator"),
     *     "resourceManager"= @DI\Inject("claroline.manager.resource_manager"),
     *     "ut"            = @DI\Inject("claroline.utilities.misc")
     * })
     */
    public function __construct(
        ObjectManager $om,
        PagerFactory $pagerFactory,
        TranslatorInterface $translator,
        ResourceManager $resourceManager,
        ClaroUtilities $ut
    )
    {
        $this->om = $om;
        $this->pagerFactory = $pagerFactory;
        $this->userSynchronizedRepo = $om->getRepository('ClarolineOfflineBundle:UserSynchronized');
        $this->revisionRepo = $om->getRepository('ClarolineCoreBundle:Resource\Revision');
        $this->translator = $translator;
        $this->resourceManager = $resourceManager;
        $this->ut = $ut;
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
        $syncTime = time();
        
        $userRes = array();
        $typeList = array('file', 'directory', 'text'); // ! PAS OPTIMAL !
        $typeArray = $this->buildTypeArray($typeList);
        $userWS = $this->om->getRepository('ClarolineCoreBundle:Workspace\AbstractWorkspace')->findByUser($user);
        
        $hashname_zip = $this->ut->generateGuid(); 
        
        $manifestName = SyncConstant::MANIFEST.'_'.$user->getId().'.xml';
        $manifest = fopen($manifestName, 'w');
        fputs($manifest,'<manifest>');
        $this->writeManifestDescription($manifest, $user, $syncTime);
        //echo get_resource_type($manifest).'<br/>';
 
        if($archive->open('sync_'.$hashname_zip.'.zip', ZipArchive::CREATE) === true)
        {
        fputs($manifest,'
    <plateform>');
    
           $this->fillSyncZip($userWS, $manifest, $typeArray, $user, $archive);
        fputs($manifest,'
    </plateform>');
           
            /*return array(
                'user_courses' => $userWS,
                'user_res' => $userRes
            );*/
        }
        else
        {
            //echo 'Impossible to open the zip file';
            throw new \Exception('Impossible to open the zip file');
        }
        fputs($manifest,'
</manifest>');
        fclose($manifest);
        
        $archive->addFile($manifestName);
        $archive->close();
        
        // Erase the manifest from the current folder.
        unlink($manifestName);
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
    
    private function fillSyncZip($userWS, $manifest, $typeArray, $user, $archive)
    {
        foreach($userWS as $element)
        {
            $this->addWorkspaceToManifest($manifest, $element);
            foreach($typeArray as $resType)
            {
                $ressourcesToSync = array();
                //$em_res = $this->getDoctrine()->getManager();
                $userRes = $this->om->getRepository('ClarolineCoreBundle:Resource\ResourceNode')->findByWorkspaceAndResourceType($element, $resType);
                if(count($userRes) >= 1)
                {
                    $path = '';
                    $ressourcesToSync = $this->checkObsolete($userRes, $user);  // Remove all the resources not modified.
                    //echo get_class($ressourcesToSync);//Ajouter le resultat dans l'archive Zip
                    $this->addResourcesToArchive($ressourcesToSync, $archive, $manifest, $user, $path);
                    //echo "<br/>".count($ressourcesToSync)."<br/>";
                }
            }
            fputs($manifest, '
        </workspace>');
        }
    }
    
    /*
    *   Filter all the resources based on the user's last synchronization and
    *   check which one need to be sent.
    */
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
            //echo $resource->getName() . "<br/>";
            //echo $interval . "<br/>";
            
            
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
        return $new_res;
        
    }
    
    
    /**
     * Create a the archive based on the user     
     * Attention, if the archive file created is empty, it will not write zip file on disk !
     *
     */
    private function addResourcesToArchive(array $resToAdd, ZipArchive $archive, $manifest, $user, $path)
    {
        foreach($resToAdd as $element)
        {
            $this->addResourceToManifest($manifest, $element);
            $this->addResourceToZip($archive, $element, $user, $archive, $manifest, $path);
        }
    }
    
    private function addResourceToZip(ZipArchive $archive, $resToAdd, $user, $archive, $manifest, $path)
    {
        switch($resToAdd->getResourceType()->getId())
        {
            case SyncConstant::FILE :
                $my_res = $this->resourceManager->getResourceFromNode($resToAdd);
                //echo 'Le fichier : '. $resToAdd->getName() . "<br/>";
                //echo 'Add to the Archive' . "<br/>";
                //$path = $path.$resToAdd->getWorkspace()->getId();
                $archive->addFile('..'.SyncConstant::ZIPFILEDIR.$my_res->getHashName(), 'data/'.$path.SyncConstant::ZIPFILEDIR.$my_res->getHashName());
                //$archive->renameName('../files/'.$my_res->getHashName(), 'data/'.$workspace_id.'/files/'.$my_res->getHashName());
                break;
            case SyncConstant::DIR :
                // TOREMOVE SI BUG! ATTENTION LES WORKSPACES SONT AUSSI DES DIRECTORY GARE AU DOUBLE CHECK
                //$my_res = $this->resourceManager->getResourceFromNode($resToAdd);
                //$this->resourceFromDir($resToAdd, $user, $archive, $manifest, $path);
                break;
            case SyncConstant::TEXT :
                //echo 'Le fichier : '. $resToAdd->getName() . "<br/>";
                //echo 'Work In Progress'. "<br/>";
                break;
        }
    }

    /*
    *   Here figure all methods used to manipulate the xml file.
    */
    
    private function addResourceToManifest($manifest, $resToAdd)
    {
        $type = $resToAdd->getResourceType()->getId();
        $creation_time = $resToAdd->getCreationDate()->getTimestamp();  
        $modification_time = $resToAdd->getModificationDate()->getTimestamp(); 
        
        switch($type)
        {
            case SyncConstant::FILE :
                $my_res = $this->resourceManager->getResourceFromNode($resToAdd);
                //echo 'My res class : '.get_class($my_res).'<br/>';
                //$creation_time = $resToAdd->getCreationDate()->getTimestamp();  
                //$modification_time = $resToAdd->getModificationDate()->getTimestamp();                
                
                fputs($manifest, '
                    <resource type="'.$resToAdd->getResourceType()->getName().'"
                    name="'.$resToAdd->getName().'"  
                    mimetype="'.$resToAdd->getMimeType().'"
                    creator="'.$resToAdd->getCreator()->getId().'"
                    size="'.$my_res->getSize().'"
                    hashname="'.$my_res->getHashName().'"
                    hashname_node="'.$resToAdd->getNodeHashName().'"
                    hashname_parent="'.$resToAdd->getParent()->getNodeHashName().'"
                    creation_date="'.$creation_time.'"
                    modification_date="'.$modification_time.'">
                    </resource>
                    ');
                break;
            case SyncConstant::DIR :
                // TOREMOVE SI BUG! ATTENTION LES WORKSPACES SONT AUSSI DES DIRECTORY GARE AU DOUBLE CHECK
                if($resToAdd->getParent() != NULL)
                {
                    //$creation_time = $resToAdd->getCreationDate()->getTimestamp();
                    //$modification_time = $resToAdd->getModificationDate()->getTimestamp();                    
                    
                fputs($manifest, '
                    <resource type="'.$resToAdd->getResourceType()->getName().'"
                    name="'.$resToAdd->getName().'"  
                    mimetype="'.$resToAdd->getMimeType().'"
                    creator="'.$resToAdd->getCreator()->getId().'"
                    hashname_node="'.$resToAdd->getNodeHashName().'"
                    hashname_parent="'.$resToAdd->getParent()->getNodeHashName().'"
                    creation_date="'.$creation_time.'"
                    modification_date="'.$modification_time.'">
                    </resource>
                    ');
                }
                break;
            case SyncConstant::TEXT :
                $my_res = $this->resourceManager->getResourceFromNode($resToAdd);  

                $revision = $this->revisionRepo->findOneBy(array('text_id' => $my_res->getId()));
                //$creation_time = $resToAdd->getCreationDate()->getTimestamp();
                //$modification_time = $resToAdd->getModificationDate()->getTimestamp();
                
                fputs($manifest, '
                    <resource type="'.$resToAdd->getResourceType()->getName().'"
                    name="'.$resToAdd->getName().'"  
                    mimetype="'.$resToAdd->getMimeType().'"
                    creator="'.$resToAdd->getCreator()->getId().'"
                    version="'.$my_res->getVersion().'"
                    hashname_node="'.$resToAdd->getNodeHashName().'"
                    hashname_parent="'.$resToAdd->getParent()->getNodeHashName().'"
                    content="'.$revision->getContent().'"
                    creation_date="'.$creation_time.'"
                    modification_date="'.$modification_time.'">
                    </resource>
                    ');
                break;
        }
    }    
    
    private function addWorkspaceToManifest($manifest, $workspace)
    {
        $my_res_node = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findResourceNodeByWorkspace($workspace);
        //echo 'Ma creation_time : '.$my_res_node[0]->getCreationDate()->format('Y-m-d H:i:s').'<br/>';
        //echo 'Ma modification_time : '.$my_res_node[0]->getModificationDate()->format('Y-m-d H:i:s').'<br/>';
        $creation_time = $my_res_node[0]->getCreationDate()->getTimestamp();  
        $modification_time = $my_res_node[0]->getModificationDate()->getTimestamp(); 
        
        fputs($manifest,  '
        <workspace id="'.$workspace->getId().'"
        type="'.get_class($workspace).'"
        creator="'.$workspace->getCreator()->getId().'"
        name="'.$workspace->getName().'"
        code="'.$workspace->getCode().'"
        displayable="'.$workspace->isDisplayable().'"
        selfregistration="'.$workspace->getSelfRegistration().'"
        selfunregistration="'.$workspace->getSelfUnregistration().'"
        guid="'.$workspace->getGuid().'"
        hashname_node="'.$my_res_node[0]->getNodeHashName().'"
        creation_date="'.$creation_time.'"
        modification_date="'.$modification_time.'">
        ');
    }
    
    private function writeManifestDescription($manifest, User $user, $syncTime)
    {
        $dateSync = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);
        $user_tmp = $dateSync[0]->getLastSynchronization(); 
        $sync_timestamp = $user_tmp->getTimestamp();
        
            
        //$current_time = time();
        //$current_timestamp = $current_time->getTimestamp();
        
        fputs($manifest ,'
    <description>
        <creation_date>'.$syncTime.'</creation_date>
        <reference_date>'.$sync_timestamp.'</reference_date>
        <user>'.$user->getUsername().'</user>
        <user_id>'.$user->getId().'</user_id>
    </description>
        ');
    }
}
