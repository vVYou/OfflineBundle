<?php

namespace Claroline\OfflineBundle\Migrations\drizzle_pdo_mysql;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated migration based on mapping information: modify it with caution
 *
 * Generation date: 2014/04/21 01:58:43
 */
class Version20140421135837 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql("
            CREATE TABLE claro_role_creation (
                id INT AUTO_INCREMENT NOT NULL,
                role_id INT DEFAULT NULL,
                creation_date DATETIME NOT NULL,
                PRIMARY KEY(id),
                INDEX IDX_5005B245D60322AC (role_id)
            )
        ");
        $this->addSql("
            ALTER TABLE claro_role_creation
            ADD CONSTRAINT FK_5005B245D60322AC FOREIGN KEY (role_id)
            REFERENCES claro_role (id)
        ");
    }

    public function down(Schema $schema)
    {
        $this->addSql("
            DROP TABLE claro_role_creation
        ");
    }
}
