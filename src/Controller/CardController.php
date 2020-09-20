<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use App\Form\CardFormType;
use App\Entity\Card;
use Kreait\Firebase\Firestore;

/**
 * @Route("/user/card")
 */
class CardController extends AbstractController
{

    private $firestore;

    public function __construct(SessionInterface $session, Firestore $firestore)
    {
        $this->firestore = $firestore;
    }

    /**
     * @Route("/", name="user_card")
     */
    public function card(Request $request)
    {

        $stripeSource = $request->get('stripeSource');

        $database = $this->firestore->database();
        $docRef = $database->collection('users')->document( $this->getUser()->getFirebaseUid() );
        $fire_data = $docRef->snapshot()->data();

        $card = new Card();
        if($fire_data){
            $card->setNameSei( $fire_data['name_sei'] );
            $card->setNameMei( $fire_data['name_mei'] );
            $card->setZip( $fire_data['zip'] );
            $card->setTel( $fire_data['tel'] );
            $card->setPref( $fire_data['pref'] );
            $card->setAddr1( $fire_data['addr1'] );
            $card->setAddr2( $fire_data['addr2'] );
            $card->setAddr3( $fire_data['addr3'] );
        }
        $card->setEmail( $this->getUser()->getUser()->email );
        $form = $this->createForm(CardFormType::class, $card);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            // Start Stripe Process
            $stripe = new \Stripe\StripeClient(
                $this->getParameter('stripe_sk_key')
            );
            try{
                
                // source and customer loading
                $customer = isset($this->getUser()->getData()->customer) ? $this->getUser()->getData()->customer : null;
                $source = $stripeSource ? $stripeSource : ( isset($this->getUser()->getData()->source) ? $this->getUser()->getData()->source : null );
                
                // create customer structure
                $customer_hash = array(
                    'source' => $source,
                    'email' => $this->getUser()->getUser()->email,
                    'description' => $card->getName().': Update information',
                    'name' => $card->getName(),
                    'phone' => $card->getTel(),
                    'address' => array(
                        'postal_code' => $card->getZip(),
                        'state' => $card->getPref(),
                        'line1' => $card->getAddr(),
                    )
                );
                // customer create or update
                if($customer){
                    $customer_obj = $stripe->customers->update(
                        $customer, $customer_hash
                    );
                } else {
                    $customer_obj = $stripe->customers->create($customer_hash);
                }
                $customer = $customer_obj->id;

                // Firebase update
                $docRef = $database->collection('users')->document( $this->getUser()->getFirebaseUid() );
                $user_data = $docRef->snapshot()->data();
                $user_data['source'] = $source;
                $user_data['customer'] = $customer;
                $user_data['name_sei'] = $card->getNameSei();
                $user_data['name_mei'] = $card->getNameMei();
                $user_data['zip'] = $card->getZip();
                $user_data['pref'] = $card->getPref();
                $user_data['addr1'] = $card->getAddr1();
                $user_data['addr2'] = $card->getAddr2();
                $user_data['addr3'] = $card->getAddr3();
                $user_data['tel'] = $card->getTel();
                $docRef->set($user_data);

                // User information update
                $em = $this->getDoctrine()->getManager();
                $this->getUser()->setRoles(array('ROLE_CARD'));
                $em->persist( $this->getUser()->setData(json_encode($user_data)) );
                $em->flush();

                // re-Login 
                $token = new UsernamePasswordToken($this->getUser(), null, 'main', $this->getUser()->getRoles());
                $this->get('security.token_storage')->setToken($token);

            } catch(\Stripe\Exception\CardException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('user_card');

            } catch (\Stripe\Exception\RateLimitException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('user_card');

            } catch (\Stripe\Exception\InvalidRequestException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('user_card');

            } catch (\Stripe\Exception\AuthenticationException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('user_card');

            } catch (\Stripe\Exception\ApiConnectionException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('user_card');

            } catch (\Stripe\Exception\ApiErrorException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('user_card');

            } catch(Exception $e){
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('user_card');

            }

            $this->addFlash('success', 'ユーザ情報・カード登録を登録しました');
            return $this->redirectToRoute('user_card');


        } // end submit form

        $stripe = new \Stripe\StripeClient(
            $this->getParameter('stripe_sk_key')
        );
        $source = isset($this->getUser()->getData()->source) ? $this->getUser()->getData()->source : null;
        $card_info = null;
        if( $source ){

            try{
                $source_data = $stripe->sources->retrieve(
                    $source,[]
                );
            } catch(Exception $e){
                $this->session->set('error_message', 'Stripe API Source retrieve Error');
                return $this->redirectToRoute('page_error');
            }
            if($source_data){
                $card_info = array(
                    'last4' => $source_data->card->last4, 
                    'exp_year' => $source_data->card->exp_year,
                    'exp_month' => $source_data->card->exp_month,
                    'brand' => $source_data->card->brand
                );
            }
        }

        return $this->render('card/index.html.twig', [
            'stripe_pk_key' => $this->getParameter('stripe_pk_key'),
            'stripe_sk_key' => $this->getParameter('stripe_sk_key'),
            'card_info' => $card_info,
            'form' => $form->createView(),
        ]);
    }

}
