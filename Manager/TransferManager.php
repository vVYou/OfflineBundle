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
    public function transferZip($toTransfer, User $user)    
    {   /*
        *   Objectif de la methode :
        *   appeler le serveur distant en lui demandant d'envoyer son zip de synchro
        *   via cette requete post, injecter mon zip de synchro local dedans
        *   enregistrer le zip recu en retour pour le traiter en local
        */
    
        // ATTENTION, droits d'ecriture de fichier
        
        //Declaration du client HTML Buzz
        $client = new Curl();
        $client->setTimeout(60);
        $browser = new Browser($client);
        
        //Browser post signature                public function post($url, $headers = array(), $content = '')
        //Utilisation de la methode POST de HTML et non la methode GET pour pouvoir injecter des données en même temps.
        
        //TODO dynamique zip file name - constante repertoire sync_up et sync_down
        
        $handle = fopen($toTransfer, 'r');
        echo 'ca part <br/>';
        $reponse = $browser->post(SyncConstant::PLATEFORM_URL.'/sync/getzip/'.$user->getId(), array(), fread($handle, filesize($toTransfer)) );        
        echo 'ca revient<br/>';
        $content = $reponse->getContent();
       // echo $browser->getLastRequest().'<br/>';
        
        $hashname = $this->ut->generateGuid();
        $zip_path = SyncConstant::SYNCHRO_DOWN_DIR.$user->getId().'/sync_'.$hashname.'.zip';
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
    *
    *   GUZZLE example, did not work for know, but we hope to have it soon, it will be better
    *
    * @param User $user
    */
    public function getSyncZip(User $user)
    {
        $client = new Client();
       // echo 'tiemout<br/>';
        $response = $client->post(SyncConstant::PLATEFORM_URL.'/sync/getzip/'.$user->getId(), [
            'body' => [
                'field_name' => 'abc',
                'file_filed' => fopen(SyncConstant::SYNCHRO_UP_DIR.$user->getId().'/sync.zip', 'r')
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
  
  
    public function processSyncRequest($request, $user)
    {
        $content = $request->getContent();
        //TODO Verifier le fichier entrant
        //TODO Gestion dynamique du nom du fichier arrivant
        
        $hashname = $this->ut->generateGuid();
        $zipFile = fopen(SyncConstant::SYNCHRO_UP_DIR.$user.'/sync_'.$hashname.'.zip', 'w+');
        $write = fwrite($zipFile, $content);
        $zipName = $zipFile->filename;
        fclose($zipFile);
        return $zipName;
    }
}
