<?php

namespace Claroline\OfflineBundle\Migrations\pdo_sqlite;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated migration based on mapping information: modify it with caution
 *
 * Generation date: 2014/08/09 09:30:43
 */
class Version20140809092959 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql("
            ALTER TABLE claro_user_sync 
            ADD COLUMN admin BOOLEAN NOT NULL
        ");
    }

    public function down(Schema $schema)
    {
        $this->addSql("
            DROP INDEX UNIQ_23C3CEFA76ED395
        ");
        $this->addSql("
            CREATE TEMPORARY TABLE __temp__claro_user_sync AS 
            SELECT id, 
            user_id, 
            last_synchronization, 
            sent_time, 
            filename, 
            status 
            FROM claro_user_sync
        ");
        $this->addSql("
            DROP TABLE claro_user_sync
        ");
        $this->addSql("
            CREATE TABLE claro_user_sync (
                id INTEGER NOT NULL, 
                user_id INTEGER NOT NULL, 
                last_synchronization DATETIME NOT NULL, 
                sent_time DATETIME NOT NULL, 
                filename VARCHAR(255) DEFAULT NULL, 
                status INTEGER NOT NULL, 
                PRIMARY KEY(id), 
                CONSTRAINT FK_23C3CEFA76ED395 FOREIGN KEY (user_id) 
                REFERENCES claro_user (id) 
                ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        ");
        $this->addSql("
            INSERT INTO claro_user_sync (
                id, user_id, last_synchronization, 
                sent_time, filename, status
            ) 
            SELECT id, 
            user_id, 
            last_synchronization, 
            sent_time, 
            filename, 
            status 
            FROM __temp__claro_user_sync
        ");
        $this->addSql("
            DROP TABLE __temp__claro_user_sync
        ");
        $this->addSql("
            CREATE UNIQUE INDEX UNIQ_23C3CEFA76ED395 ON claro_user_sync (user_id)
        ");
    }
}