<?php

namespace Claroline\OfflineBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as EXT;
use JMS\DiExtraBundle\Annotation as DI;
use JMS\SecurityExtraBundle\Annotation as SEC;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Library\Security\Authenticator;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Manager\UserManager;
use Claroline\OfflineBundle\Manager\TransferManager;
use Claroline\CoreBundle\Repository\UserRepository;
use Claroline\OfflineBundle\SyncConstant;
use Claroline\OfflineBundle\Entity\Credential;
use Claroline\OfflineBundle\Form\OfflineFormType;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\OfflineBundle\Manager\Exception\AuthenticationException;
use Claroline\OfflineBundle\Manager\Exception\ProcessSyncException;
use Claroline\OfflineBundle\Manager\Exception\ServeurException;
use Claroline\OfflineBundle\Manager\Exception\PageNotFoundException;
use \DateTime;
use \ZipArchive;
use \Buzz\Exception\ClientException;
use Symfony\Component\Form\FormFactory;


class SynchronisationController extends Controller
{    
    private $om;
    private $authenticator;
    private $request;
    private $userRepository;
    private $userManager;
    private $transferManager;
    private $router;
    private $session;
    private $formFactory;
    private $userRepo;
    private $resourceNodeRepo;
    
     /**
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "authenticator"  = @DI\Inject("claroline.authenticator"),
     *     "userManager"   = @DI\Inject("claroline.manager.user_manager"),
     *     "transferManager" = @DI\Inject("claroline.manager.transfer_manager"),
     *     "request"            = @DI\Inject("request"),
     *     "router"             = @DI\Inject("router"),
     *     "session"            = @DI\Inject("session"),
     *     "formFactory"            = @DI\Inject("form.factory")
     * })
     */
    public function __construct(
        ObjectManager $om,
        Authenticator $authenticator,       
        UserManager $userManager,
        TransferManager $transferManager,
        Request $request,
        UrlGeneratorInterface $router,
        SessionInterface $session,
        FormFactory $formFactory
    )
    {
        $this->om = $om;
        $this->authenticator = $authenticator;
        $this->userManager = $userManager;
        $this->transferManager = $transferManager;
        $this->request = $request;
        $this->userRepository = $om->getRepository('ClarolineCoreBundle:User');
        $this->router = $router;
        $this->session = $session;
        $this->formFactory = $formFactory;
        $this->userRepo = $om->getRepository('ClarolineCoreBundle:User');
        $this->resourceNodeRepo = $om->getRepository('ClarolineCoreBundle:Resource\ResourceNode');
    }
    // TODO Security voir workspace controller.

    private function getUserFromID($user)
    {
        
        $arrayRepo = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findById($user);
        return $arrayRepo[0];
    }
    
    private function authWithToken($content)
    {
        $informationsArray = (array)json_decode($content);
        $user = $this->userRepository->findOneBy(array('exchangeToken' => $informationsArray['token']));
        if($user == null){
            $status = 401;
        }else{
            $status = $this->authenticator->authenticateWithToken($user->getUsername(), $informationsArray['token']) ? 200 : 401;
        }
        
        return array(
            'user' => $user,
            'status' => $status,
            'informationsArray' => $informationsArray
        );
    }

    /**
    *   @EXT\Route(
    *       "/transfer/uploadzip",
    *       name="claro_sync_upload_zip",
    *   )
    *
    *   @EXT\Method("POST")    
    *
    *   @return Response
    */
    public function getUploadAction()
    {   /*
        *   A adapter ici. Au sein de la requete qui appelle on est maintenant sur du POST et non plus sur du GET
        *   la methode recevra avec la requete le zip de l'utilisateur offline
        *   Il faut donc commencer par recevoir le zip du offline
        *   Ensuite le traiter
        *   Generer le zip descendant et le retourner dans la stream reponse
        */
        $authTab = $this->authWithToken($this->getRequest()->getContent());
        // echo "CONTENT received : ".$content."<br/>";
        $status = $authTab['status'];
        $user = $authTab['user'];
        
        // echo "STATUS : ".$status."<br/>";
        $content = array();
        if ($status == 200){
            $content = $this->get('claroline.manager.transfer_manager')->processSyncRequest($authTab['informationsArray'], true);
            // echo "what s generate by process request? : ".json_encode($content).'<br/>';
            $status = $content['status'];
        }
        return new JsonResponse($content, $status);
        // return new JsonResponse($content, 200);
    }
    
    
    /**
    *   @EXT\Route(
    *       "/sync/unlink",
    *       name="claro_sync_unlink",
    *   )
    *
    *   @EXT\Method("POST")    
    *
    *   @return Response
    */
    public function unlinkSyncFile()
    {   /*
        *   This method is used to clean the files created in the synchronisation directory
        */
        $authTab = $this->authWithToken($this->getRequest()->getContent());
         echo "CONTENT received : ".$this->getRequest()->getContent()."<br/>";
        $status = $authTab['status'];
        $user = $authTab['user'];
        // echo "STATUS : ".$status."<br/>";
        $content = array();
        if ($status == 200){
            $content = $this->get('claroline.manager.transfer_manager')->unlinkSynchronisationFile($authTab['informationsArray'], $user);
            // echo "what s generate by process request? : ".json_encode($content).'<br/>';
            echo 'status return '.$status.'<br/>';
            $status = $content['status'];
        }
        return new JsonResponse($content, $status);
        // return new JsonResponse($content, 200);
    }
    
    /**
    *   @EXT\Route(
    *       "/transfer/getzip",
    *       name="claro_sync_get_zip",
    *   )
    *
    *   @EXT\Method("POST")    
    *
    *   @return Response
    */
    public function getZipAction()
    {
        $authTab = $this->authWithToken($this->getRequest()->getContent());
        // echo "CONTENT received : ".$content."<br/>";
        $status = $authTab['status'];
        $user = $authTab['user'];
        $informationsArray = $authTab['informationsArray'];
        // echo "Ask Packet Number : ".$informationsArray['packetNum'].'<br/>';
        // echo "STATUS : ".$status."<br/>";
        $content = array();
        if($status == 200){
            $fileName = SyncConstant::SYNCHRO_DOWN_DIR.$user->getId().'/sync_'.$informationsArray['hashname'].'.zip';
            $em = $this->getDoctrine()->getManager();
            $content = $this->get('claroline.manager.transfer_manager')->getMetadataArray($user, $fileName);
            $content['packetNum']=$informationsArray['packetNum'];
            $data = $this->get('claroline.manager.transfer_manager')->getPacket($informationsArray['packetNum'], $fileName, $user);
            if($data == null){
                $status = 424;
            }else{
                $content['file'] = base64_encode($data);
            }
        }
        return new JsonResponse($content, $status);
    }

    /**
    *   @EXT\Route(
    *       "/sync/user",
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
        // echo "receive content <br/>";
        $informationsArray = (array)json_decode($content);
        $status = $this->authenticator->authenticate($informationsArray['username'], $informationsArray['password']) ? 200 : 401;
        // echo "STATUS : ".$status."<br/>";
        $returnContent = array(); 

        if($status == 200){
            // Get User informations and return them
            $em = $this->getDoctrine()->getManager();
            $user = $em->getRepository('ClarolineCoreBundle:User')->loadUserByUsername($informationsArray['username']);
            //TODO ajout du token
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
    *       "/sync/lastUploaded",
    *       name="claro_sync_last_uploaded",
    *   )
    *
    *   @EXT\Method("POST")    
    *
    *   @return Response
    */
    public function getLastUploaded()
    {
        $authTab = $this->authWithToken($this->getRequest()->getContent());
        // echo "CONTENT received : ".$content."<br/>";
        $status = $authTab['status'];
        $informationsArray = $authTab['informationsArray'];
        $content = array();
        if($status == 200)
        {
            $filename = SyncConstant::SYNCHRO_UP_DIR.$informationsArray['id'].'/'.$informationsArray['hashname'];
            $em = $this->getDoctrine()->getManager();
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
    *       "/sync/numberOfPacketsToDownload",
    *       name="claro_sync_number_of_packets_to_download",
    *   )
    *
    *   @EXT\Method("POST")    
    *
    *   @return Response
    */
    public function getNumberOfPacketsToDownload()
    {
        $authTab = $this->authWithToken($this->getRequest()->getContent());
        // echo "CONTENT received : ".$content."<br/>";
        $status = $authTab['status'];
        $informationsArray = $authTab['informationsArray'];
        $content = array();
        if($status == 200)
        {
            $filename = SyncConstant::SYNCHRO_DOWN_DIR.$informationsArray['id'].'/sync_'.$informationsArray['hashname'].".zip";
            $nPackets = $this->get('claroline.manager.transfer_manager')->getNumberOfParts($filename);
            $content = array(
                'hashname' => $informationsArray['hashname'],
                'nPackets' => $nPackets
            );
        }
        return new JsonResponse($content, $status);
    }
          
    /**
    *   First Connection of the user
    *
    *   @EXT\Route(
    *       "/sync/config",
    *       name="claro_sync_config"
    *   )
    *
    * @EXT\Template("ClarolineOfflineBundle:Offline:config.html.twig")
    */
    public function firstConnectionAction()
    {
        $cred = new Credential();
        // $form = $this->createForm(new OfflineFormType(), $cred);
        $form = $this->formFactory->create(new OfflineFormType(), $cred);
        $msg = '';
        // $error = false;
        
        $form->handleRequest($this->request);
        if($form->isValid()) {
            /*
            *   Check if the user exists on the distant database
            */
            // $profil = $this->transferManager->getUserInfo($cred->getName(), $cred->getPassword());

            // if($profil){
                // $error = false;
                // $first_sync = true;
                //Auto-log?

                // TRUE route if auto-log.
                // $route = $this->router->generate('claro_sync');  

                // Route for test
                // $route = $this->router->generate('claro_sync_config_ok');
                
                // return new RedirectResponse($route);
            // }
            // else{
                // $msg = $this->get('translator')->trans('sync_config_fail', array(), 'offline');
                // $this->get('request')->getSession()->getFlashBag()->add('error', $msg);
            // }
            
            /*
            *   test if username + password already in DB
            */
            $alr_user = $this->userRepo->findOneBy(array('username' => $cred->getName()));
            if($alr_user == NULL){
                try{
                    $this->transferManager->getUserInfo($cred->getName(), $cred->getPassword());
                    // $error = false;
                    // $first_sync = true;
                    //Auto-log?
                    // echo 'badadoum';
                    // TRUE route if auto-log.
                    $this->authenticator->authenticate($cred->getName(), $cred->getPassword());
                    $first_sync = true;
                    return $this->render('ClarolineOfflineBundle:Offline:connect_ok.html.twig', array(
                    'first_sync' => $first_sync
                    ) );
                    // $route = $this->router->generate('claro_sync');  

                    // Route for test
                    // $route = $this->router->generate('claro_sync_config_ok');
                    
                    // return new RedirectResponse($route);              
                    // $msg = $this->get('translator')->trans('sync_ok', array(), 'offline');
                    
                    
                }
                catch(AuthenticationException $e){                
                    $msg = $this->get('translator')->trans('sync_config_fail', array(), 'offline');
                    // $this->get('request')->getSession()->getFlashBag()->add('error', $msg);
                }
                catch(ProcessSyncException $e){
                    $msg = $this->get('translator')->trans('sync_server_fail', array(), 'offline');
                    // $this->get('request')->getSession()->getFlashBag()->add('error', $msg);
                }
                catch(ServeurException $e){
                    $msg = $this->get('translator')->trans('sync_server_fail', array(), 'offline');
                    // $this->get('request')->getSession()->getFlashBag()->add('error', $msg);
                }
                catch(PageNotFoundException $e){
                    $msg = $this->get('translator')->trans('sync_unreach', array(), 'offline');
                    // $this->get('request')->getSession()->getFlashBag()->add('error', $msg);
                }
                catch(ClientException $e){
                    $msg = $this->get('translator')->trans('sync_client_fail', array(), 'offline');
                    // $this->get('request')->getSession()->getFlashBag()->add('error', $msg);
                }
            }
            else{
                $msg = $this->get('translator')->trans('sync_already', array(), 'offline');                   
            }
            
            // $this->get('request')->getSession()->getFlashBag()->add('error', $msg);
            // finally{
                // echo 'nananananananannaa BATMAN!';
                // $msg = $this->get('translator')->trans('sync_config_fail', array(), 'offline');
                // $this->get('request')->getSession()->getFlashBag()->add('error', $msg);
                // $route = $this->router->generate('claro_sync_config_ok');
                
                // return new RedirectResponse($route);
            // }

        }
        return array(
           'form' => $form->createView(),
           'msg' => $msg
        );
    }

    /**
    *   User found online.
    *
    *   @EXT\Route(
    *       "/sync/config/ok",
    *       name="claro_sync_config_ok"
    *   )
    *
    */
    public function firstConnectionOkAction()
    {
        $first_sync = true;
        return $this->render('ClarolineOfflineBundle:Offline:connect_ok.html.twig', array(
            'first_sync' => $first_sync
        ) );

        // echo 'It works!';
        // return array(
            // $first_sync = true
        // );
    }

    /**
    *   User doesn't exist online.
    *
    *   @EXT\Route(
    *       "/sync/config/nok",
    *       name="claro_sync_config_nok"
    *   )
    *
    * @EXT\Template("ClarolineOfflineBundle:Offline:config.html.twig")
    */
    public function firstConnectionNokAction()
    {
        echo '404 not found';
        return array(
        );
    }
    
    /**
    *   @EXT\Route(
    *       "/transfer/confirm",
    *       name="claro_confirm_sync",
    *   )
    *
    *   @EXT\Method("GET")    
    */
    public function confirmAction()
    {
    //DEPRECATED DO NOT USE
        /*$em = $this->getDoctrine()->getManager();
        $arrayRepo = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findById($user);
        $authUser = $arrayRepo[0];*/
        // $authUser = $this->getUserFromID($user);

        //TODO verifier authentification !!!  => SHOULD return false if fails
        $this->get('claroline.manager.user_sync_manager')->updateUserSynchronized($authUser);
        return true;
    }

    /**
    *  Transfert workspace list
    *   
    *   @EXT\Route(
    *       "/transfer/workspace/{user}",
    *       name="claro_sync_transfer"
    *   )
    *
    * @EXT\Method("GET")
    *
    * @return Response
    */
    public function workspaceAction($user)
    {
        // Deprecated, not used anymore
        //TODO Authentification User
        $authUser = $this->getUserFromID($user);
        $toSend = $this->get('claroline.manager.creation_manager')->writeWorspaceList($authUser);
        
        //Send back the online sync zip
        $response = new StreamedResponse();
        //SetCallBack voir Symfony/Bundle/Controller/Controller pour les parametres de set callback
        $response->setCallBack(
            function () use ($toSend) {                
                readfile($toSend);
            }
        );

        return $response;
    }

    //TODO Route pour supprimer les fichiers de synchro
}
