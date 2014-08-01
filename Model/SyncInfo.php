<?php

/*
*   This class described a special data structure
*   used to display the results of the synchronisation to the user.
*/

namespace Claroline\OfflineBundle\Model;

class SyncInfo
{
    private $workspace;
    private $create;
    private $update;
    private $doublon;

    public function __construct()
    {
        $this->create = array();
        $this->update = array();
        $this->doublon = array();
    }

    public function getWorkspace()
    {
        return $this->workspace;
    }

    public function setWorkspace($ws)
    {
        $this->workspace = $ws;
    }

    public function addToCreate($resource)
    {
        $this->create[] = $resource;
    }

    public function addToUpdate($resource)
    {
        $this->update[] = $resource;
    }

    public function addToDoublon($resource)
    {
        $this->doublon[] = $resource;
    }

    public function getCreate()
    {
        return $this->create;
    }

    public function getUpdate()
    {
        return $this->update;
    }

    public function getDoublon()
    {
        return $this->doublon;
    }

}
