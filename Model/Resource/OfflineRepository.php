<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\OfflineBundle\Model\Resource;

interface OfflineRepository implements OfflineResource
{
   public function addResourceToManifest($domManifest, $domWorkspace, $resToAdd){
       parent::addResourceToManifest($domManifest, $domWorkspace, $resToAdd);
   }
   
   public function createResource(){
   }
   
   public function updateResource(){
   }
   
   public function createDoublon(){
   }
}
