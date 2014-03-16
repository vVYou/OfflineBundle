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

    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "pagerFactory"   = @DI\Inject("claroline.pager.pager_factory"),
     *     "translator"     = @DI\Inject("translator")
     * })
     */
    public function __construct(
        ObjectManager $om,
        PagerFactory $pagerFactory,
        TranslatorInterface $translator
    )
    {
        $this->om = $om;
        $this->pagerFactory = $pagerFactory;
        $this->userSynchronizedRepo = $om->getRepository('ClarolineOfflineBundle:UserSynchronized');
        $this->translator = $translator;
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
        }
    }
    
    private function importPlateform($plateform)
    {
        $plateformChilds = $plateform->item(0)->childNodes;
        for($i = 0; $i<$plateformChilds->length; $i++)
        {
            //TODO CREER des constantes pour les fichier XML, ce sera plus propre que tout hardcode partout
            if($plateformChilds->item($i)->nodeName == 'workspace'){
                echo '      '.$plateformChilds->item($i)->nodeName.'<br/>';
                $this->importWorkspace($plateformChilds->item($i)->childNodes);
            }
        }
    }
    
    /**
    * Recupere un NodeList contenant les ressources  d'un workspace
    * Chaque ressource de cette NodeList devra donc être importee dans Claroline
    */
    private function importWorkspace($resourceList)
    {
        for($i=0; $i<$resourceList; $i++)
        {
            $res = $resourceList->item($i);
            echo '          '.$res->nodeName.'<br/>';
        }
    }
}