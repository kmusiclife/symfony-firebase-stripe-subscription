<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Kreait\Firebase\Firestore;
use App\Form\CardFormType;
use App\Entity\Card;

/**
 * @Route("/subscription")
 */
class SubscriptionController extends AbstractController
{

    private $firestore;

    public function __construct(Firestore $firestore)
    {
        $this->firestore = $firestore;
    }
    /**
     * @Route("/", name="subscription", methods={"get"})
     */
    public function index(Request $request)
    {
        
        $user = $this->getUser();
        $customer = $user->getData()->customer;

        $stripe = new \Stripe\StripeClient(
            $this->getParameter('stripe_sk_key')
        );
        $subscription_lists = [];

        $_prices = $stripe->prices->all();
        $_subscriptions = $stripe->subscriptions->all(['customer' => $customer ]);

        foreach($_prices as $_price){

            $subscription_id = false;
            $_product = $stripe->products->retrieve( $_price->product );
            foreach($_subscriptions as $_subscription){
                if($_subscription->items->data[0]->price->id == $_price->id ) {
                    $subscription_id = $_subscription->id;
                }
            }
            array_push($subscription_lists, array(
                'plan' => $_price,
                'product' => $_product,
                'subscription_id' => $subscription_id
            ));
        }
        return $this->render('subscription/index.html.twig', [
            'subscription_lists' => $subscription_lists
        ]);

    }
    /**
     * @Route("/do", name="subscription_do", methods={"post"})
     */
    public function do(Request $request)
    {

        $user = $this->getUser();
        $em = $this->getDoctrine()->getManager();

        try {

            $stripe = new \Stripe\StripeClient(
                $this->getParameter('stripe_sk_key')
            );
            $product_id = $request->get('product_id');
            $plan_id = $request->get('plan_id');
            $customer = $user->getData()->customer;

            $_subscriptions = $stripe->subscriptions->all([
                'status' => 'active',
                'customer' => $customer,
                'limit' => 100
            ]);
            foreach($_subscriptions as $_subscription){
                if( $_subscription->items->data[0]->plan->id == $plan_id ){
                    $this->addFlash('error', 'すでに契約しているプランです');
                    return $this->redirectToRoute('subscription');
                }
            }

            $plan = $stripe->plans->retrieve($plan_id);
            $subscription = $stripe->subscriptions->create([
                'customer' => $customer,
                'items' => [
                    ['price' => $plan_id],
                ],
            ]);

        } catch(Exception $e){
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('subscription');
        }

        // Firebase user update
        $database = $this->firestore->database();
        $docRef = $database->collection('users')->document( $user->getFirebaseUid() );
        $user_data = $docRef->snapshot()->data(); // array
        
        if(isset($user_data['subscriptions'])){
            $user_subscriptions = is_array($user_data['subscriptions']) ? $user_data['subscriptions'] : array();
        } else $user_subscriptions = array();

        array_push($user_subscriptions, $subscription->id);
        $user_data['subscriptions'] = $user_subscriptions;
        $docRef->set($user_data);

        // ROLEs
        $role = isset($plan->metadata->ROLE) ? $plan->metadata->ROLE : null; // new role
        $roles = $user->getRoles();
        array_push($roles, $role);
        array_push($roles, 'ROLE_SUBSCRIPTION');
        $roles = array_unique($roles);

        $user->setRoles($roles);
        $user->setData( json_encode($user_data) );
        $em->persist($user);
        $em->flush();

        // re-Login 
        $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
        $this->get('security.token_storage')->setToken($token);

        $this->addFlash('success', '定期購入契約しました');
        return $this->redirectToRoute('subscription');

    }
    /**
     * @Route("/confirm", name="subscription_confirm", methods={"post"})
     */
    public function confirm(Request $request)
    {

        $user = $this->getUser();

        try {

            $stripe = new \Stripe\StripeClient(
                $this->getParameter('stripe_sk_key')
            );

            $product_id = $request->get('product_id');
            $product = $stripe->products->retrieve($product_id);
            $plan_id = $request->get('plan_id');
            $plan = $stripe->plans->retrieve($plan_id);
            $customer = $user->getData()->customer;

            $_subscriptions = $stripe->subscriptions->all([
                'status' => 'active',
                'customer' => $customer,
                'limit' => 100
            ]); 

        } catch(Exception $e){
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('subscription');
        }
        foreach($_subscriptions as $_subscription){
            if( $_subscription->items->data[0]->plan->id == $plan_id ){
                $this->addFlash('error', 'すでに契約しているプランです');
                return $this->redirectToRoute('subscription');
            }
        }
        return $this->render('subscription/confirm.html.twig', [
            'product' => $product,
            'plan' => $plan
        ]);
    }
    /**
     * @Route("/cancel", name="subscription_cancel", methods={"post"})
     */
    public function cancel(Request $request)
    {
        
        $user = $this->getUser();
        $customer = $user->getData()->customer;

        try{
            
            $stripe = new \Stripe\StripeClient(
                $this->getParameter('stripe_sk_key')
            );
            $subscription_id = $request->get('subscription_id');
            $subscription_data = $stripe->subscriptions->retrieve($subscription_id);

        } catch(Exception $e){
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('subscription');
        }
        if(!$subscription_data){
            $this->addFlash('danger', 'すでにキャンセルされています');
            return $this->redirectToRoute('subscription');
        }

        $subscription_ids = is_array($user->getData()->subscriptions) ?
            $user->getData()->subscriptions : array();
        
        if( !in_array($subscription_id, $subscription_ids) ){
            $this->addFlash('danger', '購読がありません。再度ログインしたあとにもう一度試してください。');
            return $this->redirectToRoute('subscription');
        }

        if( $subscription_data ){
            $plan_data = $subscription_data->items->data[0]->plan;
            $product_data = $stripe->products->retrieve($plan_data->product);
        } else {
            $plan_data = null;
            $product_data = null;
        }
        
        return $this->render('subscription/cancel.html.twig', [
            'subscription_data' => $subscription_data,
            'product_data' => $product_data,
            'plan_data' => $plan_data
        ]);
    }
    /**
     * @Route("/cancel/do", name="subscription_cancel_do", methods={"post"})
     */
    public function cancelDo(Request $request)
    {

        $user = $this->getUser();
        $em = $this->getDoctrine()->getManager();

        $redirect = 'subscription';
        try {
            $stripe = new \Stripe\StripeClient(
                $this->getParameter('stripe_sk_key')
            );
            $subscription_id = $request->get('subscription_id');
            $subscription_data = $stripe->subscriptions->retrieve($subscription_id);
            $role = $subscription_data->items->data[0]->plan->metadata->ROLE;
            $stripe->subscriptions->cancel( $subscription_data->id, [] );

        } catch(\Stripe\Exception\CardException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute($redirect);
        } catch (\Stripe\Exception\RateLimitException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute($redirect);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute($redirect);
        } catch (\Stripe\Exception\AuthenticationException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute($redirect);
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute($redirect);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute($redirect);
        } catch(Exception $e){
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute($redirect);
        }

        // Firebase user update
        $database = $this->firestore->database();
        $docRef = $database->collection('users')->document( $user->getFirebaseUid() );
        $user_data = $docRef->snapshot()->data();

        $subscription_ids = [];
        $_subscription_ids = $user_data['subscriptions'];
        foreach($_subscription_ids as $_s_id){
            if($subscription_id != $_s_id){
                array_push($subscription_ids, $_s_id);
            }
        }
        $user_data['subscriptions'] = $subscription_ids;
        $docRef->set($user_data);

        // ROLEs
        $roles = $user->getRoles();
        $_roles = [];
        foreach($roles as $r){
            if($r != $role) array_push($_roles, $r);
        }
        $roles = array_unique($_roles);

        $user->setRoles($roles);
        $user->setData( json_encode($user_data) );
        $em->persist($user);
        $em->flush();

        // re-Login 
        $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
        $this->get('security.token_storage')->setToken($token);
        
        $this->addFlash('success', '契約をキャンセルしました');
        return $this->redirectToRoute('subscription');

    }

}
