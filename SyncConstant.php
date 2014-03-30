<?php

namespace Claroline\OfflineBundle;


class SyncConstant
{
    // ResourceType Constant
    const FILE = 1;
    const DIR = 2;
    const TEXT = 3;
    const FORUM = 9;
    
    // Zip Constant
    const DIRZIP = './extractedZip';
    const MANIFEST = 'manifest';
    const ZIPFILEDIR = '/files/';
}