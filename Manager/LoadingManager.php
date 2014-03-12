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
    public function loadXML( $xmlFilePath){
        $xml_document = new DOMDocument();
        $xml_document->load($xmlFilePath);
        
        
    }
}