<?php

namespace App\Controller;

use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\FiltreRef;
use App\Entity\Type;
use App\Repository\ChampLibreRepository;
use App\Repository\EmplacementRepository;
use App\Repository\FiltreRefRepository;
use App\Repository\TypeRepository;
use App\Service\RefArticleDataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class FiltreRefController
 * @package App\Controller
 * @Route("/filtre-ref")
 */
class FiltreRefController extends AbstractController
{
    /**
     * @var ChampLibreRepository
     */
    private $champLibreRepository;

    /**
     * @var FiltreRefRepository
     */
    private $filtreRefRepository;

    /**
     * @var RefArticleDataService
     */
    private $refArticleDataService;

	/**
	 * @var TypeRepository
	 */
    private $typeRepository;

	/**
	 * @var EmplacementRepository
	 */
    private $emplacementRepository;

	/**
	 * FiltreRefController constructor.
	 * @param TypeRepository $typeRepository
	 * @param EmplacementRepository $emplacementRepository
	 * @param ChampLibreRepository $champLibreRepository
	 * @param FiltreRefRepository $filtreRefRepository
	 * @param RefArticleDataService $refArticleDataService
	 */
    public function __construct(TypeRepository $typeRepository, EmplacementRepository $emplacementRepository, ChampLibreRepository $champLibreRepository, FiltreRefRepository $filtreRefRepository, RefArticleDataService $refArticleDataService)
    {
        $this->champLibreRepository = $champLibreRepository;
        $this->filtreRefRepository = $filtreRefRepository;
        $this->refArticleDataService = $refArticleDataService;
        $this->typeRepository = $typeRepository;
        $this->emplacementRepository = $emplacementRepository;
    }

    /**
     * @Route("/creer", name="filter_ref_new", options={"expose"=true})
     */
    public function new(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $em = $this->getDoctrine()->getManager();

            // on vérifie qu'il n'existe pas déjà un filtre sur le même champ
            $userId = $this->getUser()->getId();
            $existingFilter = $this->filtreRefRepository->countByChampAndUser($data['field'], $userId);

            if($existingFilter == 0) {
                $filter = new FiltreRef();

                // opérateur
//				$operator = isset($data['operator']) ? $data['operator'] : 'and';
//				$filter->setOperator($operator);

				// champ Champ Libre
                if (isset($data['field'])) {
                    $field = $data['field'];

                    if (intval($field) != 0) {
                        $champLibre = $this->champLibreRepository->find(intval($field));
                        $filter->setChampLibre($champLibre);
                    } else {
                        $filter->setChampFixe($data['field']);
                    }
                } else {
                    return new JsonResponse(false); //TODO gérer retour erreur (champ obligatoire)
                }

                // champ Value
                if (isset($data['value'])) {
                    $filter->setValue($data['value']);
                }

                // champ Utilisateur
                $user = $this->getUser();
                $filter->setUtilisateur($user);

                $em->persist($filter);
                $em->flush();

                $filterArray = [
                    'id' => $filter->getId(),
                    'champLibre' => $filter->getChampLibre(),
                    'champFixe' => $filter->getChampFixe(),
                    'value' => $filter->getValue(),
//					'operator' => $filter->getOperator()
                ];

                $result = [
                    'filterHtml' => $this->renderView('reference_article/oneFilter.html.twig', ['filter' => $filterArray])
                ];
            } else {
                $result = false; //TODO gérer retour erreur (filtre déjà existant)
            }
            return new JsonResponse($result);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimer", name="filter_ref_delete", options={"expose"=true})
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $filterId = $data['filterId'];

            if ($filterId) {
                $filter = $this->filtreRefRepository->find($filterId);

                if ($filter) {
                    $em = $this->getDoctrine()->getManager();
                    $em->remove($filter);
                    $em->flush();
                }
            }
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

	/**
	 * @Route("/affiche-liste", name="display_field_elements", options={"expose"=true}, methods={"GET","POST"})
	 */
	public function displayFieldElements(Request $request)
	{
		if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

			$value = $data['value'];

			if ($value === 'Emplacement') {
				$emplacements = $this->emplacementRepository->findAll();
				$options = [];
				foreach ($emplacements as $emplacement) {
					$options[] = $emplacement->getLabel();
				}
			} else if ($value === 'Type') {
				$types = $this->typeRepository->findByCategoryLabel(CategoryType::ARTICLE);
				$options = [];
				foreach ($types as $type) {
					$options[] = $type->getLabel();
				}
			} else {
				$cl = $this->champLibreRepository->find(intval($value)); /** @var $cl ChampLibre */
				$options = $cl->getElements();
			}

			$view = $this->renderView('reference_article/selectInFilter.html.twig', [
				'options' => $options,
			]);
			return new JsonResponse($view);
		}
		throw new NotFoundHttpException("404");
	}
}
