<?php

namespace Claroline\OfflineBundle\Migrations\ibm_db2;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated migration based on mapping information: modify it with caution
 *
 * Generation date: 2014/05/22 11:17:43
 */
class Version20140522111733 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql("
            ALTER TABLE claro_user_sync 
            ADD COLUMN filename VARCHAR(255) DEFAULT NULL 
            ADD COLUMN status INTEGER NOT NULL
        ");
        $this->addSql("
            ALTER TABLE claro_user_sync 
            DROP FOREIGN KEY FK_23C3CEFA76ED395
        ");
        $this->addSql("
            ALTER TABLE claro_user_sync 
            ADD CONSTRAINT FK_23C3CEFA76ED395 FOREIGN KEY (user_id) 
            REFERENCES claro_user (id) 
            ON DELETE CASCADE
        ");
    }

    public function down(Schema $schema)
    {
        $this->addSql("
            ALTER TABLE claro_user_sync 
            DROP COLUMN filename 
            DROP COLUMN status
        ");
        $this->addSql("
            ALTER TABLE claro_user_sync 
            DROP FOREIGN KEY FK_23C3CEFA76ED395
        ");
        $this->addSql("
            ALTER TABLE claro_user_sync 
            ADD CONSTRAINT FK_23C3CEFA76ED395 FOREIGN KEY (user_id) 
            REFERENCES claro_user (id)
        ");
    }
}