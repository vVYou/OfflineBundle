<?php

/*
 * This class contains all Constants used
 * in the synchronisation process.
 */

namespace Claroline\OfflineBundle;

class SyncConstant
{

    //Plateform Constants
    const PLATEFORM_URL = 'http://127.0.0.1/Claroline_2/web/app_dev.php';
    const SYNCHRO_UP_DIR = './synchronize_up/';
    const SYNCHRO_DOWN_DIR = './synchronize_down/';
    const MAX_PACKET_SIZE = 262144; //Maximum packet sends by the network, fixed to size 256Ko convert in byte (256Ko = 256 * 1024)
    const PLAT_CONF = '../app/config/sync_config.yml';

    // ResourceType Constant
    const FILE = 1;
    const DIR = 2;
    const TEXT = 3;
    const FORUM = 9;

    // Zip Constant
    const DIRZIP = './extractedZip';
    const MANIFEST = 'manifest';
    const ZIPFILEDIR = '/files/';

    //Forum Content Type Constant
    const CATE = "Claroline\ForumBundle\Entity\Category";
    const SUB = "Claroline\ForumBundle\Entity\Subject";
    const MSG = "Claroline\ForumBundle\Entity\Message";

    //Installation Constant
    const COMP_PATH_WIN = "../../../offline_component/win";
    const APP_CACHE = '/app/cache/';
    const LOG = '/app/logs/';
    // const SYNC_UP = '/web/synchronize_up/';
    // const SYNC_DW = '/web/synchronize_down/';
    const PLAT_FILES = '/files/';
}
