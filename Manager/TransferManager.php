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
    
    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "pagerFactory"   = @DI\Inject("claroline.pager.pager_factory"),
     *     "translator"     = @DI\Inject("translator"),
     *     "resourceManager"= @DI\Inject("claroline.manager.resource_manager"),
     *     "router"         = @DI\Inject("router")
     * })
     */
    public function __construct(
        ObjectManager $om,
        PagerFactory $pagerFactory,
        TranslatorInterface $translator,
        ResourceManager $resourceManager,
        UrlGeneratorInterface $router
    )
    {
        $this->om = $om;
        $this->pagerFactory = $pagerFactory;
        $this->userSynchronizedRepo = $om->getRepository('ClarolineOfflineBundle:UserSynchronized');
        $this->translator = $translator;
        $this->resourceManager = $resourceManager;
        $this->router = $router;
    }
    
    public function getSyncZip()
    {
        $client = new Curl();
        $client->setTimeout(30);
        $browser = new Browser($client);
        
        //TODO Constante pour l'URL du site, ce sera plus propre
        /*
        TODO rendre ca propre avec une route geree dynamiquement
        $route = $this->router->generate('claro_sync_get_zip');
        echo '<br/>This is my route !!! : '.$route.'<br/>';
        */
        
        //$reponse = $browser->get('http://127.0.0.1/test/index.html');
        //$reponse = $browser->get('127.0.0.1:14580/Claroline2/web/app_dev.php');
        $reponse = $browser->get('127.0.0.1:14580/Claroline2/web/app_dev.php/sync/getzip');
        
        echo $browser->getLastRequest().'<br/>';
        //echo 'REPONSE'.$reponse;
        
        $content = $reponse->getContent();
        
        //$zip = new ZipArchive();
        //$zip = (ZipArchive) $content;
        
        //echo '<br/>Zip name : '.$zip->filename;
        $content->extractTo(".");
        echo 'EXTRACT PASS !';
    }
  
}
