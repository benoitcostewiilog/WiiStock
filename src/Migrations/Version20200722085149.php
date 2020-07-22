<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200722085149 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this
            ->addSql('ALTER TABLE reference_article ADD free_fields JSON DEFAULT NULL;');

        $refArticleCategoryTypeLabel = CategoryType::ARTICLE;
        $refArticleCategoryCLabel = CategorieCL::REFERENCE_ARTICLE;

        $refsFreeFields =
            $this
                ->connection
                ->executeQuery("
                    SELECT
                            champ_libre.id,
                            champ_libre.typage,
                            champ_libre.default_value,
                            champ_libre.elements,
                            champ_libre.required_create,
                            champ_libre.required_edit,
                            champ_libre.label,
                            champ_libre.elements,
                            type.id as typeId
                        FROM champ_libre
                        INNER JOIN categorie_cl cc on champ_libre.categorie_cl_id = cc.id
                        INNER JOIN type ON type.id = champ_libre.type_id
                        INNER JOIN category_type ON category_type.id = type.category_id
                        WHERE category_type.label = '${refArticleCategoryTypeLabel}' AND cc.label = '${refArticleCategoryCLabel}'
                ")->fetchAll();

        $allRefs =
            $this
                ->connection
                ->executeQuery('
                    SELECT reference_article.id, t.id as typeId
                    FROM reference_article
                    INNER JOIN type t on reference_article.type_id = t.id
                ')->fetchAll();
        foreach ($allRefs as $ref) {
            $freeFieldsToBeInsertedInJSON = [];
            $refId = intval($ref['id']);
            $typeId = intval($ref['typeId']);
            foreach ($refsFreeFields as $refFreeField) {
                $freeFieldId = intval($refFreeField['id']);
                $clTypeId = intval($refFreeField['typeId']);
                $refsFreeFieldInDB = $this
                    ->connection
                    ->executeQuery("
                        SELECT
                            reference_article.id,
                            valeur_champ_libre.valeur,
                            t.id as typeId
                        FROM reference_article
                        INNER JOIN valeur_champ_libre_reference_article vclra on reference_article.id = vclra.reference_article_id
                        INNER JOIN valeur_champ_libre ON valeur_champ_libre.id = vclra.valeur_champ_libre_id
                        INNER JOIN champ_libre ON champ_libre.id = valeur_champ_libre.champ_libre_id
                        INNER JOIN type t on reference_article.type_id = t.id
                        WHERE champ_libre.id = '${freeFieldId}' AND reference_article.id = '${refId}'
                    ")->fetchAll();

                $value = count($refsFreeFieldInDB) > 0
                    ? (isset($refsFreeFieldInDB[0]['valeur'])
                        ? $refsFreeFieldInDB[0]['valeur']
                        : "")
                    : "";
                if ($typeId === $clTypeId) {
                    $value = $refFreeField['typage'] === ChampLibre::TYPE_BOOL
                        ? (empty($value)
                            ? "0"
                            : "1")
                        : $value;
                    $freeFieldsToBeInsertedInJSON[] = [
                        'value' => strval($value),
                        'label' => $refFreeField['label'],
                        'requiredCreate' => $refFreeField['required_create'],
                        'requiredEdit' => $refFreeField['required_edit'],
                        'typage' => $refFreeField['typage'],
                        'defaultValue' => $refFreeField['default_value'],
                        'id' => $refFreeField['id'],
                        'elements' => json_decode($refFreeField['elements'] ?? "")
                    ];
                }
            }

            $encodedFreeFields = json_encode($freeFieldsToBeInsertedInJSON);

            $this
                ->addSql("UPDATE reference_article SET free_fields = '${encodedFreeFields}' WHERE reference_article.id = ${refId}");
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
