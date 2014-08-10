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

use Claroline\CoreBundle\Event\StrictDispatcher;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\SecurityContextInterface;
// use Claroline\OfflineBundle\Model\SyncConstant;

/**
 * @DI\Service("claroline.first_connection_handler")
 */
class FirstConnectionListener
{
    private $securityContext;
    private $eventDispatcher;
    private $router;
    private $plateformConf;

    /**
     * @DI\InjectParams({
     *     "securityContext" = @DI\Inject("security.context"),
     *     "eventDispatcher" = @DI\Inject("claroline.event.event_dispatcher"),
     *     "router"          = @DI\Inject("router"),
     *     "plateformConf"   = @DI\Inject("%claroline.synchronisation.offline_config%")
     * })
     *
     */
    public function __construct(
        SecurityContextInterface $securityContext,
        StrictDispatcher $eventDispatcher,
        Router $router,
        $plateformConf
    )
    {
        $this->securityContext = $securityContext;
        $this->eventDispatcher = $eventDispatcher;
        $this->router = $router;
        $this->plateformConf = $plateformConf;
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

    /**
     * If the user is not connected and the first connection has not be done yet.
     * He will be redirect to the first connection page.
     *
     * @param GetResponseEvent $event
     */
    private function isFlagOk(GetResponseEvent $event)
    {
        $first_route = 'claro_sync_config';
        $_route = $event->getRequest()->get('_route');
        $token = $this->securityContext->getToken();
        $url = $event->getRequest()->getUri();

        if ((strpos($url, 'localhost') == false) && (strpos($url, '::1') == false) && (strpos($url, '127.0.0.1') == false)) {
            // If online.
            return $event;
        }

        if ($event->isMasterRequest()) {
            if ($first_route !== $_route) {
                if ($token && $token->getUser() == 'anon.') {
                    if (!(file_exists($this->plateformConf))) {
                        $uri = $this->router->generate($first_route);
                        $response = new RedirectResponse($uri);
                        $event->setResponse(new Response($response));
                    }
                }
            }
        }

        return $event;
    }
}
