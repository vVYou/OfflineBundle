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

interface OfflineFile implements OfflineResource
{
   public function addResourceToManifest($domManifest, $domWorkspace, $resToAdd){
   
        parent::addResourceToManifest($domManifest, $domWorkspace, $resToAdd);
        $my_res = $this->resourceManager->getResourceFromNode($resToAdd);
        $size = $domManifest->createAttribute('size');
        $size->value = $my_res->getSize();
        $domRes->appendChild($size);
        $hashname = $domManifest->createAttribute('hashname');
        $hashname->value = $my_res->getHashName();
        $domRes->appendChild($hashname);
   }
   
   public function createResource(){
   }
   
   public function updateResource(){
   }
   
   public function createDoublon(){
   }
   
   private function addResourceToZip(ZipArchive $archive, $resToAdd, $user, $archive, $path){
        $my_res = $this->resourceManager->getResourceFromNode($resToAdd);
        $archive->addFile('..'.SyncConstant::ZIPFILEDIR.$my_res->getHashName(), 'data'.$path.SyncConstant::ZIPFILEDIR.$my_res->getHashName());         
   }
}
