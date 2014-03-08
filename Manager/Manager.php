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

/**
 * @DI\Service("claroline.manager.synchronize_manager")
 */
class Manager
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
     * Create a userSynchronized.
     * Its ID and the date of creation.
     *
     * @param \Claroline\CoreBundle\Entity\User $user
     *
     */
    public function createSyncZip(User $user){
        $zip = new ZipArchive(); 
        if($zip->open('plouf.zip', ZipArchive::CREATE) === true){
            echo '&quot;Zip.zip&quot; ouvert<br/>';
            $zip->addFromString('Fichier.txt', 'Je suis le contenu de Fichier.txt !');


            // Ajout d’un fichier.
            //$zip->addFile('Fichier.txt');

            // Ajout direct.
            //$zip->addFromString('Fichier.txt', 'Je suis le contenu de Fichier.txt !');

            // Et on referme l'archive.
            $zip->close();
            echo '&quot;Zip.zip&quot; ferme<br/>';
        }else{
            echo 'Impossible d&#039;ouvrir &quot;Zip.zip<br/>';
            // Traitement des erreurs avec un switch(), par exemple.
        }
        
        
        if ($zip->open('plouf.zip') === TRUE) {
            $zip->extractTo('C:\\plouf_zip\\');
            $zip->close();
            echo 'ok';
        } else {
            echo 'échec';
        }
        
        return $zip;
    }
}
