<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Failing migration used to test docker-prod deployment rollback';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SELECT 1 FROM essai_table_absente');
    }

    public function down(Schema $schema): void
    {
    }
}
