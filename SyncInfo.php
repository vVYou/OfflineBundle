<?php

/*
*   This class described a special data structure
*   used to display the results of the synchronisation to the user.
*/

namespace Claroline\OfflineBundle;


class SyncInfo
{
    private $workspace;
    private $add;
    private $update;
    private $doublon;
    
    public function __construct()
    {
        $this->add = array();
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
    
    public function addToAdd($resource)
    {
        $this->add[] = $resource;
    }
    
    public function addToUpdate($resource)
    {
        $this->update[] = $resource;
    }
    
    public function addToDoublon($resource)
    {
        $this->doublon[] = $resource;
    }
    
    public function getAdd()
    {
        return $this->add;
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