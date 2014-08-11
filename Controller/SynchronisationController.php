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

use \Exception;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as EXT;
use JMS\DiExtraBundle\Annotation as DI;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Library\Security\Authenticator;
use Claroline\CoreBundle\Manager\UserManager;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\OfflineBundle\Entity\Credential;
use Claroline\OfflineBundle\Form\OfflineFormType;
use Claroline\OfflineBundle\Manager\Exception\AuthenticationException;
use Claroline\OfflineBundle\Manager\Exception\ProcessSyncException;
use Claroline\OfflineBundle\Manager\Exception\ServeurException;
use Claroline\OfflineBundle\Manager\Exception\PageNotFoundException;
use Claroline\OfflineBundle\Manager\Exception\SynchronisationFailsException;
use Claroline\OfflineBundle\Manager\TransferManager;
use Claroline\OfflineBundle\Model\SyncConstant;
use Claroline\OfflineBundle\Model\Security\OfflineAuthenticator;
use \Buzz\Exception\ClientException;

class SynchronisationController extends Controller
{
    private $om;
    private $authenticator;
    private $offlineAuthenticator;
    private $request;
    private $userManager;
    private $transferManager;
    private $router;
    private $session;
    private $formFactory;
    private $userRepo;
    private $resourceNodeRepo;
    private $syncUpDir;
    private $syncDownDir;

     /**
     * @DI\InjectParams({
     *     "om"                   = @DI\Inject("claroline.persistence.object_manager"),
     *     "authenticator"        = @DI\Inject("claroline.authenticator"),
     *     "offlineAuthenticator" = @DI\Inject("claroline.offline_authenticator"),
     *     "userManager"          = @DI\Inject("claroline.manager.user_manager"),
     *     "transferManager"      = @DI\Inject("claroline.manager.transfer_manager"),
     *     "request"              = @DI\Inject("request"),
     *     "router"               = @DI\Inject("router"),
     *     "session"              = @DI\Inject("session"),
     *     "formFactory"          = @DI\Inject("form.factory"),
     *     "syncUpDir"            = @DI\Inject("%claroline.synchronisation.up_directory%"),
     *     "syncDownDir"          = @DI\Inject("%claroline.synchronisation.down_directory%")
     * })
     */
    public function __construct(
        ObjectManager $om,
        Authenticator $authenticator,
        OfflineAuthenticator $offlineAuthenticator,
        UserManager $userManager,
        TransferManager $transferManager,
        Request $request,
        UrlGeneratorInterface $router,
        SessionInterface $session,
        FormFactory $formFactory,
        $syncUpDir,
        $syncDownDir
    )
    {
        $this->om = $om;
        $this->authenticator = $authenticator;
        $this->offlineAuthenticator = $offlineAuthenticator;
        $this->userManager = $userManager;
        $this->transferManager = $transferManager;
        $this->request = $request;
        $this->router = $router;
        $this->session = $session;
        $this->formFactory = $formFactory;
        $this->userRepo = $om->getRepository('ClarolineCoreBundle:User');
        $this->resourceNodeRepo = $om->getRepository('ClarolineCoreBundle:Resource\ResourceNode');
        $this->syncUpDir = $syncUpDir;
        $this->syncDownDir = $syncDownDir;
    }

    private function getUserFromID($user)
    {
        $arrayRepo = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findById($user);

        return $arrayRepo[0];
    }

    private function authWithToken($content)
    {
        $informationsArray = (array) json_decode($content);
        $user = $this->userRepo->findOneBy(array('exchangeToken' => $informationsArray['token']));
        if ($user == null) {
            $status = 401;
        } else {
            $status = $this->offlineAuthenticator->authenticateWithToken($user->getUsername(), $informationsArray['token']) ? 200 : 401;
        }

        return array(
            'user' => $user,
            'status' => $status,
            'informationsArray' => $informationsArray
        );
    }

    /**
    *   This action take care of the upload of an archive
    *   It saves it in its folders after authenticate the user that make the request
    *   If errors, returns HTTP error code
    *
    *   @EXT\Route(
    *       "/uploadArchive",
    *       name="claro_sync_upload_zip",
    *   )
    *
    *   @EXT\Method("POST")
    *
    *   @return Response
    */
    public function getUploadAction()
    {
        //Authenticate the user
        $authTab = $this->authWithToken($this->getRequest()->getContent());
        $status = $authTab['status'];
        $user = $authTab['user'];
        $content = array();
        if ($status == 200) {
            //Process the request
            $content = $this->transferManager->processSyncRequest($authTab['informationsArray'], true);
            $status = $content['status'];
        }
        return new JsonResponse($content, $status);
    }

    /**
    *   This action handle the clean up of the directory after synchronization
    *
    *   @EXT\Route(
    *       "/unlink",
    *       name="claro_sync_unlink",
    *   )
    *
    *   @EXT\Method("POST")
    *
    *   @return Response
    */
    public function unlinkSyncFile()
    {
        //Authenticate the user
        $authTab = $this->authWithToken($this->getRequest()->getContent());
        $status = $authTab['status'];
        $user = $authTab['user'];
        $content = array();
        if ($status == 200) {
            $content = $this->transferManager->unlinkSynchronisationFile($authTab['informationsArray'], $user);
            $status = $content['status'];
        }
        return new JsonResponse($content, $status);
    }

    /**
    *   This action returns a fragment of a synchronization archive
    *   If error happened error code of HTTP request is used
    *
    *   @EXT\Route(
    *       "/getzip",
    *       name="claro_sync_get_zip",
    *   )
    *
    *   @EXT\Method("POST")
    *
    *   @return Response
    */
    public function getZipAction()
    {
        //Authenticate the user
        $authTab = $this->authWithToken($this->getRequest()->getContent());
        $status = $authTab['status'];
        $user = $authTab['user'];
        $informationsArray = $authTab['informationsArray'];
        $content = array();
        if ($status == 200) {
            $fileName =$this->syncDownDir.$user->getId().'/sync_'.$informationsArray['hashname'].'.zip';
            if(file_exists($fileName)){
                $content = $this->transferManager->getMetadataArray($user, $fileName);
                $content['fragmentNumber']=$informationsArray['fragmentNumber'];
                $data = $this->transferManager->getFragment($informationsArray['fragmentNumber'], $fileName, $user);
                if ($data == null) {
                    $status = 424;
                } else {
                    $content['file'] = base64_encode($data);
                }
            }else{
                $status = 480;
            }
        }
        return new JsonResponse($content, $status);
    }

    /**
    *   This action returns basics informations about a specific user
    *
    *   @EXT\Route(
    *       "/user",
    *       name="claro_sync_user",
    *   )
    *
    *   @EXT\Method("POST")
    *
    *   @return Response
    */
    public function getUserIformations()
    {
        $content = $this->getRequest()->getContent();
        $informationsArray = (array) json_decode($content);
        //Authenticate the user
        $status = $this->authenticator->authenticate($informationsArray['username'], $informationsArray['password']) ? 200 : 401;
        $returnContent = array();
        if ($status == 200) {
            // Get User informations and return them
            $user = $this->userRepo->loadUserByUsername($informationsArray['username']);
            $user_ws_rn = $this->resourceNodeRepo->findOneBy(array('workspace' => $user->getPersonalWorkspace(), 'parent' => NULL));
            $user_inf = $user->getUserAsTab();
            $user_inf['ws_resnode'] = $user_ws_rn->getNodeHashName();
            $returnContent = $user_inf;
        }

        return new JsonResponse($returnContent, $status);
    }

   /**
    *   This function allows the requester to know what was the last part uploaded on the (online) plateform.
    *   If authentication pass, it returns an array containing the hashname of the file and the last packet upload
    *   If authentication fails, it returns an HTTP 401 error
    *
    *   @EXT\Route(
    *       "/lastUploaded",
    *       name="claro_sync_last_uploaded",
    *   )
    *
    *   @EXT\Method("POST")
    *
    *   @return Response
    */
    public function getLastUploaded()
    {
        //Authenticate the user
        $authTab = $this->authWithToken($this->getRequest()->getContent());
        $status = $authTab['status'];
        $user = $authTab['user'];
        $informationsArray = $authTab['informationsArray'];
        $content = array();
        if ($status == 200) {
            $filename =$this->syncUpDir.$user->getId().'/'.$informationsArray['hashname'];
            $lastUp = $this->get('claroline.manager.synchronisation_manager')->getDownloadStop($filename,  $authTab['user']);
            $content = array(
                'hashname' => $informationsArray['hashname'],
                'lastUpload' => $lastUp
            );
        }

        return new JsonResponse($content, $status);
    }

   /**
    *
    *   This method allows the requester to know the number of packets that he has to download to get the pending synchronized archive
    *   If authentication pass, it receives an array with the hashname of the file and the number of packet to download
    *   If authentication fails, it return an HTTP 401 error
    *
    *   @EXT\Route(
    *       "/numberOfPacketsToDownload",
    *       name="claro_sync_number_of_packets_to_download",
    *   )
    *
    *   @EXT\Method("POST")
    *
    *   @return Response
    */
    public function getNumberOfPacketsToDownload()
    {
        //Authenticate the user
        $authTab = $this->authWithToken($this->getRequest()->getContent());
        $status = $authTab['status'];
        $user = $authTab['user'];
        $informationsArray = $authTab['informationsArray'];
        $content = array();
        if ($status == 200) {
            $filename = $this->syncDownDir.$user->getId().'/sync_'.$informationsArray['hashname'].".zip";
            $nFragments = $this->transferManager->getTotalFragments($filename);
            $content = array(
                'hashname' => $informationsArray['hashname'],
                'totalFragments' => $nFragments
            );
        }

        return new JsonResponse($content, $status);
    }

    /**
    *   This action is used to retrieve the profil of the user
    *
    *   @EXT\Route(
    *       "/config",
    *       name="claro_sync_config"
    *   )
    *
    * @EXT\Template("ClarolineOfflineBundle:Offline:config.html.twig")
    */
    public function firstConnectionAction()
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
	
	private function getMessage($e)
	{
		$msg = '';
        var_dump(get_class($e));
		switch(get_class($e)) {
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
	
}
