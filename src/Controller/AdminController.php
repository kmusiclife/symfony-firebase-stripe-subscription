<?php

namespace App\Controller;

use Kreait\Firebase\Auth;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin")
 */
class AdminController extends AbstractController
{
    /** @var Auth */
    private $auth;

    public function __construct(Auth $firebase)
    {
        $this->auth = $firebase;
    }
    /**
     * @Route("/", name="admin_index")
     */
    public function index()
    {
        return $this->render('admin/index.html.twig', []);
    }
    /**
     * @Route("/users", name="users")
     */
    public function users(): JsonResponse
    {
        return $this->json([
            'data' => iterator_to_array($this->auth->listUsers()),
        ]);
    }
}
