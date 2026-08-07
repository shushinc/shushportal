<?php

namespace Drupal\sam_ldap\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\sam\SsoAppInterface;
use Drupal\sam_ldap\Service\LdapConnectionService;

/**
 * Service for authenticating users against LDAP.
 */
final class LdapAuthenticationService {

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The LDAP connection service.
   *
   * @var \Drupal\sam_ldap\Service\LdapConnectionService
   */
  protected LdapConnectionService $connectionService;

  /**
   * Constructs the LDAP authentication service.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\sam_ldap\Service\LdapConnectionService $connection_service
   *   The LDAP connection service.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    LdapConnectionService $connection_service
  ) {
    $this->loggerFactory = $logger_factory;
    $this->connectionService = $connection_service;
  }

  /**
   * Authenticates a user against LDAP.
   *
   * @param string $email
   *   The user's email address.
   * @param string $password
   *   The user's password.
   * @param \Drupal\sam\SsoAppInterface $app
   *   The SSO app configuration.
   *
   * @return array
   *   An array containing user identity data with keys:
   *   - email: The user's email address
   *   - username: The user's username
   *   - display_name: The user's display name
   *
   * @throws \RuntimeException
   *   Thrown when authentication fails.
   */
  public function authenticate(string $email, string $password, SsoAppInterface $app): array {
    $settings = $app->getSettings();

    $configuration = [
      'host' => $settings['host'] ?? '',
      'port' => $settings['port'] ?? 389,
      'encryption' => $settings['encryption'] ?? 'none',
      'base_dn' => $settings['base_dn'] ?? '',
      'bind_dn' => $settings['bind_dn'] ?? '',
      'bind_password_key' => $settings['bind_password_key'] ?? '',
    ];

    // Connect and bind with service account.
    $connection = $this->connectionService->connect($configuration);

    try {
      $this->connectionService->bindServiceAccount(
        $connection,
        $configuration['bind_dn'],
        $configuration['bind_password_key']
      );

      // Search for the user.
      $user_dn = $this->searchUser($connection, $email, $configuration);

      if ($user_dn === NULL) {
        throw new \RuntimeException('User not found in LDAP directory.');
      }

      // Attempt to bind as the user to verify password.
      $this->bindUser($connection, $user_dn, $password);

      // Retrieve user attributes.
      $attributes = $this->getUserAttributes($connection, $user_dn, $configuration);

      $this->loggerFactory->get('sam_ldap')->info('Successfully authenticated user @email via LDAP.', [
        '@email' => $email,
      ]);

      return $attributes;
    }
    finally {
      @ldap_unbind($connection);
    }
  }

  /**
   * Searches for a user in the LDAP directory.
   *
   * @param \LDAP\Connection $connection
   *   The LDAP connection.
   * @param string $email
   *   The user's email address.
   * @param array $settings
   *   The LDAP settings.
   *
   * @return string|null
   *   The user's Distinguished Name, or NULL if not found.
   *
   * @throws \RuntimeException
   *   Thrown when the search fails.
   */
  private function searchUser(\LDAP\Connection $connection, string $email, array $settings): ?string {
    $base_dn = trim($settings['base_dn'] ?? '');
    $search_filter = trim($settings['search_filter'] ?? '(mail={email})');

    if ($base_dn === '') {
      throw new \RuntimeException('Base DN is not configured.');
    }

    if ($search_filter === '') {
      throw new \RuntimeException('Search filter is not configured.');
    }

    // Replace {email} placeholder.
    $filter = str_replace('{email}', ldap_escape($email, '', LDAP_ESCAPE_FILTER), $search_filter);

    $result = @ldap_search($connection, $base_dn, $filter, ['dn']);

    if ($result === FALSE) {
      $error = ldap_error($connection);
      throw new \RuntimeException(sprintf('LDAP search failed: %s', $error));
    }

    $entries = @ldap_get_entries($connection, $result);

    if ($entries === FALSE || $entries['count'] === 0) {
      return NULL;
    }

    return $entries[0]['dn'] ?? NULL;
  }

  /**
   * Binds to LDAP as a specific user to verify their password.
   *
   * @param \LDAP\Connection $connection
   *   The LDAP connection.
   * @param string $user_dn
   *   The user's Distinguished Name.
   * @param string $password
   *   The user's password.
   *
   * @throws \RuntimeException
   *   Thrown when the bind fails.
   */
  private function bindUser(\LDAP\Connection $connection, string $user_dn, string $password): void {
    if ($password === '') {
      throw new \RuntimeException('Password cannot be empty.');
    }

    $result = @ldap_bind($connection, $user_dn, $password);

    if ($result === FALSE) {
      throw new \RuntimeException('Invalid credentials.');
    }
  }

  /**
   * Retrieves user attributes from LDAP.
   *
   * @param \LDAP\Connection $connection
   *   The LDAP connection.
   * @param string $user_dn
   *   The user's Distinguished Name.
   * @param array $settings
   *   The LDAP settings.
   *
   * @return array
   *   An array containing user identity data.
   *
   * @throws \RuntimeException
   *   Thrown when attributes cannot be retrieved.
   */
  private function getUserAttributes(\LDAP\Connection $connection, string $user_dn, array $settings): array {
    $email_attr = trim($settings['email_attribute'] ?? 'mail');
    $username_attr = trim($settings['username_attribute'] ?? 'uid');
    $display_name_attr = trim($settings['display_name_attribute'] ?? 'cn');

    $attributes = [$email_attr, $username_attr, $display_name_attr];

    $result = @ldap_read($connection, $user_dn, '(objectClass=*)', $attributes);

    if ($result === FALSE) {
      $error = ldap_error($connection);
      throw new \RuntimeException(sprintf('Failed to read user attributes: %s', $error));
    }

    $entries = @ldap_get_entries($connection, $result);

    if ($entries === FALSE || $entries['count'] === 0) {
      throw new \RuntimeException('User entry not found.');
    }

    $entry = $entries[0];

    return [
      'email' => $this->extractAttribute($entry, $email_attr),
      'username' => $this->extractAttribute($entry, $username_attr),
      'display_name' => $this->extractAttribute($entry, $display_name_attr),
    ];
  }

  /**
   * Extracts a single attribute value from an LDAP entry.
   *
   * @param array $entry
   *   The LDAP entry.
   * @param string $attribute
   *   The attribute name.
   *
   * @return string
   *   The attribute value, or empty string if not found.
   */
  private function extractAttribute(array $entry, string $attribute): string {
    $attribute_lower = strtolower($attribute);

    if (!isset($entry[$attribute_lower][0])) {
      return '';
    }

    return (string) $entry[$attribute_lower][0];
  }

}
