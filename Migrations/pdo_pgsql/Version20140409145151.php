<?php

namespace Claroline\OfflineBundle\Migrations\pdo_pgsql;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated migration based on mapping information: modify it with caution
 *
 * Generation date: 2014/04/09 02:51:59
 */
class Version20140409145151 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql("
            ALTER TABLE claro_user_sync 
            ADD sent_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
        ");
    }

    public function down(Schema $schema)
    {
        $this->addSql("
            ALTER TABLE claro_user_sync 
            DROP sent_time
        ");
    }
}