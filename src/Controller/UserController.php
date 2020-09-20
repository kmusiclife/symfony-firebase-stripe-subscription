<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @Route("/user")
 */
class UserController extends AbstractController
{

    /**
     * @Route("/", name="user_index")
     */
    public function index()
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

        return $this->render('user/index.html.twig', [
            'subscription_data' => $subscription_data,
            'product_data' => $product_data,
            'plan_data' => $plan_data
        ]);
    }
}

/*

*/