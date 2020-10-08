<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Kreait\Firebase\Auth;
use Kreait\Firebase\Firestore;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class SignController extends AbstractController
{

    private $session, $auth, $firestore, $user;

    public function __construct(SessionInterface $session, Auth $auth, Firestore $firestore)
    {
        $this->session = $session;
        $this->auth = $auth;
        $this->firestore = $firestore;
    }
    /**
     * @Route("/verify", name="verify")
     */
    public function verify(Request $request)
    {   
        $idToken = $request->headers->get('idToken');
        $stripe = new \Stripe\StripeClient(
            $this->getParameter('stripe_sk_key')
        );

        // verify
        // https://firebase-php.readthedocs.io/en/latest/authentication.html#verify-a-firebase-id-token
        
        try {
            $verifiedIdToken = $this->auth->verifyIdToken($idToken);
        } catch (\InvalidArgumentException $e) {
            $res = new Response( json_encode(array( 'error' => $e->getMessage() )) );
            $res->setStatusCode(500);
            $res->headers->set('Content-Type','application/json');
            return $res;

        } catch (InvalidToken $e) {
            $res = new Response( json_encode(array( 'error' => $e->getMessage() )) );
            $res->setStatusCode(500);
            $res->headers->set('Content-Type','application/json');
            return $res;
        }

        // Firebase Verified
        $uid = $verifiedIdToken->getClaim('sub');
        $fire_user = $this->auth->getUser($uid);
        
        // Firestore data restore to local user
        $database = $this->firestore->database();
        $docRef = $database->collection('users')->document( $fire_user->uid );
        $fire_data = $docRef->snapshot()->data();

        if(!$fire_data) $fire_data = array();
        $docRef->set($fire_data);

        // redirect method treating
        $redirect_url = $this->session->get('redirect_url');
        $json = json_encode(array( 'redirect_url' => $redirect_url ) );
        $this->session->set('redirect_url', null);

        // combine firebase and firestore with local user
        $em = $this->getDoctrine()->getManager();
        $db_user = $em->getRepository(User::class)->findOneByFirebaseUid( $fire_user->uid );
        if(!$db_user){
            $db_user = new User();
            $db_user->setFirebaseUid( $fire_user->uid );
        }
        $db_user->setUser(json_encode($fire_user));
        $db_user->setData(json_encode($fire_data));
        $db_user->setLoginAt(new \DateTime());

        // start: Create roles
        $customer = isset($fire_data['customer']) ? $fire_data['customer'] : null;
        $subscriptions = isset($fire_data['subscriptions']) ? $fire_data['subscriptions'] : array();

        $roles = array();
        if($customer) array_push($roles, 'ROLE_CARD');
        if($subscriptions) array_push($roles, 'ROLE_SUBSCRIPTION');
        foreach($subscriptions as $subscription)
        {
            $subscription_data = $stripe->subscriptions->retrieve($subscription);
            $role = $subscription_data->items->data[0]->price->metadata->ROLE;
            if($role) array_push($roles, $role);
        }
        $roles = array_unique($roles);
        $db_user->setRoles($roles);
        $em->persist($db_user);
        $em->flush();
        // end: Create roles

        // Re-Login 
        $token = new UsernamePasswordToken($db_user, null, 'main', $db_user->getRoles());
        $this->get('security.token_storage')->setToken($token);

        // make response
        $res = new Response($json);
        $res->setStatusCode(200);
        $res->headers->set('Content-Type','application/json');
        
        return $res;
    }
    /**
     * @Route("/signin", name="signin")
     */
    public function signin()
    {
        $csrf_id = sha1( uniqid() );
        $csrf = $this->get('security.csrf.token_manager')->getToken($csrf_id);
        $this->session->set('csrf', $csrf);
        return $this->render('sign/signin.html.twig', ['csrf_id' => $csrf_id]);
    }
    /**
     * @Route("/signout", name="signout")
     */
    public function signout()
    {
        $this->get('security.token_storage')->setToken();
        return $this->render('sign/signout.html.twig', []);
    }

}
