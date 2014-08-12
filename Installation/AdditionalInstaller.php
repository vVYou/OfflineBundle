<?php

namespace Claroline\OfflineBundle\Installation;

use Claroline\InstallationBundle\Additional\AdditionalInstaller as BaseInstaller;

class AdditionalInstaller extends BaseInstaller
{
    private $logger;

    public function __construct()
    {
        $self = $this;
        $this->logger = function ($message) use ($self) {
            $self->log($message);
        };
    }

    public function preUpdate($currentVersion, $targetVersion)
    {
        $updater = new Updater000001();
        $updater->preUpdate();
    }

    public function postUpdate($currentVersion, $targetVersion)
    {
    }
}