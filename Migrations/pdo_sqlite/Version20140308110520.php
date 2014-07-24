<?php

namespace Claroline\OfflineBundle\Migrations\pdo_sqlite;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated migration based on mapping information: modify it with caution
 *
 * Generation date: 2014/03/08 11:05:29
 */
class Version20140308110520 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql("
            CREATE TABLE claro_user_sync (
                id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                last_synchronization DATETIME NOT NULL,
                PRIMARY KEY(id)
            )
        ");
        $this->addSql("
            CREATE UNIQUE INDEX UNIQ_23C3CEFA76ED395 ON claro_user_sync (user_id)
        ");
    }

    public function down(Schema $schema)
    {
        $this->addSql("
            DROP TABLE claro_user_sync
        ");
    }
}
