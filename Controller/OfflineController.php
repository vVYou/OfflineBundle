<?php

namespace Claroline\OfflineBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class OfflineController extends Controller
{

 /**
     * Get content by id
     *
     * @Route(
     *     "/sync",
     *     name="claro_sync"
     * )
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function helloAction()
    {
        $sync = $this->get('claroline_offline.synchronizer');
        if ($sync->isOk())
        {
           return $this->render('ClarolineOfflineBundle:Offline:ok.html.twig');
        }
        //return $this->render('ClarolineOfflineBundle:Offline:hello.html.twig');
    }
    
 /**
     * Get content by id
     *
     * @Route(
     *     "/test_2",
     *     name="claro_test"
     * )
     * @return \Symfony\Component\HttpFoundation\Response
     */    
    public function testAction()
    {
        return $this->render('ClarolineOfflineBundle:Offline:hello.html.twig');
    }
}
