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

        $stripe = new \Stripe\StripeClient(
            $this->getParameter('stripe_sk_key')
        );

        $redirect = 'index';
        try {
            $plans = $stripe->plans->all(['limit' => 10]);
            $subscription_lists = array();
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

        foreach($plans as $plan)
        {
            $product = $stripe->products->retrieve($plan->product);
            array_push($subscription_lists, array(
                'plan' => $plan,
                'product' => $product
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

        $em = $this->getDoctrine()->getManager();
        $user = $this->getUser();

        $stripe = new \Stripe\StripeClient(
            $this->getParameter('stripe_sk_key')
        );
        $product_id = $request->get('product_id');
        $plan_id = $request->get('plan_id');
        $customer = $this->getUser()->getData()->customer;
        $subscription = isset($this->getUser()->getData()->subscription) ? $this->getUser()->getData()->subscription : null;

        $redirect = 'subscription';
        try {
            
            $plan = $stripe->plans->retrieve($plan_id);
            $role = isset($plan->metadata->ROLE) ? $plan->metadata->ROLE : null;

            if($subscription){
                
                // https://stripe.com/docs/billing/subscriptions/upgrade-downgrade
                $subscription_data = $stripe->subscriptions->retrieve($subscription);
                $subscription = $stripe->subscriptions->update(
                    $subscription,
                    array(
                        'cancel_at_period_end' => false,
                        'proration_behavior' => 'create_prorations',
                        'proration_date' => time(),
                        'items' => array(
                            array(
                                'id' => $subscription_data->items->data[0]->id,
                                'price' => $plan_id
                            )
                        )
                    )
                );

            } else {

                $subscription = $stripe->subscriptions->create([
                    'customer' => $customer,
                    'items' => [
                        ['price' => $plan_id],
                    ],
                ]);
            }

            // Firebase user update
            $database = $this->firestore->database();
            $docRef = $database->collection('users')->document( $user->getFirebaseUid() );
            $user_data = $docRef->snapshot()->data();
            
            $user_data['product_id'] = $product_id;
            $user_data['plan_id'] = $plan_id;
            $user_data['subscription'] = $subscription->id;
            $docRef->set($user_data);

            // create ROLE
            $roles = array('ROLE_SUBSCRIPTION', 'ROLE_USER');
            if($role) array_push($roles, $role);

            // local db update for user
            $user->setRoles($roles);
            $user->setData( json_encode($user_data) );
            $em->persist($user);
            $em->flush();

            // re-Login 
            $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
            $this->get('security.token_storage')->setToken($token);
            
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
        $this->addFlash('success', '定期購入契約しました');
        return $this->redirectToRoute('subscription');

    }
    /**
     * @Route("/confirm", name="subscription_confirm", methods={"post"})
     */
    public function confirm(Request $request)
    {
        // https://stripe.com/docs/billing/subscriptions/prorations
        $stripe = new \Stripe\StripeClient(
            $this->getParameter('stripe_sk_key')
        );

        $redirect = 'subscription';
        try {
            $product_id = $request->get('product_id');
            $plan_id = $request->get('plan_id');

            $product = $stripe->products->retrieve($product_id);
            $plan = $stripe->plans->retrieve($plan_id);

            // preview
            $subscription = isset($this->getUser()->getData()->subscription) ? $this->getUser()->getData()->subscription : null;
            if($subscription){
                $subscription_data = $stripe->subscriptions->retrieve($subscription);
            } else {
                $subscription_data = null;
            }
            if($subscription_data){
                $items = [
                    [
                        'id' => $subscription_data->items->data[0]->id,
                        'price' => $plan_id,
                    ],
                ];
                $invoice = $stripe->invoices->upcoming([
                    'customer' => $this->getUser()->getData()->customer,
                    'subscription' => $subscription,
                    'subscription_items' => $items,
                    'subscription_proration_date' => time(),
                ]);
            } else {
                $invoice = null;
            }

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

        return $this->render('subscription/confirm.html.twig', [
            'product' => $product,
            'plan' => $plan,
            'invoice' => $invoice
        ]);
    }
    /**
     * @Route("/cancel/do", name="subscription_cancel_do", methods={"post"})
     */
    public function cancelDo(Request $request)
    {
        $stripe = new \Stripe\StripeClient(
            $this->getParameter('stripe_sk_key')
        );

        $redirect = 'subscription';
        try {

            $subscription = $this->getUser()->getData()->subscription;
            $stripe->subscriptions->cancel(
                $subscription, []
            );

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
        $docRef = $database->collection('users')->document( $this->getUser()->getFirebaseUid() );
        $user_data = $docRef->snapshot()->data();
        
        $user_data['product_id'] = '';
        $user_data['plan_id'] = '';
        $user_data['subscription'] = '';
        $docRef->set($user_data);

        // local db update for user
        $this->getUser()->setRoles(array('ROLE_USER'));
        $this->getUser()->setData( json_encode($user_data) );
        
        $em = $this->getDoctrine()->getManager();
        $em->persist($this->getUser());
        $em->flush();

        // re-Login 
        $token = new UsernamePasswordToken($this->getUser(), null, 'main', $this->getUser()->getRoles());
        $this->get('security.token_storage')->setToken($token);
        
        $this->addFlash('success', 'Your changes were saved!');
        return $this->redirectToRoute('subscription');
    }
    /**
     * @Route("/cancel", name="subscription_cancel", methods={"get"})
     */
    public function cancel()
    {
        $stripe = new \Stripe\StripeClient(
            $this->getParameter('stripe_sk_key')
        );

        $subscription_id = isset($this->getUser()->getData()->subscription) ?
            $this->getUser()->getData()->subscription : null;

        if( $subscription_id ){
            $subscription_data = $stripe->subscriptions->retrieve($subscription_id);
            $plan_data = $subscription_data->items->data[0]->plan;
            $product_data = $stripe->products->retrieve($plan_data->product);
        } else {
            $subscription_data = null;
            $plan_data = null;
            $product_data = null;
        }
        
        return $this->render('subscription/cancel.html.twig', [
            'subscription_data' => $subscription_data,
            'product_data' => $product_data,
            'plan_data' => $plan_data
        ]);
    }

}
