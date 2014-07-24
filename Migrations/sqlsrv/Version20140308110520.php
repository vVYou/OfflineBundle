<?php

namespace Claroline\OfflineBundle\Migrations\sqlsrv;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated migration based on mapping information: modify it with caution
 *
 * Generation date: 2014/03/08 11:05:31
 */
class Version20140308110520 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql("
            CREATE TABLE claro_user_sync (
                id INT IDENTITY NOT NULL,
                user_id INT NOT NULL,
                last_synchronization DATETIME2(6) NOT NULL,
                PRIMARY KEY (id)
            )
        ");
        $this->addSql("
            CREATE UNIQUE INDEX UNIQ_23C3CEFA76ED395 ON claro_user_sync (user_id)
            WHERE user_id IS NOT NULL
        ");
        $this->addSql("
            ALTER TABLE claro_user_sync
            ADD CONSTRAINT FK_23C3CEFA76ED395 FOREIGN KEY (user_id)
            REFERENCES claro_user (id)
        ");
    }

    public function down(Schema $schema)
    {
        $this->addSql("
            DROP TABLE claro_user_sync
        ");
    }
}
