<?php

namespace Drupal\analytics\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\UserAuthenticationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\EmailValidator;


class AnalyticsPostController extends ControllerBase {

  protected $requestStack;

  protected $configFactory;

  protected $userAuth;

  protected $emailValidate;

  public function __construct(RequestStack $request_stack, UserAuthenticationInterface $user_auth, EmailValidator $email_validate) {
    $this->requestStack = $request_stack;
    $this->userAuth = $user_auth;
    $this->emailValidate = $email_validate;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('user.auth'),
      $container->get('email.validator'),
    );
  }

  public function addAnalyticsNode() {
    $request = $this->requestStack->getCurrentRequest();
    $contents = $request->toArray();
    $authorization = base64_decode(str_replace('Basic ', '', $request->headers->get('authorization')));
    if ($authorization && str_contains($authorization, ':')) {
      list($mail, $password) = explode(':', $authorization);
      $cond = [];
      if ($this->emailValidate->isValid($mail)){
        $cond['mail'] = $mail;
      } else {
        $cond['name'] = $mail;
      }
      $user = $this->entityTypeManager()->getStorage('user')->loadByProperties($cond);
      $uid = 0;
      if (reset($user)->status->value != 1) {
        $response = new JsonResponse(['message' => 'Authentication failed!.']);
        $response->setStatusCode(403);
        return $response;
      }
      elseif ($user && reset($user)) {
        $uid = $this->userAuth->authenticateAccount(reset($user), $password);
      }
      if (!$uid){
        $response = new JsonResponse(['message' => 'Authorization provided are not valid.']);
        $response->setStatusCode(403);
        return $response;
      }
    }
    else {
      $response = new JsonResponse(['message' => 'No Authorization provided.']);
      $response->setStatusCode(403);
      return $response;
    }
    $response = [];
    if ($contents) {
      foreach ($contents as $key => $content) {
        $node = Node::create(['type' => 'analytics']);
        $node->set('title', $content['carrier_name']);
        $node->set('uid', key($user));

        //attribute
        $attrTerms = $this->entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'analytics_attributes', 'name' => $content['attribute']]);
        if (!$attrTerms) {
          $attrTerm = Term::create(['vid' => 'analytics_attributes']);
          $attrTerm->set('name', $content['attribute']);
          $attrTerm->save();
        }
        else {
          $attrTerm = reset($attrTerms);
        }
        $node->set('field_attribute', $attrTerm->id());

        //carrier
        $carrierTerms = $this->entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'analytics_carrier', 'name' => $content['carrier_name']]);
        if (!$carrierTerms) {
          $carrierTerm = Term::create(['vid' => 'analytics_carrier']);
          $carrierTerm->set('name', $content['carrier_name']);
          $carrierTerm->save();
        }
        else {
          $carrierTerm = reset($carrierTerms);
        }
        $node->set('field_carrier', $carrierTerm->id());

        //end customer
        $customerTerms = $this->entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'analytics_customer', 'name' => $content['customer_name']]);
        if (!$customerTerms) {
          $customerTerm = Term::create(['vid' => 'analytics_customer']);
          $customerTerm->set('name', $content['customer_name']);
          $customerTerm->save();
        }
        else {
          $customerTerm = reset($customerTerms);
        }
        $node->set('field_end_customer', $customerTerm->id());

        // remaining fields
        $node->set('field_api_volume_in_mil', array_sum($content['status_counts']));
        $node->set('field_average_api_latency_in_mil', $content['avg_latency_ms']);
        $node->set('field_date', str_replace(' ', 'T', $content['timestamp_interval']));
        $node->set('field_error_api_volume_in_mil', $content['status_counts']['other_non_200']);
        $node->set('field_success_api_volume_in_mil', $content['status_counts']['200']);
        $node->set('field_404_api_volume_in_mil', $content['status_counts']['404']);
        $node->set('field_transaction_type', $content['transaction_type']);
        $node->set('field_transaction_type_count', $content['transaction_type_count']);
        $node->set('field_est_revenue', $content['est_revenue']);
        $node->set('field_partner',  $this->getGroupId($content['client']));
        $node->set('field_kong_analytical_id', $content['kong_analytical_id']);
        
        $node_exsist = $this->checkAnalyticalId($content['kong_analytical_id']);      
        if($node_exsist) {
          $responses[$key] = ['message' => 'Analytics id: '. $content['kong_analytical_id'] . " already exists."];
        }
        else {
          $node->enforceIsNew();
          $nodeSave = $node->save();
          if (!$nodeSave) {
            $responses[$key] = ['message' => $content['carrier_name'] . " Node creation failed."];
          }
          else {
            $responses[$key] = ['message' => $content['carrier_name'] . " Node created successfully."];
          }
        }
      }
    }
    else {
      $response = new JsonResponse(['message' => 'There is no Json provided to create content.']);
      $response->setStatusCode(202);
      return $response;
    }
    return new JsonResponse($responses);
  }

  
  public function getGroupId($title){
    $query = \Drupal::entityQuery('group')
    ->condition('label', $title)
    ->accessCheck(FALSE)  // Optionally disable access check if needed.
    ->range(0, 1);  // Limit to one result, since title should be unique.
    $group_ids = $query->execute();
    return !empty($group_ids) ? reset($group_ids) : NULL;
  }

  public function checkAnalyticalId($analytical_id){
    // Check if the entity is a node and has the field_kong_analytical_id field.
    if (!empty($analytical_id)) {
      // Query to check if the value already exists in the field.
      $query = \Drupal::entityQuery('node')
        ->condition('field_kong_analytical_id', $analytical_id)
        ->condition('type', 'analytics'); // Optional: restrict to the same content type
      $existing_node = $query->accessCheck()->execute();
      if (!empty($existing_node)) {
         return TRUE;
      }
      else {
        return False;
      }
    } 
  }
}