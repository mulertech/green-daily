<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260511150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'L7 — recipe_suggestion.recipe_data (structured + computed apports)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipe_suggestion ADD COLUMN recipe_data JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipe_suggestion DROP COLUMN recipe_data');
    }
}
