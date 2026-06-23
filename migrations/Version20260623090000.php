<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du complément Arko Pharma "Azinc vitalité" — 2 gélules';
    }

    public function up(Schema $schema): void
    {
        // Complément multivitamines Arko Pharma (réf. Azinc vitalité).
        // Convention d'unité : on encode « 1 prise = 2 gélules = quantity_grams 1 ».
        // L'apport étant calculé en amount_per_100g * quantity_grams / 100,
        // on stocke donc (valeur par prise de 2 gélules) * 100 dans amount_per_100g.
        //
        // Composition pour 2 gélules (source : étiquette produit) :
        //   Bêta-carotène 4,8 mg (équiv. Vitamine A 800 µg RE), Vitamine B1 1,4 mg,
        //   B2 1,6 mg, B3 18 mg, B5 6 mg, B6 2 mg, B8 150 µg, B9 200 µg, B12 1 µg,
        //   Vitamine C 120 mg, Vitamine D3 5 µg, Vitamine E 10 mg, Calcium 120 mg,
        //   Chrome 25 µg, Cuivre 1,5 mg, Fer 8 mg, Manganèse 3,5 mg, Molybdène 80 µg,
        //   Sélénium 50 µg, Zinc 15 mg.
        // Seuls les nutriments présents dans App\Enum\NutrientCode sont enregistrés :
        //   VITA (équiv. rétinol 800 µg), VITD (5 µg), CA (120 mg), FE (8 mg),
        //   SE (50 µg), ZN (15 mg), B12 (1 µg).
        // Les autres apports (vitamines B1/B2/B3/B5/B6/B8/B9, C, E, chrome,
        // cuivre, manganèse, molybdène) ne sont pas suivis par l'app.
        $this->addSql(<<<'SQL'
            INSERT INTO food (alim_code, name_fr, group_name, sub_group_name)
            VALUES ('AZINC_VITALITE', 'Arko Pharma Azinc vitalité (2 gélules)', 'Compléments alimentaires', 'Multivitamines')
            SQL);

        // amount_per_100g = (valeur par prise de 2 gélules, dans l'unité du nutriment) * 100
        $this->addSql(<<<'SQL'
            INSERT INTO food_nutrient (food_id, nutrient_code, amount_per_100g)
            SELECT f.id, v.nutrient_code, v.amount_per_100g
            FROM food f
            CROSS JOIN (VALUES
                ('VITA', 80000),
                ('VITD', 500),
                ('CA', 12000),
                ('FE', 800),
                ('SE', 5000),
                ('ZN', 1500),
                ('B12', 100)
            ) AS v(nutrient_code, amount_per_100g)
            WHERE f.alim_code = 'AZINC_VITALITE'
            SQL);
    }

    public function down(Schema $schema): void
    {
        // food_nutrient supprimé en cascade (FK ON DELETE CASCADE).
        $this->addSql(<<<'SQL'
            DELETE FROM food WHERE alim_code = 'AZINC_VITALITE'
            SQL);
    }
}
