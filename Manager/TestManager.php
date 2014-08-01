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
use Claroline\OfflineBundle\SyncConstant;
use Claroline\OfflineBundle\Entity\UserSynchronized;
use JMS\DiExtraBundle\Annotation as DI;
use \DateTime;

/**
 * @DI\Service("claroline.manager.test_offline_manager")
 */
class TestManager
{
    private $om;
    private $transferManager;

    /**
     * Constructor.
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "transferManager" = @DI\Inject("claroline.manager.transfer_manager")
     * })
     */
    public function __construct(
        ObjectManager $om,
        TransferManager $transferManager
    )
    {
        $this->om = $om;
        $this->transferManager = $transferManager;
    }

    public function testTransfer($user)
    {
        $fichier1M = "";
        $fichier5M = "";
        $fichier10M = "";
        $fichier50M = "";
        
        echo "DEBUT DU TEST<br/>";
        $rFile = fopen("test_transfer.txt", "w");
        fwrite($rFile, "DEBUT FICHIER TEST TAILLE PACKET\n");
        fclose($rFile);
        echo "FIN DU TEST <br/>";
    }
    
    private function testUploadFile($report, $file, $user)
    {
        fwrite($report, "\n------------------------------\n");
        fwrite($report, "TEST envoi fichier : ".$file."\n");
        for($i = 0; $i < 20; $i++)
        {
            $begin = new Datetime;
            $this->transferManager->uploadArchive($file, $user, 0);
            $stop = new Datetime;
            fwrite($report, $stop-$begin.",");
        }
        fwrite($report, "END TEST\n");
    }
    
    private function testDownloadFile($report, $file, $user)
    {
        fwrite($report, "\n------------------------------\n");
        fwrite($report, "TEST telechargement fichier : ".$file."\n");
        $totalFrag = $this->transferManager->getNumberOfFragmentsOnline($file, $user);
        for($i = 0; $i < 20; $i++)
        {
            $begin = new Datetime;
            $this->transferManager->downloadArchive($file, $totalFrag, $user, 0);
            $stop = new Datetime;
            fwrite($report, $stop-$begin.",");
        }
        fwrite($report, "END TEST\n");
    }
}
