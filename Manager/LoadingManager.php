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
use Claroline\CoreBundle\Manager\WorkspaceManager;
use Claroline\CoreBundle\Manager\ResourceManager;
use \ZipArchive;
use \DOMDocument;
use \DOMElement;

/**
 * @DI\Service("claroline.manager.loading_manager")
 */
class LoadingManager
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
     *     "wsManager"      = @DI\Inject("claroline.manager.workspace_manager"),
     *     "resourceManager"= @DI\Inject("claroline.manager.resource_manager")
     * })
     */
    public function __construct(
        ObjectManager $om,
        PagerFactory $pagerFactory,
        TranslatorInterface $translator,
        WorkspaceManager $wsManager,
        ResourceManager $resourceManager
    )
    {
        $this->om = $om;
        $this->pagerFactory = $pagerFactory;
        $this->userSynchronizedRepo = $om->getRepository('ClarolineOfflineBundle:UserSynchronized');
        $this->translator = $translator;
        $this->wsManager = $wsManager;
        $this->resourceManager = $resourceManager;
    }
    
    /**
     * This method will load and parse the manifest XML file
     *
     *
     */
    public function loadXML($xmlFilePath){
        $xmlDocument = new DOMDocument();
        $xmlDocument->load($xmlFilePath);
        
        /*
        *   getElementsByTagName renvoit un NodeList
        *   Sur un NodeList on peut faire ->length et ->item($i) qui retourne un NodeItem
        *   sur un NodeItem on peut faire 
                ->nodeName
                ->nodeValue
                ->childNodes qui renvoit lui meme un NodeList. la boucle est bouclée
        */
        $this->importDescription($xmlDocument->getElementsByTagName('description'));
        $this->importPlateform($xmlDocument->getElementsByTagName('plateform'));
        
        /*
        echo $descriptionNodeList->item(0)->nodeName.'<br/>';
        
        for ($i = 0; $i < $descriptionNodeList->length; $i++) {
            echo 'i='.$i.'  '.$descriptionNodeList->item($i)->nodeValue. "<br/>";
            echo $descriptionNodeList->item(0)->childNodes->item(0)->nodeValue;
            
         $enfants = $descriptionNodeList->childNodes;
            foreach($enfants as $child){
            echo $child->item(0)->nodeName.'<br/>';
        }
        }*/
        
    }
    
    private function importDescription($documentDescription)
    {
        $descriptionChilds = $documentDescription->item(0)->childNodes;
        for($i = 0; $i<$descriptionChilds->length ; $i++){
            echo '$i : '.$i.' '.$descriptionChilds->item($i)->nodeName.' '.$descriptionChilds->item($i)->nodeValue.'<br/>' ;
            /*
            *   ICI on peut controler / stocker les metadata du manfiest
            */
            if($descriptionChilds->item($i)->nodeName == 'user_id')
            {
                //$this->user = $descriptionChilds->item($i)->nodeValue;
            }
        }
        //echo $this->user;
    }
    
    private function importPlateform($plateform)
    {
        $plateformChilds = $plateform->item(0)->childNodes;
        for($i = 0; $i<$plateformChilds->length; $i++)
        {
            //TODO CREER des constantes pour les fichier XML, ce sera plus propre que tout hardcode partout
            if($plateformChilds->item($i)->nodeName == 'workspace'){
                $workspace = $plateformChilds->item($i);
                $work_id = $workspace->getAttribute('id');              
                echo $work_id.'<br/>';
                
                /*
                *   On recupere les differents arguments necessaire pour construire le workspace 
                *   si besoin (cad qu'il n'existe aucun workspace avec un code similaire).
                *
                *   - if workspace_code do not exist then create the workspace
                *       
                *   - proceed to the ressources (no matter if we have to create the workspace previously
                */
                
                
                $this->importWorkspace($workspace->childNodes);
            }
        }
    }
    
    private function createWorkspace($workspace)
    {
        $ds = DIRECTORY_SEPARATOR;
        $type = $workspace->getAttribute('type') == "Claroline\CoreBundle\Entity\Workspace\SimpleWorkspace" ?
            Configuration::TYPE_SIMPLE :
            Configuration::TYPE_AGGREGATOR;
        $config = Configuration::fromTemplate(
            $this->templateDir . $ds . $workspace->getAttribute('type')
        );
        $config->setWorkspaceType($type);
        $config->setWorkspaceName($workspace->getAttribute('name'));
        $config->setWorkspaceCode($workspace->getAttribute('code'));
        $config->setDisplayable($$workspace->getAttribute('displayable'));
        $config->setSelfRegistration($workspace->getAttribute('selfregistration'));
        $config->setSelfUnregistration($workspace->getAttribute('selfunregistration'));
        //$user = $this->security->getToken()->getUser();
        $this->wsManager->create($config, $user);
    }
    
    /**
    * Recupere un NodeList contenant les ressources  d'un workspace
    * Chaque ressource de cette NodeList devra donc être importee dans Claroline
    */
    private function importWorkspace($resourceList)
    {
        for($i=0; $i<$resourceList->length; $i++)
        {
            $res = $resourceList->item($i);
            echo 'Workspace :          '.$res->nodeName.'<br/>';
            //echo 'Attribute : '.$res->getAttribute('type').'<br/>';
            
        }
    }
}