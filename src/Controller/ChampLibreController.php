<?php

namespace App\Controller;

use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\CategorieCL;
use App\Entity\ChampLibre;

use App\Entity\Collecte;
use App\Entity\Demande;
use App\Entity\Handling;
use App\Entity\Reception;
use App\Entity\ReferenceArticle;
use App\Entity\Type;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/type")
 */
class ChampLibreController extends AbstractController
{


    const CATEGORY_CL_TO_CLASSNAMES = [
        CategorieCL::RECEPTION => Reception::class,
        CategorieCL::ARTICLE => Article::class,
        CategorieCL::REFERENCE_ARTICLE => ReferenceArticle::class,
        CategorieCL::ARRIVAGE => Arrivage::class,
        CategorieCL::DEMANDE_COLLECTE => Collecte::class,
        CategorieCL::DEMANDE_LIVRAISON => Demande::class,
        CategorieCL::DEMANDE_HANDLING => Handling::class,
        CategorieCL::AUCUNE => null
    ];

    /**
     * @Route("/api/{id}", name="champ_libre_api", options={"expose"=true}, methods={"POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param $id
     * @return Response
     * @throws Exception
     */
    public function api(Request $request,
                        EntityManagerInterface $entityManager,
                        $id): Response
    {
        if ($request->isXmlHttpRequest()) { //Si la requête est de type Xml
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
            $champsLibres = $champLibreRepository->findByType($id);
            $rows = [];
            foreach ($champsLibres as $champLibre) {

                if ($champLibre->getTypage() === ChampLibre::TYPE_BOOL) {
                    $typageCLFr = 'Oui/Non';
                } elseif ($champLibre->getTypage() === ChampLibre::TYPE_NUMBER) {
                    $typageCLFr = 'Nombre';
                } elseif ($champLibre->getTypage() === ChampLibre::TYPE_TEXT) {
                    $typageCLFr = 'Texte';
                } elseif ($champLibre->getTypage() === ChampLibre::TYPE_LIST) {
                    $typageCLFr = 'Liste';
                } elseif ($champLibre->getTypage() === ChampLibre::TYPE_DATE) {
                    $typageCLFr = 'Date';
                } elseif ($champLibre->getTypage() === ChampLibre::TYPE_DATETIME) {
                    $typageCLFr = 'Date et heure';
                } elseif ($champLibre->getTypage() === ChampLibre::TYPE_LIST_MULTIPLE) {
                    $typageCLFr = 'Liste multiple';
                } else {
                    $typageCLFr = '';
                }

                $defaultValue = $champLibre->getDefaultValue();
                if ($champLibre->getTypage() == ChampLibre::TYPE_BOOL) {
                    $defaultValue = $champLibre->getDefaultValue() ? 'oui' : 'non';
                } else if ($champLibre->getTypage() === ChampLibre::TYPE_DATETIME
                    || $champLibre->getTypage() === ChampLibre::TYPE_DATE) {
                    $defaultValueDate = new DateTime(str_replace('/', '-', $defaultValue));
                    $defaultValue = $defaultValueDate->format('d/m/Y H:i');
                }

                $rows[] =
                    [
                        'id' => ($champLibre->getId() ? $champLibre->getId() : 'Non défini'),
                        'Label' => ($champLibre->getLabel() ? $champLibre->getLabel() : 'Non défini'),
                        "S'applique à" => ($champLibre->getCategorieCL() ? $champLibre->getCategorieCL()->getLabel() : ''),
                        'Typage' => $typageCLFr,
                        'Affiché à la création' => ($champLibre->getDisplayedCreate() ? "oui" : "non"),
                        'Obligatoire à la création' => ($champLibre->getRequiredCreate() ? "oui" : "non"),
                        'Obligatoire à la modification' => ($champLibre->getRequiredEdit() ? "oui" : "non"),
                        'Valeur par défaut' => $defaultValue,
                        'Elements' => $champLibre->getTypage() == ChampLibre::TYPE_LIST || $champLibre->getTypage() == ChampLibre::TYPE_LIST_MULTIPLE ? $this->renderView('champ_libre/champLibreElems.html.twig', ['elems' => $champLibre->getElements()]) : '',
                        'Actions' => $this->renderView('champ_libre/datatableChampLibreRow.html.twig', ['idChampLibre' => $champLibre->getId()]),
                    ];
            }
            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/voir/{id}/champs-libres", name="champs_libre_show", methods={"GET","POST"})
     * @param EntityManagerInterface $entityManager
     * @param $id
     * @return Response
     */
    public function show(EntityManagerInterface $entityManager,
                         $id): Response {
        $categorieCLRepository = $entityManager->getRepository(CategorieCL::class);
        $typages = ChampLibre::TYPAGE;
        return $this->render('champ_libre/show.html.twig', [
            'type' => $entityManager->find(Type::class, $id),
            'categoriesCL' => $categorieCLRepository->findByLabel([CategorieCL::ARTICLE, CategorieCL::REFERENCE_ARTICLE]),
            'typages' => $typages,
        ]);
    }

    /**
     * @Route("/new", name="champ_libre_new", options={"expose"=true}, methods={"GET","POST"}, condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager): Response
    {
        $data = json_decode($request->getContent(), true);

        $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $categorieCLRepository = $entityManager->getRepository(CategorieCL::class);

		// on vérifie que le nom du champ libre n'est pas déjà utilisé
		$champLibreExist = $champLibreRepository->countByLabel($data['label']);
		if (!$champLibreExist) {
			$type = $typeRepository->find($data['type']);
			$champLibre = new ChampLibre();
			$champLibre
				->setlabel($data['label'])
				->setRequiredCreate($data['displayedCreate'] ? $data['requiredCreate'] : false)
				->setRequiredEdit($data['requiredEdit'])
				->setDisplayedCreate($data['displayedCreate'])
				->setType($type)
				->settypage($data['typage']);

			if (isset($data['categorieCL'])) {
                $champLibre->setCategorieCL($categorieCLRepository->find($data['categorieCL']));
            } else {
                $champLibre->setCategorieCL($categorieCLRepository->findOneBy([
                    'categoryType' => $type->getCategory()
                ]));
            }

			if (in_array($champLibre->getTypage(), [ChampLibre::TYPE_LIST, ChampLibre::TYPE_LIST_MULTIPLE])) {
				$champLibre
					->setElements(array_filter(explode(';', $data['elem'])))
					->setDefaultValue(null);
			} else {
				$champLibre
					->setElements(null)
					->setDefaultValue($data['valeur']);
			}
			$entityManager->persist($champLibre);
            $entityManager->flush();

            return $this->json([
                'success' => true,
                'msg' => 'Le champ libre <strong>' . $data['label'] . '</strong> a bien été créé.'
            ]);
		} else {
			return new JsonResponse([
			    'success' => false,
                'msg' => 'Le champ libre <strong>' . $data['label'] . '</strong> existe déjà, veuillez définir un autre nom.'
            ]);
		}
    }

    /**
     * @Route("/api-modifier", name="champ_libre_api_edit", options={"expose"=true},  methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function editApi(Request $request,
                            EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
            $categorieCLRepository = $entityManager->getRepository(CategorieCL::class);
            $champLibre = $champLibreRepository->find($data['id']);
            $typages = ChampLibre::TYPAGE;

            $json = $this->renderView('champ_libre/modalEditChampLibreContent.html.twig', [
                'champLibre' => $champLibre,
                'typageCL' => ChampLibre::TYPAGE_ARR[$champLibre->getTypage()],
                'categoriesCL' => $categorieCLRepository->findByLabel([CategorieCL::ARTICLE, CategorieCL::REFERENCE_ARTICLE]),
                'typages' => $typages,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="champ_libre_edit", options={"expose"=true},  methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function edit(Request $request,
                         EntityManagerInterface $entityManager): Response
    {
        $data = json_decode($request->getContent(), true);

        $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
        $categorieCLRepository = $entityManager->getRepository(CategorieCL::class);

		$categorieCL = $categorieCLRepository->find($data['categorieCL']);
		$champLibre = $champLibreRepository->find($data['champLibre']);

		$champLibre
			->setLabel($data['label'])
			->setCategorieCL($categorieCL)
			->setRequiredCreate($data['displayedCreate'] ? $data['requiredCreate'] : false)
			->setRequiredEdit($data['requiredEdit'])
			->setDisplayedCreate($data['displayedCreate'])
			->setTypage($data['typage']);
		if (in_array($champLibre->getTypage(), [ChampLibre::TYPE_LIST, ChampLibre::TYPE_LIST_MULTIPLE])) {
			$champLibre
				->setElements(array_filter(explode(';', $data['elem'])))
				->setDefaultValue(null);
		} else {
			$champLibre
				->setElements(null)
				->setDefaultValue($data['valeur']);
		}
		$em = $this->getDoctrine()->getManager();
		$em->flush();
        return $this->json([
            'success' => true,
            'msg' => 'Le champ libre <strong>' . $data['label'] . '</strong> a bien été modifié.'
        ]);
    }

    /**
     * @Route("/delete", name="champ_libre_delete",options={"expose"=true}, methods={"GET","POST"}, condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        $data = json_decode($request->getContent(), true);

        $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

		$champLibre = $champLibreRepository->find($data['champLibre']);
		$filters = $champLibre->getFilters();
		$ffLabel = $champLibre->getLabel();
		foreach ($filters as $filter) {
		    $entityManager->remove($filter);
        }

		$categorieCL = $champLibre->getCategorieCL();
		$categorieCLLabel = $categorieCL ? $categorieCL->getLabel() : null;

        $userFieldToRemove = $categorieCLLabel === CategorieCL::ARTICLE
            ? 'rechercheForArticle'
            : ($categorieCLLabel === CategorieCL::REFERENCE_ARTICLE
                ? 'recherche'
                : null);
		if ($userFieldToRemove) {
		    $utilisateurRepository->removeFromSearch($userFieldToRemove, ucfirst(strtolower($champLibre->getLabel())));
        }
		$entityManager->remove($champLibre);
		$entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => 'Le champ libre <strong>' . $ffLabel . '</strong> a bien été supprimé.'
        ]);
    }

    /**
     * @Route("/display-require-champ", name="display_required_champs_libres", options={"expose"=true},  methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function displayRequiredChampsLibres(Request $request,
                                                EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
            $typeRepository = $entityManager->getRepository(Type::class);

            if (array_key_exists('create', $data)) {
                $type = $typeRepository->find($data['create']);
                $champsLibres = $champLibreRepository->getByTypeAndRequiredCreate($type);
            } else if (array_key_exists('edit', $data)) {
                $type = $typeRepository->find($data['edit']);
                $champsLibres = $champLibreRepository->getByTypeAndRequiredEdit($type);
            } else {
                $json = false;
                return new JsonResponse($json);
            }
            $json = [];
            foreach ($champsLibres as $champLibre) {
                $json[] = $champLibre['id'];
            }
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }
}
