<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * This class contains all Constants used
 * in the synchronisation process.
 */

namespace Claroline\OfflineBundle\Model;

class SyncConstant
{

    //Plateform Constants
    const PLATEFORM_URL = 'http://127.0.0.1/Claroline_2/web/app_dev.php';
    const SYNCHRO_UP_DIR = './synchronize_up/';
    const SYNCHRO_DOWN_DIR = './synchronize_down/';
    //Maximum fragment sends by the network, fixed to size 512Ko convert in byte (512Ko = 512 * 1024)
    const MAX_FRAG_SIZE = 524288; // Value must be in bits.
    const PLAT_CONF = '../app/config/sync_config.yml';

    // ResourceType Constant
    const FILE = 1;
    const DIR = 2;
    const TEXT = 3;
    const FORUM = 9;

    // Zip Constant
    const DIRZIP = './extractedZip';
    const MANIFEST = 'manifest';

    //Forum Content Type Constant
    const CATE = "Claroline\ForumBundle\Entity\Category";
    const SUB = "Claroline\ForumBundle\Entity\Subject";
    const MSG = "Claroline\ForumBundle\Entity\Message";
}
