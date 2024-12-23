<?php

namespace Drupal\zcs_kong\Services;

use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Serialization\Json;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Drupal\group\Entity\Group;
use Drupal\node\Entity\Node;
use Drupal\group\Entity\GroupContent;
use Drupal\group\Entity\GroupRelationship;
use Drupal\group\Entity\GroupContentType;
use Drupal\group\Entity\GroupMembership;


/**
 * Provides an kong service
 */

class kongService  {



  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;  

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

   /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Constructs a ConfigExistsConstraintValidator object.
   * 
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *  The current user.
   * 
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
 
  public function __construct(AccountInterface $currentUser, ClientInterface $http_client) {
    $this->currentUser = $currentUser;
    $this->httpClient = $http_client;
  }

   /**
   * {@inheritdoc}
   */

  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('current_user'),
        $container->get('http_client')
    );
  }




  /**
   * {@inheritdoc}
   */
  public function createConsumer($username, $customer_id) {

    $endpoint_url = \Drupal::config('zcs_custom.settings')->get('kong_endpoint');
    $endpoint = $endpoint_url .'/consumers';

    $body = [
      'username' => $username,
      'custom_id' => $customer_id,
    ];

    $request_body = json::encode($body);
    $response = $this->httpClient->request('POST', $endpoint, [
      'headers' => [
       'content-type' => 'application/json',
      ],
      'verify' => FALSE,
      'body' => $request_body,
    ]);
    return $response;
  }

   /**
   * {@inheritdoc}
   */
  public function getTagsList() {
    $endpoint_url = \Drupal::config('zcs_custom.settings')->get('kong_endpoint');
    $endpoint = $endpoint_url . '/tags';
    $response = $this->httpClient->request('GET', $endpoint, [
      'headers' => [
       'content-type' => 'application/json',
      ],
      'verify' => FALSE,
    ]);
    return $response;
  }

   /**
   * {@inheritdoc}
   */
  public function checkUserAccessGeneratekey() {
    if (!\Drupal::currentUser()->hasRole('client_admin')) {
      //\Drupal::messenger()->addError('The user has no access to create APP');
      return FALSE;
    }
    else {
      $memberships = \Drupal::service('group.membership_loader')->loadByUser(\Drupal::currentUser());
      if (isset($memberships)) {
        $roles = $memberships[0]->getRoles();
        $group_roles = [];
        foreach($roles as $role) {
          $group_roles[] = $role->id();
        }
        if (in_array('partner-admin', $group_roles)) {
          $group_id = $memberships[0]->getGroup()->id();
          $group = Group::load($group_id);
          if ($group) {
             return $group->get('field_consumer_id')->getValue()[0]['value'];     
          }
          else {
           // \Drupal::messenger()->addError('Problem in fetching the client details');
            return "error";
          }
        }
        else {
         // \Drupal::messenger()->addError('You dont have admin access to create App');
          return "error";
        }
      }
      else {
       // \Drupal::messenger()->addError('Your are not part of client');
        return "error";
      }    
    }
  }

   /**
   * {@inheritdoc}
   */
  public function generateKey($consumer_id, $tags, $ttl) {

    $endpoint_url = \Drupal::config('zcs_custom.settings')->get('kong_endpoint');
    $endpoint = $endpoint_url .'/consumers/'. $consumer_id .'/key-auth';

    if ($ttl == 'never_expires') {
      $ttl = '';
    } 
    try {
      $body = [
        'consumer' => [
          'id' => $consumer_id,
        ],
        "created_at" => time(),
        "tags" => [$tags],
        "ttl" => (int) $ttl,
      ];
      $request_body = json::encode($body);
      $response = $this->httpClient->request('POST', $endpoint, [
        'headers' => [
        'content-type' => 'application/json',
        ],
        'verify' => FALSE,
        'body' => $request_body,
      ]);
      return $response;
    }  
    catch (\Exception $e) {
      \Drupal::messenger()->addError(('Request Error: ' . $e->getMessage())); 
      return "error";
    }
  }

  /**
  * {@inheritdoc}
  */
  public function getAppList($consumer_id) {
    $endpoint_url = \Drupal::config('zcs_custom.settings')->get('kong_endpoint');
    $endpoint = $endpoint_url .'/consumers/'. $consumer_id .'/key-auth';
    try {
      $response = $this->httpClient->request('GET', $endpoint, [
        'headers' => [
        'content-type' => 'application/json',
        ],
        'verify' => FALSE,
      ]);
      return $response;
    }
    catch (\Exception $e) {
      // Get the error message and error code
      $error_message = $e->getMessage();
      $error_code = $e->getCode();
      if ($error_code == '404') {
        \Drupal::messenger()->addError('No API keys available or problem in fetching Details');
      }
      else {
        \Drupal::messenger()->addError('Something Went wrong contact site adminstrator.');
      }    
      return "error";
    }
  }

   /**
  * {@inheritdoc}
  */
  public function getConsumerId() {
    $memberships = \Drupal::service('group.membership_loader')->loadByUser(\Drupal::currentUser());
    if (isset($memberships)) {
      $group_id = $memberships[0]->getGroup()->id();
      $group = Group::load($group_id);
      if ($group) {
        return $group;     
      }
      else {
        \Drupal::messenger()->addError('Problem in fetching the client id');
        return FALSE;
      }
    }
    else {
      \Drupal::messenger()->addError('Your are not part of client');
      return FALSE;
    }
  }



  /**
  * {@inheritdoc}
  */
  public function getApp($consumer_id, $app_id) {

    $endpoint_url = \Drupal::config('zcs_custom.settings')->get('kong_endpoint');
    $endpoint = $endpoint_url .'/consumers/'. $consumer_id .'/key-auth/'.$app_id;

    try {
      $response = $this->httpClient->request('GET', $endpoint, [
        'headers' => [
        'content-type' => 'application/json',
        ],
        'verify' => FALSE,
      ]);
      return $response;
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError(('Request Error: ' . $e->getMessage())); 
      return 0;
    }
  }




  /**
  * {@inheritdoc}
  */
  public function updateApp($consumer_id, $app_id, $ttl, $tag, $app_key) {

    $endpoint_url = \Drupal::config('zcs_custom.settings')->get('kong_endpoint');
    $endpoint = $endpoint_url .'/consumers/'. $consumer_id .'/key-auth/'. $app_id;
    
    try {
      $body = [
        "ttl" => (int) $ttl,
        "tags" => [$tag],
        "key" =>  $app_key,  
      ];
      $request_body = json::encode($body);
      $response = $this->httpClient->request('PUT', $endpoint, [
        'headers' => [
        'content-type' => 'application/json',
        ],
        'verify' => FALSE,
        'body' => $request_body,
      ]);
      return $response;
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError(('Request Error: ' . $e->getMessage())); 
      return 0;
    }
  }



  public function deleteApp($consumer_id, $app_id) {

    $endpoint_url = \Drupal::config('zcs_custom.settings')->get('kong_endpoint');
    $endpoint = $endpoint_url .'/consumers/'.$consumer_id .'/key-auth/'.$app_id;
    try {
      $response = $this->httpClient->request('DELETE', $endpoint, [
        'headers' => [
        'content-type' => 'application/json',
        ],
        'verify' => FALSE,
      ]);
      return $response;
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError(('Request Error: ' . $e->getMessage())); 
      return 0;
    }
  }


     /**
   * {@inheritdoc}
   */
  public function saveApp($client_id, $ttl, $response) {
    $group = $this->getGroupDetails($client_id);
    $app = Json::decode($response); 
    if ($ttl!= 'never_expires') {
      $expiry_time = $ttl + time();
    }

  
    // Create a new node object.
    $node = Node::create([
      'type' => 'app', // Replace with your content type machine name.
      'title' => $group->label(),
      'status' => 1, // 1 = Published, 0 = Unpublished.
      'field_app_id' => $app['id'],
      'field_app_key' => $app['key'],
      'field_tag' => $app['tags'],
      'field_consumer_id' => $client_id,
      'field_ttl' => $ttl,
      'field_app_status' => 'active',
      'field_expiry_date' => $expiry_time ?? '',
      'field_renewal_date' => '',  
      'uid' => \Drupal::currentUser()->id(),
      'created' => time(), // Node creation timestamp.
    ]);
    $node->save();


    $group->addRelationship($node, 'group_node:app');
    $group->save();

    return TRUE;
  }


  /**
   * Get the group details
   */

   public function getGroupDetails($client_id) {
      $query = \Drupal::entityQuery('group')
        ->condition('field_consumer_id', $client_id, '=');
      $group_id = $query->accessCheck()->execute();
      $group = Group::load(reset($group_id));
      return $group;
   }


  
     /**
   * {@inheritdoc}
   */
  public function updateAppNode($app_node_id, $ttl, $response) {
    $app = Json::decode($response); 
    $node = Node::load($app_node_id);
    if ($ttl!= 'never_expires') {
      $expiry_time = $ttl + time();
    }
    else {
      $expiry_time = '';
    }
    $node->set('field_app_key', $app['key']);
    $node->set('field_tag', $app['tags']);
    $node->set('field_ttl', $ttl);
    $node->set('field_expiry_date', $expiry_time);
    $node->set('field_renewal_date', time());
    $node->save();
    return TRUE;
  } 

}
