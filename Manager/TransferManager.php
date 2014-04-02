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
use Claroline\CoreBundle\Library\Utilities\ClaroUtilities;
use Claroline\OfflineBundle\Entity\UserSynchronized;
use Claroline\OfflineBundle\SyncConstant;
//use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use symfony\Component\Routing\Generator\UrlGeneratorInterface;
use JMS\DiExtraBundle\Annotation as DI;
use Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Entity\Resource\ResourceType;
use Claroline\CoreBundle\Entity\Resource\ResourceNode;
use \ZipArchive;
use \DateTime;
use \Buzz\Browser;
use \Buzz\Client\Curl;
use \Buzz\Client\FileGetContents;
use \Guzzle\Http\Client;
use \Guzzle\Http\Post\PostFile;
use \Guzzle\Http\EntityBody;
use \Guzzle\Http\EntityBodyInterface;
use \Guzzle\Http\Post\PostBodyInterface;


/**
 * @DI\Service("claroline.manager.transfer_manager")
 */

class TransferManager
{
    private $om;
    private $pagerFactory;
    private $translator;
    private $userSynchronizedRepo;
    private $resourceManager;
    private $router;
    private $ut;
    
    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "pagerFactory"   = @DI\Inject("claroline.pager.pager_factory"),
     *     "translator"     = @DI\Inject("translator"),
     *     "resourceManager"= @DI\Inject("claroline.manager.resource_manager"),
     *     "router"         = @DI\Inject("router"),
     *     "ut"            = @DI\Inject("claroline.utilities.misc")
     * })
     */
    public function __construct(
        ObjectManager $om,
        PagerFactory $pagerFactory,
        TranslatorInterface $translator,
        ResourceManager $resourceManager,
        UrlGeneratorInterface $router,
        ClaroUtilities $ut
    )
    {
        $this->om = $om;
        $this->pagerFactory = $pagerFactory;
        $this->userSynchronizedRepo = $om->getRepository('ClarolineOfflineBundle:UserSynchronized');
        $this->translator = $translator;
        $this->resourceManager = $resourceManager;
        $this->router = $router;
        $this->ut = $ut;
    }
    
    
    /*
    *   @param User $user
    */
    public function getSyncZip(User $user)    
    {
    /*
    *   Objectif de la methode :
    *   appeler le serveur distant en lui demandant d'envoyer son zip de synchro
    *   via cette requete, injecter mon zip de synchro local dedans
    *   enregistrer le zip recu en retour pour le traiter en local
    */
    
    // ATTENTION, droits d'ecriture de fichier
        $client = new Curl();
        $client->setTimeout(45);
        $browser = new Browser($client);
        
        //TODO Constante pour l'URL du site, ce sera plus propre
        
        //$reponse = $browser->get(SyncConstant::PLATEFORM_URL.$user->getId());        
        
        /*  Browser post signature
        *   public function post($url, $headers = array(), $content = '')
        *   Utilisation de la methode POST de HTML et non la methode GET pour pouvoir injecter des données en même temps.
        */
        //TODO Header = array vide ??? peut etre que j'oublie de declarer le content que je pousse derriere
        
        //$inside_zip = '';
        //$ds = DIRECTORY_SEPARATOR; 
        //$filePointer = fopen('synchronize_up'.$ds.$user->getId().$ds.'sync.zip', 'r');
       /* if(! ($filePointer = fopen('sync.zip', 'r'))){
            echo 'echec de l\'ouverture du fichier';
        }else{
            while(!feof($filePointer)){
                //$inside_zip .=fgets($filePointer);
                echo fgets($filePointer);
            }
            fclose($filePointer);
            echo '<br/>***********************<br/>'.$inside_zip.'<br/>***********************<br/>';
        }
        
        $reponse = $browser->post(SyncConstant::PLATEFORM_URL.'/sync/getzip/'.$user->getId(), array(), $inside_zip);        
        */
        $filename = './synchronize_up/'.$user->getId().'/sync_F8673788-EB93-4F78-85C3-4C7ACAB1802F.zip';
        $handle = fopen($filename, 'r');
        $reponse = $browser->post(SyncConstant::PLATEFORM_URL.'/sync/getzip/'.$user->getId(), array(), fread($handle, filesize($filename)) );        
        
        
        //$reponse = $browser->post(SyncConstant::PLATEFORM_URL.'/sync/getzip/'.$user->getId(), array(), './synchronize_up/'.$user->getId().'/sync.zip' );    
        $content = $reponse->getContent();
        
       // echo $browser->getLastRequest().'<br/>';
        
        $hashname = $this->ut->generateGuid();
        $zip_path = './synchronize_down/'.$user->getId().'/sync_'.$hashname.'.zip';
        //TODO Check ouverture du fichier
        $zipFile = fopen($zip_path, 'w+');
        $write = fwrite($zipFile, $content);
        if(!$write){
        //SHOULD RETURN ERROR
            echo 'An ERROR happen re-writing zip file at reception<br/>';
        }
        fclose($zipFile);
        //TODO Controller erreur a la fermeture
        //echo 'TRANSFER PASS !';
        
        return $zip_path;
    }
    
    /*
    * @param User $user
    */
    public function transferZip(User $user)
    {
        $client = new Client();
       // echo 'tiemout<br/>';
        $response = $client->post(SyncConstant::PLATEFORM_URL.'/sync/getzip/'.$user->getId(), [
            'body' => [
                'field_name' => 'abc',
                'file_filed' => fopen('./synchronize_up/'.$user->getId().'/sync.zip', 'r')
            ],
            'timeout' => 45
        ]);
       
        /*
        $request = $client->createRequest('POST', SyncConstant::PLATEFORM_URL.'/sync/getzip/'.$user->getId());
        $postBody = $request->getBody();
        echo 'Body :=  '.get_class($postBody).'<br/>';
        $postBody->setField('filename', 'sync_zip');
        //$postBody->addFile(new PostFile('file',  fopen('./synchronize_up/'.$user->getId().'/sync.zip', 'r')));
        //$response = $client->send($request);
        */
        echo 'TRANSFER PASS 2 <br/>';
    }
  
}
