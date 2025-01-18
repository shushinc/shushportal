<?php

namespace Drupal\zcs_aws\Services;

use Aws\ApiGateway\ApiGatewayClient;
use Aws\Exception\AwsException;
use Aws\Credentials\Credentials;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\Group;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;



/**
 * Provides an AWS SDK  service
 */

class AwsHelperService  {



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
   * Constructs a ConfigExistsConstraintValidator object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   */

  public function __construct(AccountInterface $currentUser) {
   // $this->configFactory = $config_factory;
    $this->currentUser = $currentUser;
  }

   /**
   * {@inheritdoc}
   */

  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('current_user'),
    );
  }


  public function deleteApp($client_id) {

    $region = \Drupal::config('zcs_custom.settings')->get('aws_region');
    $aws_key =  \Drupal::config('zcs_custom.settings')->get('aws_access_key');
    $aws_secret_key =  \Drupal::config('zcs_custom.settings')->get('aws_secret_key');
    $user_pool_id =  \Drupal::config('zcs_custom.settings')->get('user_pool_id');

    $config = [
      'region'  => $region,// e.g., 'us-east-1'
      'version' =>  null,
      'credentials' => [
        'key'    => $aws_key,
        'secret' => $aws_secret_key,
      ],
    ];


    $client = new CognitoIdentityProviderClient($config);
    $deleteresponse = $client->deleteUserPoolClient([
      'ClientId' => $client_id,
      'UserPoolId' => $user_pool_id,
    ]);
    return $deleteresponse;
  }
  /**
   * {@inheritdoc}
   */
  public function saveApp($group_id, $response) {

    $aws_app_response = $response->toArray();
    $group = Group::load($group_id);
    // Create a new node object.
    $node = Node::create([
      'type' => 'app', // Replace with your content type machine name.
      'title' => $group->label(),
      'status' => 1, // 1 = Published, 0 = Unpublished.
      'field_tag' => $aws_app_response['UserPoolClient']['ClientName'],
      'field_client_id' => $aws_app_response['UserPoolClient']['ClientId'],
      'field_client_secret' => $aws_app_response['UserPoolClient']['ClientSecret'],
      'field_consumer_id' => '',
      'field_app_status' => 'active',
      'field_gateway' => 'aws',
      'uid' => \Drupal::currentUser()->id(),
      'created' => time(), // Node creation timestamp.
    ]);
    $node->save();
    //dump($node);die;
    $group->addRelationship($node, 'group_node:app');
    $group->save();
    return TRUE;
  }


  /**
   * {@inheritdoc}
   */
  public function updateAwsAppClient($app_name, $client_id) {
    $region = \Drupal::config('zcs_custom.settings')->get('aws_region');
    $aws_key =  \Drupal::config('zcs_custom.settings')->get('aws_access_key');
    $aws_secret_key =  \Drupal::config('zcs_custom.settings')->get('aws_secret_key');
    $user_pool_id =  \Drupal::config('zcs_custom.settings')->get('user_pool_id');

    $config = [
      'region'  => $region,// e.g., 'us-east-1'
      'version' =>  null,
      'credentials' => [
          'key'    => $aws_key,
          'secret' => $aws_secret_key,
      ],
    ];
    $client = new CognitoIdentityProviderClient($config);
    $updateClients = $client->updateUserPoolClient([
      'UserPoolId' => $user_pool_id,
      'ClientName' => $app_name,
      'ClientId' => $client_id, // REQUIRED
    ]);
    return $updateClients;
  }



  public function createAwsAppClient($app_name) {
    $region = \Drupal::config('zcs_custom.settings')->get('aws_region');
    $aws_key =  \Drupal::config('zcs_custom.settings')->get('aws_access_key');
    $aws_secret_key =  \Drupal::config('zcs_custom.settings')->get('aws_secret_key');
    $user_pool_id =  \Drupal::config('zcs_custom.settings')->get('user_pool_id');

    $OAuth_flows =  \Drupal::config('zcs_custom.settings')->get('allowed_oauth_flows');
    $OAuth_scopes =  \Drupal::config('zcs_custom.settings')->get('allowed_oauth_scopes');
    $supported_idps =  \Drupal::config('zcs_custom.settings')->get('supported_identity_providers');
    $OAuth_flows_formatted = array_map('trim', explode(',',$OAuth_flows));
    $OAuth_scopes_formatted = array_map('trim', explode(',',$OAuth_scopes));

    $config = [
      'region'  => $region,// e.g., 'us-east-1'
      'version' =>  null,
      'credentials' => [
        'key'    => $aws_key,
        'secret' => $aws_secret_key,
      ],
    ];
    $client = new CognitoIdentityProviderClient($config);
    $createClients = $client->createUserPoolClient([
      'UserPoolId' => $user_pool_id,
      'ClientName' => $app_name,
      'GenerateSecret' => true,
      'AllowedOAuthFlows' =>  $OAuth_flows_formatted,
      'AllowedOAuthScopes' => $OAuth_scopes_formatted,
      'SupportedIdentityProviders' => [$supported_idps],
      'CallbackURLs' => ['https://your-app/callback'],
      'LogoutURLs' => ['https://your-app/logout'],
    ]);
    return $createClients;
  }


  /**
   * {@inheritdoc}
   */
  public function checkUserAccessGeneratekey() {
    if (!\Drupal::currentUser()->hasRole('client_admin')) {
      //\Drupal::messenger()->addError('The user has no access to create APP');
      return 'error';
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
            return TRUE;
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

}
