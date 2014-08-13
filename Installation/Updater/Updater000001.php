<?php

namespace Claroline\OfflineBundle\Installation\Updater;

class Updater000001
{

    private $container;
    private $logger;

    public function __construct($container)
    {
        $this->container = $container;
    }

    public function preUpdate()
    {
        $ds = DIRECTORY_SEPARATOR;
        $src = __DIR__ .$ds.'..'.$ds.'..'.$ds.'Resources'.$ds;
        $kernDir = $this->container->getParameter('kernel.root_dir');
        $dest = $kernDir.$ds.'..'.$ds.'web'.$ds;
        copy($src.'app_offline.php', $dest.$ds.'app_offline.php');
        copy($src.'offline_install.php', $dest.'offline_install.php');
        copy($src.'loading_install.php', $dest.'loading_install.php');
    }

    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    private function log($message)
    {
        if ($log = $this->logger) {
            $log('    ' . $message);
        }
    }
}
