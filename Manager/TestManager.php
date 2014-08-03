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
        $zipArray = array(
        'fichier1M' => SyncConstant::SYNCHRO_DOWN_DIR.$user->getId()."/sync_474A1C5C-B54B-4DCF-AB46-B8AB47CBB2B0.zip",
        'fichier5M' =>  SyncConstant::SYNCHRO_DOWN_DIR.$user->getId()."/sync_F9024E90-9C91-4B73-94CE-1F2A8317495F.zip",
        'fichier10M' =>  SyncConstant::SYNCHRO_DOWN_DIR.$user->getId()."/sync_3F28AD04-58DC-458C-B09E-4A19E630588D.zip",
        'fichier25M' =>  SyncConstant::SYNCHRO_DOWN_DIR.$user->getId()."/sync_C179B49F-5183-4D96-A13A-97816E97EA16.zip",
        'fichier50M' =>  SyncConstant::SYNCHRO_DOWN_DIR.$user->getId()."/sync_9FEAF80B-DB57-4737-9CCB-DCE01871608F.zip");

        echo "DEBUT DU TEST<br/>";
        $rFile = fopen("test_transfer.txt", "w");
        fwrite($rFile, "DEBUT FICHIER TEST TAILLE PACKET\n");
        fwrite($rFile, "Taille des fragments : ".SyncConstant::MAX_PACKET_SIZE);
        foreach ($zipArray as $file) {
            echo "DOING ".$file."   ----------------<br/>";
            $this->testUploadFile($rFile, $file, $user);
        }
        fclose($rFile);
        echo "FIN DU TEST <br/>";
    }

    private function testUploadFile($report, $file, $user)
    {
        ini_set('max_execution_time', 0);
        fwrite($report, "\n------------------------------\n");
        fwrite($report, "TEST envoi fichier : ".$file."\n");
        fwrite($report, "Taille du fichier : ".filesize($file)."\n");
        for ($i = 0; $i < 10; $i++) {
            $begin = new Datetime;
            $this->transferManager->uploadArchive($file, $user, 0);
            $stop = new Datetime;
            // fwrite($report, print_r($stop->diff($begin)).",");
            // echo "      exe ".$i."    TEMPS : ".print_r($stop->diff($begin))."<br/>";
            $diffTime = $stop->diff($begin);
            $diffSec = $diffTime->i*60 + $diffTime->s;
            fwrite($report, $diffSec.",");
            echo "      exe ".$i."    TEMPS : ".$diffSec."<br/>";
        }
        fwrite($report, "\nEND TEST\n");
    }

    private function testDownloadFile($report, $file, $user)
    {
        ini_set('max_execution_time', 0);
        fwrite($report, "\n------------------------------\n");
        fwrite($report, "TEST telechargement fichier : ".$file."\n");
        fwrite($report, "Taille du fichier : ".filesize($file)."\n");
        $totalFrag = $this->transferManager->getNumberOfFragmentsOnline($file, $user);
        for ($i = 0; $i < 20; $i++) {
            $begin = new Datetime;
            $this->transferManager->downloadArchive($file, $totalFrag, $user, 0);
            $stop = new Datetime;
            fwrite($report, $stop-$begin.",");
        }
        fwrite($report, "END TEST\n");
    }
}
