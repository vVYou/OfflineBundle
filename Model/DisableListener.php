<?php
 
namespace Claroline\OfflineBundle\Model;

class DisableListener {
 
   private $disable = false;

   public function __construct() {  
   }

   public function isDisable(){
	return $this->disable;
   }

   public function setDisable($val){
  	$this->disable = $val;
   }
}
