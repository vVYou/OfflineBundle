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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Routing\Router;

/**
 * @DI\Service("claroline.first_connection_handler")
 */

class FirstConnectionListener
{   
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
        if ($event->isMasterRequest() and
            $user = $this->getUser($event->getRequest()) and
            !$user->hasAcceptedTerms() and
            !$this->isImpersonated()and
            $content = $this->termsOfService->getTermsOfService(false)
        ) {
            if ($termsOfService = $event->getRequest()->get('accept_terms_of_service_form') and
                isset($termsOfService['terms_of_service'])
            ) {
                $user->setAcceptedTerms(true);
                $this->manager->persist($user);
                $this->manager->flush();
            } else {
                $form = $this->formFactory->create(new TermsOfServiceType(), $content);
                $response = $this->templating->render(
                    "ClarolineCoreBundle:Authentication:termsOfService.html.twig",
                    array('form' => $form->createView())
                );

                $event->setResponse(new Response($response));
            }
        }

        return $event;
    }
}
