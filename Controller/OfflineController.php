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

use JMS\DiExtraBundle\Annotation as DI;
use JMS\SecurityExtraBundle\Annotation as SEC;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Entity\Workspace\Workspace;
use Claroline\CoreBundle\Entity\ResourceNode;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\OfflineBundle\Model\SyncInfo;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Dumper;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as EXT;
use Claroline\OfflineBundle\Manager\CreationManager;
use Claroline\OfflineBundle\Manager\TransferManager;
use Claroline\OfflineBundle\Manager\Exception\AuthenticationException;
use Claroline\OfflineBundle\Manager\Exception\ProcessSyncException;
use Claroline\OfflineBundle\Manager\Exception\ServeurException;
use Claroline\OfflineBundle\Manager\Exception\PageNotFoundException;
use Claroline\OfflineBundle\Manager\Exception\SynchronisationFailsException;
use \Buzz\Exception\ClientException;

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
    private $plateformConf;
    private $creationManager;
    private $transferManager;

    /**
    * @DI\InjectParams({
    *   "router"          = @DI\Inject("router"),
    *   "request"         = @DI\Inject("request"),
    *   "om"              = @DI\Inject("claroline.persistence.object_manager"),
    *   "plateformConf"   = @DI\Inject("%claroline.synchronisation.offline_config%"),
    *   "creationManager" = @DI\Inject("claroline.manager.creation_manager"),
    *   "transferManager" = @DI\Inject("claroline.manager.transfer_manager")
    * })
    */
    public function __construct(
        UrlGeneratorInterface $router,
        Request $request,
        ObjectManager $om,
        $plateformConf,
        CreationManager $creationManager,
        TransferManager $transferManager
    )
    {
       $this->router = $router;
       $this->request = $request;
       $this->om = $om;
       $this->resourceNodeRepo = $om->getRepository('ClarolineCoreBundle:Resource\ResourceNode');
       $this->yaml_parser = new Parser();
       $this->yaml_dump = new Dumper();
       $this->plateformConf = $plateformConf;
       $this->creationManager = $creationManager;
       $this->transferManager = $transferManager;
    }

    /**
     * Get content by id
     *
     * @EXT\Route(
     *     "/",
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
        $first_sync = false;

        return array(
           'first_sync' => $first_sync,
		   'msg' => ''
        );

    }

    /**
     * Get result
     *
     * @EXT\Route(
     *     "/result",
     *     name="claro_sync_result",
     *     options={"expose"=true}
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
    *       "/param",
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
    *       "/testTrans",
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
    *       "/load",
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
    *       "/exchange",
    *       name="claro_sync_exchange",
    *       options = {"expose"=true}
    *
    *   )
    *
    * @EXT\ParamConverter("authUser", options={"authenticatedUser" = true})
    *
    * @param User $authUser
    * @return JsonResponse
    */
    public function syncAction(User $authUser)
    {  /**
        *   TODO MODIFY return with render different twig donc redirect plutot que le boolean true false
        */

        $em = $this->getDoctrine()->getManager();
        $userSync = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($authUser);
        $msg = '';
        try {
            $infoArray = $this->get('claroline.manager.synchronisation_manager')->synchroniseUser($authUser, $userSync[0]);
			// Show the result window
            return $this->render(
				'ClarolineOfflineBundle:Offline:result.html.twig',
				array(
					'results' => $infoArray,
					'msg' => ''
                )
            );

        } catch (AuthenticationException $e) {
            $msg = $this->get('translator')->trans('sync_config_fail', array(), 'offline');
        } catch (ProcessSyncException $e) {
            $msg = $this->get('translator')->trans('sync_server_fail', array(), 'offline');
        } catch (ServeurException $e) {
            $msg = $this->get('translator')->trans('sync_server_fail', array(), 'offline');
        } catch (PageNotFoundException $e) {
            $msg = $this->get('translator')->trans('sync_unreach', array(), 'offline');
        } catch (ClientException $e) {
            $msg = $this->get('translator')->trans('sync_client_fail', array(), 'offline');
        } catch (SynchronisationFailsException $e) {
            $msg = $this->get('translator')->trans('sync_fail', array('%message%' => $e->getMessage()), 'offline');
        }
        // $i = $this->get('claroline.manager.synchronisation_manager')->getDownloadStop("24F0DCDC-3B64-4019-8D6A-80FBCEA68AF9", $authUser);
        // echo "last download : ".$i."<br/>";

        //Format the view
        $username = $authUser->getFirstName() . ' ' . $authUser->getLastName();

        return new JsonResponse(
            array(
                'first_sync' => false,
                'msg' => $msg
            ),
            500
         );
    }

    /**
    *
    *   Seek and show all the modified courses and ressources
    *
    *   @EXT\Route(
    *       "/seek",
    *       name="claro_sync_seek"
    *   )
    *
    * @EXT\ParamConverter("user", options={"authenticatedUser" = true})
    *
    * @param User $user
    * @return JsonResponse
    */
    public function seekAction(User $user)
    {
        $em = $this->getDoctrine()->getManager();
        $userSyncTab = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);        
        $toUpload = $this->creationManager->createSyncZip($user, $userSyncTab[0]->getLastSynchronization()->getTimestamp());
        $metadatas = $this->transferManager->getMetadataArray($user, $toUpload);
        
        return new JsonResponse(array(
            'metadata' => $metadatas,
            'toUpload' => $toUpload));
    }

    /**
    *
    *  Transfer a file (sync archive) from a computer to another
    *
    *   @EXT\Route(
    *       "/transfer/{user}",
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
            $toTransfer = './hronize_down/3/_0252D476-FD7D-4E39-9285-A53EDEFCAC90.zip';
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
    *       "/getsync/{user}",
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
    *       "/getuser",
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
        $value = $this->yaml_parser->parse(file_get_contents($this->plateformConf));
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

        $value = $this->yaml_parser->parse(file_get_contents($this->plateformConf));

        foreach ($value as $elem) {
            if ($elem['username'] == $user->getUserName() && $elem['mail'] == $user->getMail()) {
                $elem['url'] = $new_url;
            }
            $new_yaml[] = $elem;
        }
        $yaml = $this->yaml_dump->dump($new_yaml);
        file_put_contents($this->plateformConf, $yaml);

        return $this->redirect($this->generateUrl('claro_desktop_open_tool', array('toolName' => "claroline_offline_tool")));

    }
	
    /**
     *  Add a new User to the sync_config file
     *
     * @Route(
     *     "/add_user",
     *     name="sync_add_user"
     * )
     * @EXT\ParamConverter("user", options={"authenticatedUser" = true})
     *
     * @EXT\Template("ClarolineOfflineBundle:Offline:new_user.html.twig")
     *
     * @return Response
     */
    public function addUserAction()
    {
        $cred = new Credential();
        $form = $this->formFactory->create(new OfflineFormType(), $cred);
        $msg = '';

        $form->handleRequest($this->request);
        if ($form->isValid()) {
            // Check if the user exists on the distant database
            $alr_user = $this->userRepo->findOneBy(array('username' => $cred->getName()));
            if ($alr_user == NULL) {
                try {
                    $this->transferManager->getUserInfo($cred->getName(), $cred->getPassword(), $cred->getUrl());
                    $this->authenticator->authenticate($cred->getName(), $cred->getPassword());

                    return $this->render(
                        'ClarolineOfflineBundle:Offline:connect_ok.html.twig',
                        array(
                            'first_sync' => true,
							'msg' => ''
                        ));
                } catch (Exception $e) {
                    $msg = $this->getMessage($e);
                }
            } else {
                $msg = $this->get('translator')->trans('sync_already', array(), 'offline');
            }
        }

        return array(
           'form' => $form->createView(),
           'msg' => $msg
        );
    }
	
	private function getMessage(Exception $e)
	{
		$msg = '';
		switch($e) {
			case AuthenticationException :
                $msg = $this->get('translator')->trans('sync_config_fail', array(), 'offline');
                break;
			case ProcessSyncException :
                $msg = $this->get('translator')->trans('sync_server_fail', array(), 'offline');
                break;
			case ServeurException :
                $msg = $this->get('translator')->trans('sync_server_fail', array(), 'offline');
                break;
			case PageNotFoundException :
                $msg = $this->get('translator')->trans('sync_unreach', array(), 'offline');
                break;
			case ClientException :
                $msg = $this->get('translator')->trans('sync_client_fail', array(), 'offline');
                break;			
		}
		
		return $msg;
	
	}
    /*
    *   METHODE DE TEST : Those methods are used for the creation and loading tests.
    */
    /**
    *
    *   Test Creation
    *
    *   @EXT\Route(
    *       "/seek_test",
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
           'results' => $results,
           'msg' => ''
        );
    }

    /**
    *
    *   Test Creation
    *
    *   @EXT\Route(
    *       "/load_test",
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
