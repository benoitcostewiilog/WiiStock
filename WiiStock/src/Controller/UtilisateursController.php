<?php

namespace App\Controller;

use App\Entity\Utilisateurs;
use App\Form\UtilisateursType;
use App\Repository\UtilisateursRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Knp\Component\Pager\PaginatorInterface;

// use Proxies\__CG__\App\Entity\Utilisateurs;

/**
 * @Route("/admin/utilisateurs")
 */
class UtilisateursController extends Controller
{
    /**
     * @Route("/", name="utilisateurs_index", methods="GET|POST")
     */
    public function index(UtilisateursRepository $utilisateursRepository, /* EntityManagerInterface $em, */ Request $request, PaginatorInterface $paginator/* UserPasswordEncoderInterface $passwordEncoder */) : Response
    {
        // envoie des données en ajax pour la table
        /* if ($request->isXmlHttpRequest()) {

            $current = $request->request->get('current');
            $rowCount = $request->request->get('rowCount');
            $searchPhrase = $request->request->get('searchPhrase');
            $sort = $request->request->get('sort');

            $utilisateurs = $utilisateursRepository->findBySearchSort($searchPhrase, $sort);

            if ($searchPhrase != "") {
                $count = count($utilisateurs->getQuery()->getResult());
            } else {
                $count = count($utilisateursRepository->findAll());
            }

            if ($rowCount != -1) {
                $min = ($current - 1) * $rowCount;
                $max = $rowCount;

                $utilisateurs->setMaxResults($max)
                    ->setFirstResult($min);
            }

            $utilisateurs = $utilisateurs->getQuery()->getResult();

            $rows = array();
            foreach ($utilisateurs as $utilisateur) {
                $roles = $utilisateur->getRoles();
                $roles_string = "";
                foreach ($roles as $role) {
                    $roles_string = $role . ", " . $roles_string;
                }
 */
                // enlève les deux derniers caractères
               /*  $roles_string = substr($roles_string, 0, -2); */


                // format de la derniere date de connexion
                /* if ($utilisateur->getLastLogin()) {
                    $lastLogin = date_diff(new \Datetime(), $utilisateur->getLastLogin());

                    $format = "Il y a ";
                    if ($lastLogin->y) {
                        $format = $format . "environ " . $lastLogin->y . "an(s) " . $lastLogin->m . "mois";
                    } else if ($lastLogin->m) {
                        $format = $format . "environ " . $lastLogin->m . "mois " . $lastLogin->d . "jour(s)";
                    } else if ($lastLogin->d) {
                        $format = $format . $lastLogin->d . "jour(s) " . $lastLogin->h . "heure(s)";
                    } else if ($lastLogin->h) {
                        $format = $format . $lastLogin->h . "h" . $lastLogin->i . "min";
                    } else {
                        $format = $format . $lastLogin->i . "min";
                    }

                    $lastLogin = $lastLogin->format($format);

                } else {
                    $lastLogin = "Aucune connexion";
                }


                $row = [
                    "id" => $utilisateur->getId(),
                    "username" => $utilisateur->getUsername(),
                    "email" => $utilisateur->getEmail(),
                    "groupe" => $utilisateur->getGroupe(),
                    "lastLogin" => $lastLogin,
                    "roles" => $roles_string,
                ];

                array_push($rows, $row);
            }

            $data = array(
                "current" => intval($current),
                "rowCount" => intval($rowCount),
                "rows" => $rows,
                "total" => intval($count)
            );

            return new JsonResponse($data);
        }  */

        // gestion des formulaires

        /* $user = new Utilisateurs(); */
        
        // creation form
        /* $form_creation = $this->createForm(UtilisateursType::class, $user); */
/*
        $form_creation->handleRequest($request);

        if ($form_creation->isSubmitted() && $form_creation->isValid()) {
            $user = $form_creation->getData();

            $password = $passwordEncoder->encodePassword($user, $user->getPlainPassword());
            $user->setPassword($password);
            $user->setRoles(array('ROLE_USER'));
            
            $em->persist($user);
            $em->flush();
            
            return $this->redirectToRoute('utilisateurs_index');
        }*/

        // modification form
        /* $form_modif = $this->createFormBuilder()
            ->add('id_user', HiddenType::class, array(
                'mapped' => false,
            ))
            ->add('email', EmailType::class, array(
                'label' => "Adresse email"
            ))
            ->add('username', TextType::class, array(
                'label' => "Nom d'utilisateur"
            ))
            ->add('plainPassword', PasswordType::class, array(
                'label' => "Réinitialiser Mot de Passe",
            ))
            ->add('roles', ChoiceType::class, array(
                'label' => 'Rôles',
                'choices' => array(
                    'Utilisateur' => 'ROLE_USER',
                    'Utilisateur parc' => 'ROLE_PARC',
                    'Admin parc' => 'ROLE_PARC_ADMIN',
                ),
                'multiple' => true,
            ))
            ->getForm(); */

        /* dump($_POST); */

        if($_POST) 
        {
            $utilisateurId = array_keys($_POST); /* Chaque clé représente l'id d'un utilisateur */
            /* dump($utilisateurId); */ /* On regarde les clés = Id */

            for($i = 0; $i < count($utilisateurId); $i++) /* Pour chaque utilisateur on regarde si le rôle a changé */
            {
                $utilisateur = $utilisateursRepository->find($utilisateurId[$i]);
                $roles = $utilisateur->getRoles(); /* On regarde le rôle de l'utilisateur */
                /* dump($_POST[$utilisateurId[$i]]); */

                if($roles[0] != $_POST[$utilisateurId[$i]]) /* Si le rôle a changé on le modifie dans la bdd */
                {
                    $em = $this->getDoctrine()->getEntityManager();
                    $utilisateur->setRoles([$_POST[$utilisateurId[$i]]]);
                    $em->flush();
                }
                /* dump($utilisateur); */
            }
        }

        $pagination = $paginator->paginate(
            $utilisateursRepository->findAll(), /* On récupère la requête et on la pagine */
            $request->query->getInt('page', 1),
            2
        );
        

        return $this->render('utilisateurs/index.html.twig', [
            'utilisateurs' => $pagination,
/*             'form_creation' => $form_creation->createView(),
            'form_modif' => $form_modif->createView() */

        ]);
    }

    /**
     * @Route("/create", name="utilisateurs_index_create", methods="GET|POST")
     */
    public function create(Request $request, EntityManagerInterface $em, UtilisateursRepository $utilisateursRepository/* , UserPasswordEncoderInterface $passwordEncoder */)
    {

        /* Création nouvel utilisateur si POST */
        if (array_key_exists('username', $_POST) 
        && array_key_exists('email', $_POST)
        && array_key_exists('password', $_POST)
        && array_key_exists('password2', $_POST)
        ) {
            /* On vérifie les erreurs */
            dump($_POST['email']);
            echo "1";
            $erreurs = array();
            $userSearch = $utilisateursRepository->findCountEmail($_POST['email']);
            $userCount = $userSearch[0][1];
            dump($userSearch);


            /* On vérifie si l'email est valide ou si l'utilisateur existe déja */
            if(!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                array_push($erreurs, "L'adresse email est incorrect");
            }
            else if($userCount === '1')
            {
                array_push($erreurs, "Cette adresse est déja utilisée");
            }
            
            /* Si le mot de passe est trop court */
            if($_POST['password'] < 4) {
                array_push($erreurs, "Votre mot de passe est trop court");
                if($_POST['password'] != $_POST['password2']) {
                    array_push($erreurs, "Veuillez rentrer le même mot de passe");
                }
            }


            if(count($erreurs) === 0) { /* Si la création d'utilisateur est valide on crée l'utilisateur */

                $utilisateur = new Utilisateurs(); 
                
                foreach($_POST as $information) { 
                    strip_tags($information);
                }

                $password = $_POST['password']; /* Pour futur traitement comme le hashage*/

                $utilisateur->setUsername($_POST['username']);
                $utilisateur->setEmail($_POST['email']);
                $utilisateur->setPassword($password);
                
                /* Il faut ajouter le rôle utilisateur */

                $em = $this->getDoctrine()->getManager();
                $em->persist($utilisateur);
                $em->flush();
                return $this->redirectToRoute('utilisateurs_index');

            }
            else /* Sinon on envoi un tableau d'erreurs */
            {
                dump($erreurs);
                return $this->render('utilisateurs/create.html.twig', [
                    'erreurs' => $erreurs
                ]);
            }
            
        }
        else
        {
            return $this->render('utilisateurs/create.html.twig');
        }

        
        /* if ($request->isXmlHttpRequest()) {

            $new_user = new Utilisateurs();

            $user = $request->request->get('user');

            $new_user->setUsername($user[0]["value"]);
            $new_user->setEmail($user[1]["value"]);

            $password = $passwordEncoder->encodePassword($new_user, $user[2]["value"]);
            $new_user->setPassword($password);

            $new_user->setRoles(array('ROLE_USER'));

            $em->persist($new_user);
            $em->flush();
            $session = $request->getSession();
            $session->getFlashBag()->add('success', 'Félicitations ! L\'utilisateur a été créé avec succès !');
    
            return new JsonResponse(true);
        }
        throw new NotFoundHttpException('404 Léo not found'); */
    }


    /**
     * @Route("/modif", name="utilisateurs_index_modif", methods="GET|POST")
     */
    public function modif(Request $request, EntityManagerInterface $em, UserPasswordEncoderInterface $passwordEncoder)
    {

        if ($request->isXmlHttpRequest()) {

            $id = $request->request->get('id');
            $user = $em->getRepository(Utilisateurs::class)->find($id);

            $encoders = array(new JsonEncoder());
            $normalizers = array(new ObjectNormalizer());

            $serializer = new Serializer($normalizers, $encoders);
            $jsonContent = $serializer->serialize($user, 'json');
            return new JsonResponse($jsonContent);
        }
        throw new NotFoundHttpException('404 Léo not found');
    }


    /**
     * @Route("/modif_bis", name="utilisateurs_index_modif_bis", methods="GET|POST")
     */
    public function modif_bis(Request $request, EntityManagerInterface $em, UserPasswordEncoderInterface $passwordEncoder)
    {

        if ($request->isXmlHttpRequest()) {

            $user_modif = $request->request->get('user');


            $id = $user_modif[2]["value"];

            $user = $em->getRepository(Utilisateurs::class)->find($id);

            $user->setUsername($user_modif[0]["value"]);
            $user->setEmail($user_modif[1]["value"]);

            $plain_password = $user_modif[3]["value"];
            if ($plain_password) {
                $new_password = $passwordEncoder->encodePassword($user, $plain_password);
                $user->setPassword($new_password);
            }

            $roles = array();
            for ($i = 4; $i < count($user_modif) - 1; ++$i) {
                array_push($roles, $user_modif[$i]["value"]);
            }
            $user->setRoles($roles);

            $em->flush();
            $session = $request->getSession();
            $session->getFlashBag()->add('success', 'Félicitations ! L\'utilisateur a été modifié avec succès !');

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404 Léo not found');
    }


    /**
     * @Route("/ajax/username", name="utilisateurs_username_error", methods="GET|POST")
     */
    public function utlisateurs_username_error(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();
            $username = $request->request->get('username');

            $utilisateurs = $em->getRepository(Utilisateurs::class)->findAll();
            foreach ($utilisateurs as $utilisateur) {
                if (!strcmp($username, $utilisateur->getUsername())
                    && $utilisateur->getUsername() != null) {
                    return new JsonResponse(true);
                }
            }
            return new JsonResponse(false);
        }
        throw new NotFoundHttpException('404 Léo not found');
    }

    /**
     * @Route("/ajax/email", name="utilisateurs_email_error", methods="GET|POST")
     */
    public function utlisateurs_email_error(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();
            $email = $request->request->get('email');

            $utilisateurs = $em->getRepository(Utilisateurs::class)->findAll();
            foreach ($utilisateurs as $utilisateur) {
                if (!strcmp($email, $utilisateur->getEmail())
                    && $utilisateur->getEmail() != null) {
                    return new JsonResponse(true);
                }
            }
            return new JsonResponse(false);
        }
        throw new NotFoundHttpException('404 Léo not found');
    }
 

    /**
     * @Route("/new", name="utilisateurs_new", methods="GET|POST")
     */
    public function new(Request $request, UserPasswordEncoderInterface $passwordEncoder) : Response
    {
        $utilisateur = new Utilisateurs();
        $form = $this->createForm(UtilisateursType::class, $utilisateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $password = $passwordEncoder->encodePassword($utilisateur, $utilisateur->getPlainPassword());
            $utilisateur->setPassword($password);
            $em->persist($utilisateur);
            $em->flush();

            return $this->redirectToRoute('utilisateurs_index');
        }

        return $this->render('utilisateurs/new.html.twig', [
            'utilisateur' => $utilisateur,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="utilisateurs_show", methods="GET")
     */
    public function show(Utilisateurs $utilisateur) : Response
    {
        $receptions = $utilisateur->getReceptions();
        $demandes = $utilisateur->getDemandes();
        $alertes = $utilisateur->getUtilisateurAlertes();

        return $this->render('utilisateurs/show.html.twig', [
            'utilisateur' => $utilisateur, 
            'receptions' => $receptions,
            'demandes' => $demandes,
            'alertes' => $alertes
        ]);
    }

    /**
     * @Route("/{id}/edit", name="utilisateurs_edit", methods="GET|POST")
     */
    public function edit(Request $request, Utilisateurs $utilisateur, UserPasswordEncoderInterface $passwordEncoder) : Response
    {
        $form = $this->createForm(UtilisateursType::class, $utilisateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $password = $passwordEncoder->encodePassword($utilisateur, $utilisateur->getPlainPassword());
            $utilisateur->setPassword($password);
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('utilisateurs_edit', ['id' => $utilisateur->getId()]);
        }

        return $this->render('utilisateurs/edit.html.twig', [
            'utilisateur' => $utilisateur,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="utilisateurs_delete", methods="DELETE")
     */
    public function delete(Request $request, Utilisateurs $utilisateur) : Response
    {
        if ($this->isCsrfTokenValid('delete' . $utilisateur->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($utilisateur);
            $em->flush();
        }

        return $this->redirectToRoute('utilisateurs_index');
    }

    /**
     * @Route("/{id}/remove", name="utilisateurs_remove", methods="DELETE")
     */
    public function remove(Request $request, Utilisateurs $utilisateur) : Response
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($utilisateur);
        $em->flush();
        $session = $request->getSession();
        $session->getFlashBag()->add('success', 'Félicitations ! L\'utilisateur a été supprimé avec succès !');


        return $this->redirectToRoute('utilisateurs_index');
    }
}
