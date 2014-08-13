<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\OfflineBundle\Entity;

use Symfony\Bridge\Doctrine\Validator\Constraints as DoctrineAssert;
use Doctrine\ORM\Mapping as ORM;
//use Doctrine\Common\Collections\ArrayCollection;
use Claroline\CoreBundle\Entity\User;
use Gedmo\Mapping\Annotation as Gedmo;
use \DateTime;

/**
 * @ORM\Entity(repositoryClass="Claroline\OfflineBundle\Repository\UserSynchronizedRepository")
 * @ORM\Table(name="claro_user_sync")
 * @DoctrineAssert\UniqueEntity("user")
 */
class UserSynchronized
{
    const SUCCESS_SYNC = 0;
    const STARTED_UPLOAD = 1;
    const FAIL_UPLOAD = 2;
    const SUCCESS_UPLOAD = 3;
    const FAIL_DOWNLOAD = 4;
    const SUCCESS_DOWNLOAD = 5;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     *
     * @ORM\OneToOne(
        targetEntity="Claroline\CoreBundle\Entity\User",
        cascade={"persist"}
       )
     * @ORM\JoinColumn(nullable=false, unique=true, onDelete="CASCADE")
     */
    protected $user;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="last_synchronization", type="datetime")
     * @Gedmo\Timestampable(on="create")
     */
    protected $lastSynchronization;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="sent_time", type="datetime")
     * @Gedmo\Timestampable(on="create")
     */
    protected $sentTime;

    /**
     * @var string
     *
     * @ORM\Column(name="filename", nullable=true, type="string")
     */
    protected $filename;

    /**
     * @var integer
     *
     * @ORM\Column(name="status", type="integer")
     */
    protected $status;

    /**
     * @var boolean
     *
     * @ORM\Column(name="admin", type="boolean")
     */
    protected $admin;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->lastSynchronization = new DateTime('@0');
        $this->sentTime = new DateTime('@0');
        $this->status = UserSynchronized::SUCCESS_SYNC;
        $this->admin = $user->isAdmin();
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @return \DateTime
     */
    public function getLastSynchronization()
    {
        return $this->lastSynchronization;
    }

    /**
     *
     * @param \DateTime $date
     */
    public function setLastSynchronization(\DateTime $date)
    {
        $this->lastSynchronization = $date;
    }

    /**
     * @return \DateTime
     */
    public function getSentTime()
    {
        return $this->sentTime;
    }

    /**
     *
     * @param \DateTime $date
     */
    public function setSentTime(\DateTime $date)
    {
        $this->sentTime = $date;
    }

    public function getFilename()
    {
        return $this->filename;
    }

    public function setFilename($filename)
    {
        $this->filename = $filename;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function setAdmin($admin)
    {
        $this->admin = $admin;
    }

    public function isAdmin()
    {
        return $this->admin;
    }
}
