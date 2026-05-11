<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260511160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'L8 — recipe_suggestion.meal_type';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipe_suggestion ADD COLUMN meal_type VARCHAR(16) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipe_suggestion DROP COLUMN meal_type');
    }
}
