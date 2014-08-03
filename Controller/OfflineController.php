<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\OfflineBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as EXT;
use JMS\DiExtraBundle\Annotation as DI;
use JMS\SecurityExtraBundle\Annotation as SEC;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Entity\Workspace\Workspace;
use Claroline\CoreBundle\Entity\ResourceNode;
use Claroline\OfflineBundle\Model\SyncConstant;
use Claroline\OfflineBundle\Model\SyncInfo;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Dumper;

/**
 * @DI\Tag("security.secure_service")
 * @SEC\PreAuthorize("hasRole('ROLE_USER')")
 */
class OfflineController extends Controller
{
    private $om;
    private $router;
    private $request;
    private $resourceNodeRepo;
    private $yamlparser;
    private $yaml_parser;
    private $yaml_dump;

    /**
    * @DI\InjectParams({
    *      "router"             = @DI\Inject("router"),
    *     "request"            = @DI\Inject("request"),
         *     "om"             = @DI\Inject("claroline.persistence.object_manager")
    * })
    */
    public function __construct(
        UrlGeneratorInterface $router,
        Request $request,
                ObjectManager $om
    )
    {
       $this->router = $router;
       $this->request = $request;
               $this->om = $om;
       $this->resourceNodeRepo = $om->getRepository('ClarolineCoreBundle:Resource\ResourceNode');
       $this->yaml_parser = new Parser();
       $this->yaml_dump = new Dumper();
    }

    /**
     * Get content by id
     *
     * @EXT\Route(
     *     "/sync",
     *     name="claro_sync"
     * )
     * @EXT\ParamConverter("user", options={"authenticatedUser" = true})
     *
     * @EXT\Template("ClarolineOfflineBundle:Offline:connect_ok.html.twig")
     * @param User $user
     *
     * @return Response
     */
    public function helloAction(User $user)
    {
        // $em = $this->getDoctrine()->getManager();
        // $userSync = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);

        // $username = $user->getFirstName() . ' ' . $user->getLastName();

        // if ($userSync) {
        // TODO Liens vers la route de synchronisation
            // return $this->render('ClarolineOfflineBundle:Offline:sync.html.twig', array(
                // 'user' => $username,
                // 'user_sync_date' => $userSync[0]->getLastSynchronization()
            // ) );
        // } else {
        // TODO Methode d'installation
            // return $this->render('ClarolineOfflineBundle:Offline:first_sync.html.twig', array(
                // 'user' => $username
            // ) );
        // }

        // $route = $this->router->generate('claro_sync_exchange');

        // return new RedirectResponse($route);

        $first_sync = false;

        return array(
           'first_sync' => $first_sync
        );

    }

    /**
     * Get result
     *
     * @EXT\Route(
     *     "/sync/result",
     *     name="claro_sync_result"
     * )
     * @EXT\ParamConverter("user", options={"authenticatedUser" = true})
     *
     * @EXT\Template("ClarolineOfflineBundle:Offline:result.html.twig")
     * @param User $user
     *
     * @return Response
     */
    public function resultAction(User $user)
    {
        $results = array();
        $result1 = new SyncInfo();
        $result1->setWorkspace('Mon Workspace 123');
        $result1->addToCreate('mon_premier_cours.pdf');
        $result1->addToUpdate('une_maj.odt');
        $result1->addToDoublon('un_doublon.calc');

        $results[] = $result1;

        $result2 = new SyncInfo();
        $result2->setWorkspace('Mon Workspace 10000');
        $result2->addToCreate('mon_premier_cours.pdf');
        $result2->addToCreate('mon_deuxieme_cours.pdf');
        $result2->addToDoublon('un_doublon.calc');
        $result2->addToDoublon('un_autre_doublon.calc');

        $results[] = $result2;

        $msg = $this->get('translator')->trans('sync_config_fail', array(), 'offline');

        $user_ws_rn = $this->resourceNodeRepo->findOneBy(array('workspace' => $user->getPersonalWorkspace(), 'parent' => NULL));
        $user_inf = $user->getUserAsTab();
        $user_inf[] = $user_ws_rn->getNodeHashName();
        $returnContent = $user_inf;

        return array(
           'results' => $results,
           'msg' => $msg
        );

    }

    /**
    *   Options of synchronisation modifications
    *
    *   @EXT\Route(
    *       "/sync/param",
    *       name="claro_sync_param"
    *   )
    *
    * @EXT\Template("ClarolineOfflineBundle:Offline:sync_param.html.twig")
    */
    public function syncParamAction()
    {
        return array(
           'msg' => ''
        );
    }

     /**
    *   @EXT\Route(
    *       "/sync/testTrans",
    *       name="claro_test_trans"
    *   )
    *
    * @EXT\ParamConverter("user", options={"authenticatedUser" = true})
    * @EXT\Template("ClarolineOfflineBundle:Offline:load.html.twig")
    *
    * @param User $user
    * @return Response
    */
    public function testTrans($user)
    {
        $this->get('claroline.manager.test_offline_manager')->testTransfer($user);

        return array(
            'user' => "plouf"
         );
    }


    /**
    *   Create userSyncrhonized entity
    *
    *   @EXT\Route(
    *       "/sync/load",
    *       name="claro_sync_load"
    *   )
    *
    * @EXT\ParamConverter("user", options={"authenticatedUser" = true})
    * @EXT\Template("ClarolineOfflineBundle:Offline:load.html.twig")
    *
    * @param User $user
    * @return Response
    */
    public function loadAction(User $user)
    {
        //$userSynchro = $this->get('claroline.manager.user_sync_manager')->createUserSynchronized($user);

        //$em = $this->getDoctrine()->getManager();
        //$userSynchroDate = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);

        //$zip = $this->get('claroline.manager.loading_manager')->loadXML('manifest_test_3.xml');
        /*
        $em = $this->getDoctrine()->getManager();
        $lol = $em->getEventManager();
        $tmp = array();
        $tmp = $lol->getListener();

        foreach ($tmp as $listener) {
            echo 'Listener found';
        }*/
        /*
        $dispatcher = $this->get('event_dispatcher');
        $tmp = array();
        $tmp = $dispatcher->getListeners();
        foreach ($tmp as $listener) {
            echo 'Boulou'.'<br/>';
        }
        */
       // $em = this->getDoctrine->

        //$bool = $dispatcher->hasListeners('Gedmo\Timestampable');

        //echo 'Bool : '.$bool.'<br/>';

        $zip = $this->get('claroline.manager.loading_manager')->loadZip('sync_D2DF8F72-D0E5-4E7A-A48D-08379822500D.zip', $user);

        $username = $user->getFirstName() . ' ' . $user->getLastName();

        return array(
            'user' => $username
         );
    }

    /**
    *   Create userSyncrhonized entity
    *
    *   @EXT\Route(
    *       "/sync/exchange",
    *       name="claro_sync_exchange"
    *   )
    *
    * @EXT\ParamConverter("authUser", options={"authenticatedUser" = true})
    * @EXT\Template("ClarolineOfflineBundle:Offline:result.html.twig")
    *
    * @param User $authUser
    * @return Response
    */
    public function syncAction(User $authUser)
    {  /**
        *   TODO MODIFY return with render different twig donc redirect plutot que le boolean true false
        */

        $em = $this->getDoctrine()->getManager();
        $userSync = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($authUser);
        try {
            $infoArray = $this->get('claroline.manager.synchronisation_manager')->synchroniseUser($authUser, $userSync[0]);
        } catch (AuthenticationException $e) {
            $msg = $this->get('translator')->trans('sync_config_fail', array(), 'offline');
            // $this->get('request')->getSession()->getFlashBag()->add('error', $msg);
        } catch (ProcessSyncException $e) {
            $msg = $this->get('translator')->trans('sync_server_fail', array(), 'offline');
            // $this->get('request')->getSession()->getFlashBag()->add('error', $msg);
        } catch (ServeurException $e) {
            $msg = $this->get('translator')->trans('sync_server_fail', array(), 'offline');
            // $this->get('request')->getSession()->getFlashBag()->add('error', $msg);
        } catch (PageNotFoundException $e) {
            $msg = $this->get('translator')->trans('sync_unreach', array(), 'offline');
            // $this->get('request')->getSession()->getFlashBag()->add('error', $msg);
        } catch (ClientException $e) {
            $msg = $this->get('translator')->trans('sync_client_fail', array(), 'offline');
            // $this->get('request')->getSession()->getFlashBag()->add('error', $msg);
        } catch (SynchronisationFailsException $e) {
            $msg = $this->get('translator')->trans('sync_fail', array(), 'offline');
        }
        // $i = $this->get('claroline.manager.synchronisation_manager')->getDownloadStop("24F0DCDC-3B64-4019-8D6A-80FBCEA68AF9", $authUser);
        // echo "last download : ".$i."<br/>";

        //Format the view
        $username = $authUser->getFirstName() . ' ' . $authUser->getLastName();

        return array(
            'results' => $infoArray,
            'msg' => $msg
         );

    }

    /**
    *
    *   Seek and show all the modified courses and ressources
    *
    *   @EXT\Route(
    *       "/sync/seek",
    *       name="claro_sync_seek"
    *   )
    *
    * @EXT\ParamConverter("user", options={"authenticatedUser" = true})
    * @EXT\Template("ClarolineOfflineBundle:Offline:courses.html.twig")
    *
    * @param User $user
    * @return Response
    */
    public function seekAction(User $user)
    {
        $em = $this->getDoctrine()->getManager();
        $userSyncTab = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);
        $test = $this->get('claroline.manager.creation_manager')->createSyncZip($user, ''.$userSyncTab[0]->getlastSynchronization()->getTimestamp());
        $username = $user->getFirstName() . ' ' . $user->getLastName();
        echo 'Congratulations '.$username.'! '."<br/>".'You are now synchronized!';
        echo ''.$test;

        return array(
            'user' => $username,
            //'user_courses' => $test['user_courses'],
            //'user_res' => $test['user_res']
        );
    }

    /**
    *
    *  Transfer a file (sync archive) from a computer to another
    *
    *   @EXT\Route(
    *       "/sync/transfer/{user}",
    *       name="claro_sync_transfertest"
    *   )
    *
    * @EXT\ParamConverter("authUser", options={"authenticatedUser" = true})
    * @EXT\Template("ClarolineOfflineBundle:Offline:transfer.html.twig")
    *
    * @param User $user
    * @return Reponse
    */
    public function transferAction($user, User $authUser)
    {
        $transfer = true;
        if ($user == $authUser->getId()) {
            $toTransfer = './synchronize_down/3/sync_0252D476-FD7D-4E39-9285-A53EDEFCAC90.zip';
            $test = $this->get('claroline.manager.transfer_manager')->uploadArchive($toTransfer, $authUser, 0);
        } else {
            $transfer = false;
        }

        $username = $authUser->getFirstName() . ' ' . $authUser->getLastName();

        return array(
            'user' => $username,
            'transfer' => $transfer
        );
    }

    /**
    *
    *  Transfer a file (sync archive) from a computer to another
    *
    *   @EXT\Route(
    *       "/sync/getsync/{user}",
    *       name="claro_sync_gettest"
    *   )
    *
    * @EXT\ParamConverter("authUser", options={"authenticatedUser" = true})
    * @EXT\Template("ClarolineOfflineBundle:Offline:transfer.html.twig")
    *
    * @param User $user
    * @return Reponse
    */
    public function getSyncAction($user, User $authUser)
    {
        $transfer = true;
        if ($user == $authUser->getId()) {
            $hashToGet = '1A7BE8A0-EE83-4853-93A4-63BABB8B8B84';
            $numPackets = 3;
            $test = $this->get('claroline.manager.transfer_manager')->downloadArchive($hashToGet, $numPackets, 0, $authUser);
            echo $test."<br/>";
        } else {
            $transfer = false;
        }

        $username = $authUser->getFirstName() . ' ' . $authUser->getLastName();

        return array(
            'user' => $username,
            'transfer' => $transfer
        );
    }

    /**
    *   @EXT\Route(
    *       "/sync/loadWorkspaces",
    *       name="claro_sync_load_workspace"
    *   )
    *
    * @EXT\ParamConverter("user", options={"authenticatedUser" = true})
    * @EXT\Template("ClarolineOfflineBundle:Offline:load.html.twig")
    *
    * @param User $user
    * @return Response
    */
    public function loadWorkspacesAction(User $user)
    {
        $zip = $this->get('claroline.manager.loading_manager')->loadPublicWorkspaceList(SyncConstant::SYNCHRO_UP_DIR.$user->getId().'/all_workspaces.xml');

        $username = $user->getFirstName() . ' ' . $user->getLastName();

        return array(
            'user' => $username
         );
    }


    /**
    *   @EXT\Route(
    *       "/sync/getuser",
    *       name="claro_sync_getuser"
    *   )
    *
    * @EXT\ParamConverter("user", options={"authenticatedUser" = true})
    * @EXT\Template("ClarolineOfflineBundle:Offline:load.html.twig")
    *
    * @param User $user
    * @return Response
    */
    public function getUserAction(User $user)
    {
        //TODO change "password", with a window getting password in clear
        $this->get('claroline.manager.transfer_manager')->getUserInfo($user->getUsername(), "password");

        $username = $user->getFirstName() . ' ' . $user->getLastName();

        return array(
            'user' => $username
         );
    }

    /**
     *  Allow the user to modify the URL contacted during the synchronisation
     *
     * @Route(
     *     "/url/form/edit",
     *     name="sync_url_edit_form"
     * )
     * @EXT\ParamConverter("user", options={"authenticatedUser" = true})
     *
     * @EXT\Template("ClarolineOfflineBundle:Offline:sync_url.html.twig")
     * @param User $user
     *
     * @return Response
     */
    public function editUrlAction(User $user)
    {
        $value = $this->yaml_parser->parse(file_get_contents(SyncConstant::PLAT_CONF));
        $results = array();
        foreach ($value as $elem) {
            if ($elem['username'] == $user->getUserName() && $elem['mail'] == $user->getMail()) {
                $results = $elem;
            }
        }

        return array(
            'value' => $results
        );
    }

    /**
     * @Route(
     *     "/url/edit",
     *     name="sync_url_edit"
     * )
     * @EXT\ParamConverter("user", options={"authenticatedUser" = true})
     *
     * @param User $user
     *
     * @return Response
     */
    public function editUrlYmlAction(User $user)
    {
        $request = $this->get('request');
        $user = $this->get('security.context')->getToken()->getUser();
        $new_url = $request->request->get('_url');
        $new_yaml = array();

        $value = $this->yaml_parser->parse(file_get_contents(SyncConstant::PLAT_CONF));

        foreach ($value as $elem) {
            if ($elem['username'] == $user->getUserName() && $elem['mail'] == $user->getMail()) {
                $elem['url'] = $new_url;
            }
            $new_yaml[] = $elem;
        }
        $yaml = $this->yaml_dump->dump($new_yaml);
        file_put_contents(SyncConstant::PLAT_CONF, $yaml);

        return $this->redirect($this->generateUrl('claro_desktop_open_tool', array('toolName' => "claroline_offline_tool")));

    }


    /*
    *   METHODE DE TEST : Those methods are used for the creation and loading tests.
    */
    /**
    *
    *   Test Creation
    *
    *   @EXT\Route(
    *       "/sync/seek_test",
    *       name="claro_sync_seek_test"
    *   )
    *
    * @EXT\ParamConverter("user", options={"authenticatedUser" = true})
     * @EXT\Template("ClarolineOfflineBundle:Offline:result.html.twig")
     * @param User $user
     *
     * @return Response
     */
    public function seekTestAction(User $user)
    {
        $results = array();
        $em = $this->getDoctrine()->getManager();
        $userSyncTab = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);
        $test = $this->get('claroline.manager.creation_manager')->createSyncZip($user, $userSyncTab[0]->getlastSynchronization()->getTimestamp());

        return array(
           'results' => $results
        );
    }

    /**
    *
    *   Test Creation
    *
    *   @EXT\Route(
    *       "/sync/load_test",
    *       name="claro_sync_load_test"
    *   )
    *
    * @EXT\ParamConverter("user", options={"authenticatedUser" = true})
     * @EXT\Template("ClarolineOfflineBundle:Offline:result.html.twig")
     * @param User $user
     *
     * @return Response
     */
    public function loadTestAction(User $user)
    {
        $results = array();
        $results = $this->get('claroline.manager.loading_manager')->loadZip('sync_DDD5A32E-92C6-4882-8CAC-E6D4F9D702F0.zip', $user);

        return array(
            'results' => $results['infoArray'],
            'msg' => ''
         );
    }

}
