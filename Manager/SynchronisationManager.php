<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\OfflineBundle\Manager;

use Claroline\CoreBundle\Persistence\ObjectManager;

/**
 * @DI\Service("claroline.manager.synchronisation_manager")
 */

class SynchronisationManager
{
    private $om;

    /**
     * Constructor.
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager")
     * })
     */
    public function __construct(
        ObjectManager $om
    )
    {
        $this->om = $om;
    }
}