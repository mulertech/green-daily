<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Essai docker-prod : migration lente, pour interrompre la bascule';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SELECT pg_sleep(30)');
    }

    public function down(Schema $schema): void
    {
    }
}
