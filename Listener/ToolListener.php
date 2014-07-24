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

use Claroline\CoreBundle\Event\DisplayToolEvent;
use Symfony\Bundle\TwigBundle\TwigEngine;
use JMS\DiExtraBundle\Annotation as DI;

/**
 * @DI\Service
 */
class ToolListener
{
    private $templating;

    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "templating"         = @DI\Inject("templating")
     * })
     */
    public function __construct(
        TwigEngine $templating
    )
    {
        $this->templating = $templating;

    }

    /**
     * @DI\Observe("open_tool_desktop_claroline_offline_tool")
     *
     * @param DisplayToolEvent $event
     */
    public function onDesktopOpen(DisplayToolEvent $event)
    {
        $content = $this->templating->render(
            "ClarolineOfflineBundle:Offline:connect_ok.html.twig",
            array(
                'first_sync' => false
            )
        );
        $event->setContent($content);
        $event->stopPropagation();
    }

}
