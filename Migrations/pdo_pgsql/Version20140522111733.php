<?php

namespace Claroline\OfflineBundle\Migrations\pdo_pgsql;

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
            DROP CONSTRAINT FK_23C3CEFA76ED395
        ");
        $this->addSql("
            ALTER TABLE claro_user_sync
            ADD filename VARCHAR(255) DEFAULT NULL
        ");
        $this->addSql("
            ALTER TABLE claro_user_sync
            ADD status INT NOT NULL
        ");
        $this->addSql("
            ALTER TABLE claro_user_sync
            ADD CONSTRAINT FK_23C3CEFA76ED395 FOREIGN KEY (user_id)
            REFERENCES claro_user (id)
            ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        ");
    }

    public function down(Schema $schema)
    {
        $this->addSql("
            ALTER TABLE claro_user_sync
            DROP CONSTRAINT FK_23C3CEFA76ED395
        ");
        $this->addSql("
            ALTER TABLE claro_user_sync
            DROP filename
        ");
        $this->addSql("
            ALTER TABLE claro_user_sync
            DROP status
        ");
        $this->addSql("
            ALTER TABLE claro_user_sync
            ADD CONSTRAINT FK_23C3CEFA76ED395 FOREIGN KEY (user_id)
            REFERENCES claro_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        ");
    }
}
