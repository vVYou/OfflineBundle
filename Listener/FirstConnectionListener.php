<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\OfflineBundle\Listener;

use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Event\StrictDispatcher;
use Claroline\CoreBundle\Library\Configuration\PlatformConfigurationHandler;
use Claroline\CoreBundle\Manager\TermsOfServiceManager;
use Claroline\CoreBundle\Persistence\ObjectManager;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Claroline\OfflineBundle\Model\SyncConstant;
use Claroline\OfflineBundle\Entity\Credential;
use Claroline\OfflineBundle\Form\OfflineFormType;

/**
 * @DI\Service("claroline.first_connection_handler")
 */
class FirstConnectionListener
{
    private $securityContext;
    private $eventDispatcher;
    private $configurationHandler;
    private $templating;
    private $formFactory;
    private $termsOfService;
    private $manager;
    private $router;

    /**
     * @DI\InjectParams({
     *     "securityContext"                = @DI\Inject("security.context"),
     *     "eventDispatcher"        = @DI\Inject("claroline.event.event_dispatcher"),
     *     "configurationHandler"   = @DI\Inject("claroline.config.platform_config_handler"),
     *     "templating"             = @DI\Inject("templating"),
     *     "formFactory"            = @DI\Inject("form.factory"),
     *     "termsOfService"         = @DI\Inject("claroline.common.terms_of_service_manager"),
     *     "manager"                = @DI\Inject("claroline.persistence.object_manager"),
     *     "router"                 = @DI\Inject("router")
     * })
     *
     */
    public function __construct(
        SecurityContextInterface $securityContext,
        StrictDispatcher $eventDispatcher,
        PlatformConfigurationHandler $configurationHandler,
        EngineInterface $templating,
        FormFactory $formFactory,
        TermsOfServiceManager $termsOfService,
        ObjectManager $manager,
        Router $router
    )
    {
        $this->securityContext = $securityContext;
        $this->eventDispatcher = $eventDispatcher;
        $this->configurationHandler = $configurationHandler;
        $this->templating = $templating;
        $this->formFactory = $formFactory;
        $this->termsOfService = $termsOfService;
        $this->manager = $manager;
        $this->router = $router;
    }

    /**
     * @DI\Observe("kernel.request")
     *
     * @param GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event)
    {

        $event = $this->isFlagOk($event);
    }

    private function isFlagOk(GetResponseEvent $event)
    {
        /*
        *   If the user is not connected and the first connection has not be done yet.
        *   He will be redirect to the first connection page.
        */
        $first_route = 'claro_sync_config';
        $_route = $event->getRequest()->get('_route');
        $token = $this->securityContext->getToken();
        $url = $event->getRequest()->getHttpHost();

        if(strpos($url, 'localhost')){
            $uri = $this->router->generate($first_route);
            $response = new RedirectResponse($uri);
            $event->setResponse(new Response($response));
        }
        
        // if ($event->isMasterRequest()) {
            // if ($first_route !== $_route) {
                // if ($token && $token->getUser() == 'anon.') {
                    // if (!(file_exists(SyncConstant::PLAT_CONF))) {
                        // $uri = $this->router->generate($first_route);
                        // $response = new RedirectResponse($uri);
                        // $event->setResponse(new Response($response));
                    // }
                // }
            // }
        // }

        return $event;

        //VERSION 2.0
        // if ($this->securityContext->getToken()->getUser() == 'anon.') {

            // if (!(file_exists(SyncConstant::PLAT_CONF))) {

                // $uri = $this->router->generate('claro_sync_config');
                // $response = new RedirectResponse($uri);
                // $event->setResponse(new Response($response));

            // }
        // }

        // return $event;

        // VERSION DE BASE
        // if (!(file_exists('../app/config/test_first.txt'))) {
            // file_put_contents('../app/config/test_first.txt', 'monuser');
            // $uri = $this->router->generate('claro_sync_config');
            // $response = new RedirectResponse($uri);
            // $event->setResponse(new Response($response));
        // }

        //VERSION 1.0
        // if (!(file_exists('../app/config/test_first.txt'))) {
            // file_put_contents('../app/config/test_first.txt', 'monuser');
            // $cred = new Credential();
            // $form = $this->formFactory->create(new OfflineFormType(), $cred);
            // $msg = '';

            // $response = $this->templating->render(
                // "ClarolineOfflineBundle:Offline:config.html.twig",
                // array(
            // 'form' => $form->createView(),
            // 'msg' => $msg
            // )
            // );

            // $event->setResponse(new Response($response));
        // }
        return $event;
    }
}
