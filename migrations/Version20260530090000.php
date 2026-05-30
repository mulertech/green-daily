<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du complément Onatera "Essentiels Vegan" (Orfito) — 1 comprimé';
    }

    public function up(Schema $schema): void
    {
        // Complément alimentaire vegan Orfito (réf. Onatera).
        // Convention d'unité : on encode « 1 comprimé = quantity_grams 1 ».
        // L'apport étant calculé en amount_per_100g * quantity_grams / 100,
        // on stocke donc (valeur par comprimé) * 100 dans amount_per_100g.
        //
        // Composition par comprimé (source : page produit Onatera) :
        //   Vitamine B9 (folate) 400 µg, Iode 150 µg, Sélénium 60 µg,
        //   Vitamine B12 30 µg, Vitamine D3 20 µg, Vitamine B2 3,2 mg, Vitamine B6 2,3 mg.
        // Seuls les nutriments présents dans App\Enum\NutrientCode sont enregistrés
        // (B12, IODE, SE, VITD) ; B9/folate, B2 et B6 ne sont pas suivis par l'app.
        $this->addSql(<<<'SQL'
            INSERT INTO food (alim_code, name_fr, group_name, sub_group_name)
            VALUES ('ONATERA_EV', 'Onatera essentiels vegan (1 comprimé)', 'Compléments alimentaires', 'Multivitamines vegan')
            SQL);

        // amount_per_100g = (µg par comprimé) * 100
        $this->addSql(<<<'SQL'
            INSERT INTO food_nutrient (food_id, nutrient_code, amount_per_100g)
            SELECT f.id, v.nutrient_code, v.amount_per_100g
            FROM food f
            CROSS JOIN (VALUES
                ('B12', 3000),
                ('IODE', 15000),
                ('SE', 6000),
                ('VITD', 2000)
            ) AS v(nutrient_code, amount_per_100g)
            WHERE f.alim_code = 'ONATERA_EV'
            SQL);
    }

    public function down(Schema $schema): void
    {
        // food_nutrient supprimé en cascade (FK ON DELETE CASCADE).
        $this->addSql(<<<'SQL'
            DELETE FROM food WHERE alim_code = 'ONATERA_EV'
            SQL);
    }
}
