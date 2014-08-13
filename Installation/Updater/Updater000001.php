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
        $src = __DIR__ . $ds . '..' . $ds . '..' . $ds . 'Resources' . $ds . 'app_offline.php';
        echo $src;
        $kernDir = $this->container->getParameter('kernel.root_dir');
        $dest = $kernDir . $ds . '..' . $ds . 'web' . $ds;
        echo $dest;
        copy($src, $dest . $ds . 'app_offline.php');
        $src = __DIR__ . $ds . '..' . $ds . '..' . $ds . 'Resources' . $ds . 'offline_install.php';
        copy($src, $dest . $ds . 'offline_install.php');
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