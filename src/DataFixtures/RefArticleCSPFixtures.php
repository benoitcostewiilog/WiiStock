<?php

namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\ChampsLibre;
use App\Entity\Emplacement;
use App\Entity\Fournisseur;
use App\Entity\Type;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\CategorieCLRepository;
use App\Repository\EmplacementRepository;
use App\Repository\FournisseurRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\ValeurChampsLibreRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\ReferenceArticle;
use App\Entity\ValeurChampsLibre;
use App\Repository\TypeRepository;
use App\Repository\ChampsLibreRepository;

class RefArticleCSPFixtures extends Fixture implements FixtureGroupInterface
{
    private $encoder;


    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var ChampsLibreRepository
     */
    private $champsLibreRepository;

    /**
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $refArticleRepository;

    /**
     * @var CategorieCLRepository
     */
    private $categorieCLRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var ArticleFournisseurRepository
     */
    private $articleFournisseurRepository;

    /**
     * @var ValeurChampsLibreRepository
     */
    private $valeurCLRepository;


    public function __construct(ValeurChampsLibreRepository $valeurChampsLibreRepository, ArticleFournisseurRepository $articleFournisseurRepository, EmplacementRepository $emplacementRepository, UserPasswordEncoderInterface $encoder, TypeRepository $typeRepository, ChampsLibreRepository $champsLibreRepository, FournisseurRepository $fournisseurRepository, StatutRepository $statutRepository, ReferenceArticleRepository $refArticleRepository, CategorieCLRepository $categorieCLRepository)
    {
        $this->typeRepository = $typeRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->encoder = $encoder;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->statutRepository = $statutRepository;
        $this->refArticleRepository = $refArticleRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->valeurCLRepository = $valeurChampsLibreRepository;
    }

    public function load(ObjectManager $manager)
    {
        $path = "src/DataFixtures/Csv/csp.csv";
        $file = fopen($path, "r");

        $rows = [];
        while (($data = fgetcsv($file, 1000, ";")) !== false) {
            $rows[] = array_map('utf8_encode', $data);
        }

        array_shift($rows); // supprime la 1è ligne d'en-têtes

        // à modifier pour faire imports successifs
        $rows = array_slice($rows, 0, 1000);

        $i = 1;
        foreach($rows as $row) {
            if (empty($row[0])) continue;
            dump($i);
            $i++;
            $typeCsp = $this->typeRepository->findOneBy(['label' => Type::LABEL_CSP]);

            // si l'article de référence n'existe pas déjà, on le crée
            $referenceArticle = $this->refArticleRepository->findOneBy(['reference' => $row[0]]);
            if (empty($referenceArticle)) {
                // champs fixes
                $referenceArticle = new ReferenceArticle();
                $referenceArticle
                    ->setType($typeCsp)
                    ->setStatut($this->statutRepository->findOneByCategorieAndStatut(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF))
                    ->setReference($row[0])
                    ->setLibelle($row[1])
                    ->setTypeQuantite(ReferenceArticle::TYPE_QUANTITE_ARTICLE);
                $manager->persist($referenceArticle);
                $manager->flush();

                // champ fournisseur
                $fournisseurLabel = $row[5];
                if (empty($fournisseurLabel)) {
                    $fournisseurLabel = 'A DETERMINER';
                    $fournisseurRef = 'A_DETERMINER';
                } else {
                    $fournisseurRef = $row[6];
                }

                if (in_array($fournisseurRef, ['nc', 'nd', 'NC', 'ND', '*', '.', ''])) {
                    $fournisseurRef = $fournisseurLabel;
                }
                $fournisseur = $this->fournisseurRepository->findOneBy(['codeReference' => $fournisseurRef]);

                // si le fournisseur n'existe pas, on le crée
                if (empty($fournisseur)) {
                    $fournisseur = new Fournisseur();
                    $fournisseur
                        ->setNom($fournisseurLabel)
                        ->setCodeReference($fournisseurRef);
                    $manager->persist($fournisseur);
                }


                // on crée l'article fournisseur et on le lie au fournisseur et à l'article de référence
                $articleFournisseur = new ArticleFournisseur();
                $articleFournisseur
                    ->setLabel($row[1])
                    ->setReference(time() . '-' . $i)// code aléatoire unique
                    ->setFournisseur($fournisseur)
                    ->setReferenceArticle($referenceArticle);

                $manager->persist($articleFournisseur);


                // champs libres
                $listFields = [
                    ['label' => 'famille produit', 'col' => 4, 'type' => ChampsLibre::TYPE_LIST, 'elements' => ['CONSOMMABLES', 'PAD', 'POMPE', 'POMPE_41', 'PIECES DETACHEES', 'PDT GENERIQUE', 'DCOS TEST ELECTRIQUE', 'SILICIUM', 'SIL_EXTERNE', 'SIL_INTERNE', 'MOBILIER SB', 'MOBILIER TERTIAIRE', 'CIBLE / SLUGS']],
                    ['label' => "stock mini", 'col' => 7, 'type' => ChampsLibre::TYPE_NUMBER],
                    ['label' => "stock alerte", 'col' => 8, 'type' => ChampsLibre::TYPE_NUMBER],
                    ['label' => "prix du stock final", 'col' => 11, 'type' => ChampsLibre::TYPE_DATE],
                    ['label' => "alerte mini", 'col' => 12, 'type' => ChampsLibre::TYPE_LIST, 'elements' => ['besoin', '']],
                    ['label' => "alerte prévision", 'col' => 13, 'type' => ChampsLibre::TYPE_NUMBER],
                ];

                foreach ($listFields as $field) {
                    $vcl = new ValeurChampsLibre();
                    $label = $field['label'] . ' (' . $typeCsp->getLabel() . ')';
                    $cl = $this->champsLibreRepository->findOneBy(['label' => $label]);
                    if (empty($cl)) {
                        dump('il manque le champ libre de label ' . $label);
                    } else {
                        $vcl
                            ->setChampLibre($cl)
                            ->addArticleReference($referenceArticle)
                            ->setValeur($row[$field['col']]);
                        $manager->persist($vcl);
                    }
                }
                $manager->flush();
            }

            // on crée l'article
            $article = new Article();
            $article
                ->setReference($row[0] . '-' . $i)
                ->setLabel($row[1])
                ->setStatut($this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_ACTIF))
                ->setType($typeCsp)
                ->setConform(true)
                ->setQuantite(intval($row[3]));

            // champ fournisseur
            $fournisseurLabel = $row[5];
            if (empty($fournisseurLabel)) {
                $fournisseurLabel = 'A DETERMINER';
                $fournisseurRef = 'A_DETERMINER';
            } else {
                $fournisseurRef = $row[6];
            }

            if (in_array($fournisseurRef, ['nc', 'nd', 'NC', 'ND', '*', '.', ''])) {
                $fournisseurRef = $fournisseurLabel;
            }
            $fournisseur = $this->fournisseurRepository->findOneBy(['codeReference' => $fournisseurRef]);

            // si le fournisseur n'existe pas, on le crée
            if (empty($fournisseur)) {
                $fournisseur = new Fournisseur();
                $fournisseur
                    ->setNom($fournisseurLabel)
                    ->setCodeReference($fournisseurRef);
                $manager->persist($fournisseur);
            }


            // on lie l'article à l'article fournisseur
            $article->setArticleFournisseur($articleFournisseur);


            // champ emplacement
            $emplacementLabel = $row[2];
            if (!empty($emplacementLabel)) {
                $emplacement = $this->emplacementRepository->findOneBy(['label' => $emplacementLabel]);

                // si l'emplacement n'existe pas, on le crée
                if (empty($emplacement)) {
                    $emplacement = new Emplacement();
                    $emplacement->setLabel($emplacementLabel);
                    $manager->persist($emplacement);
                }

                $article->setEmplacement($emplacement);
            }
            $manager->persist($article);

            // champs libres
            $listFields = [
                ['label' => "prix unitaire", 'col' => 9, 'type' => ChampsLibre::TYPE_TEXT],
                ['label' => "date entrée", 'col' => 10, 'type' => ChampsLibre::TYPE_DATE],
                ['label' => "péremptions", 'col' => 14, 'type' => ChampsLibre::TYPE_DATE],
            ];

            foreach ($listFields as $field) {
                $vcl = new ValeurChampsLibre();
                $label = $field['label'] . ' (' . $typeCsp->getLabel() . ')';
                $cl = $this->champsLibreRepository->findOneBy(['label' => $label]);
                if (empty($cl)) {
                    dump('il manque le champ libre de label ' . $label);
                } else {
                    $vcl
                        ->setChampLibre($cl)
                        ->addArticle($article)
                        ->setValeur($row[$field['col']]);
                    $manager->persist($vcl);
                }
            }
            $manager->flush();
        }
        fclose($file);
    }

    public static function getGroups():array {
        return ['articlesCSP', 'articles'];
    }

}
