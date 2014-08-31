<?php
 
namespace Claroline\OfflineBundle\Model;

use Claroline\OfflineBundle\Model\DisableListener;

class DisableListenerI {
 
   private static $_instance = null;

   private function __construct() {  
   }

   public static function getInstance() {
 
     if(is_null(self::$_instance)) {
       self::$_instance = new DisableListener();  
     }
 
     return self::$_instance;
   }
}
