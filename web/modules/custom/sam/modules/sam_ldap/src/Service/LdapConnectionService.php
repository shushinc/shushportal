<?php

namespace Drupal\sam_ldap\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\key\KeyRepositoryInterface;

/**
 * Service for managing LDAP connections.
 */
final class LdapConnectionService {

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The Drupal Key repository service.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected KeyRepositoryInterface $keyRepository;

  /**
   * Constructs the LDAP connection service.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   The Drupal Key repository.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    KeyRepositoryInterface $key_repository
  ) {
    $this->loggerFactory = $logger_factory;
    $this->keyRepository = $key_repository;
  }

  /**
   * Establishes an LDAP connection.
   *
   * @param array $configuration
   *   The LDAP configuration array containing:
   *   - host: The LDAP server hostname or IP.
   *   - port: The LDAP server port.
   *   - encryption: The encryption method (none, starttls, ldaps).
   *
   * @return \LDAP\Connection
   *   The LDAP connection resource.
   *
   * @throws \RuntimeException
   *   Thrown when the connection cannot be established.
   */
  public function connect(array $configuration): \LDAP\Connection {
    if (!extension_loaded('ldap')) {
      throw new \RuntimeException('PHP LDAP extension is not installed.');
    }

    $host = trim($configuration['host'] ?? '');
    $port = (int) ($configuration['port'] ?? 389);
    $encryption = strtolower(trim($configuration['encryption'] ?? 'none'));

    if ($host === '') {
      throw new \RuntimeException('LDAP host is required.');
    }

    if ($port < 1 || $port > 65535) {
      throw new \RuntimeException('LDAP port must be between 1 and 65535.');
    }

    if (!in_array($encryption, ['none', 'starttls', 'ldaps'], TRUE)) {
      throw new \RuntimeException('Invalid encryption method.');
    }

    // Build the LDAP URI.
    $protocol = $encryption === 'ldaps' ? 'ldaps' : 'ldap';
    $uri = sprintf('%s://%s:%d', $protocol, $host, $port);

    $this->loggerFactory->get('sam_ldap')->info('Attempting LDAP connection to @uri', [
      '@uri' => $uri,
    ]);

    $connection = @ldap_connect($uri);

    if ($connection === FALSE) {
      throw new \RuntimeException(sprintf('Unable to connect to LDAP server at %s', $uri));
    }

    // Set protocol version to 3.
    if (!@ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3)) {
      throw new \RuntimeException('Unable to set LDAP protocol version to 3.');
    }

    // Disable referrals.
    if (!@ldap_set_option($connection, LDAP_OPT_REFERRALS, 0)) {
      throw new \RuntimeException('Unable to disable LDAP referrals.');
    }

    // Handle STARTTLS.
    if ($encryption === 'starttls') {
      if (!@ldap_start_tls($connection)) {
        $error = ldap_error($connection);
        throw new \RuntimeException(sprintf('STARTTLS failed: %s', $error));
      }
    }

    return $connection;
  }

  /**
   * Binds to the LDAP server using service account credentials.
   *
   * @param \LDAP\Connection $connection
   *   The LDAP connection.
   * @param string $bind_dn
   *   The service account DN.
   * @param string $bind_password_key_id
   *   The Drupal Key entity ID containing the bind password.
   *
   * @throws \RuntimeException
   *   Thrown when the bind operation fails.
   */
  public function bindServiceAccount(
    \LDAP\Connection $connection,
    string $bind_dn,
    string $bind_password_key_id
  ): void {
    $bind_dn = trim($bind_dn);
    $bind_password_key_id = trim($bind_password_key_id);

    if ($bind_dn === '') {
      throw new \RuntimeException('Bind DN is required.');
    }

    if ($bind_password_key_id === '') {
      throw new \RuntimeException('Bind password Key ID is required.');
    }

    // Load the Drupal Key.
    $key = $this->keyRepository->getKey($bind_password_key_id);

    if ($key === NULL) {
      throw new \RuntimeException(sprintf(
        'Drupal Key "%s" was not found.',
        $bind_password_key_id
      ));
    }

    $password = $key->getKeyValue();

    if (!is_string($password) || $password === '') {
      throw new \RuntimeException(sprintf(
        'Drupal Key "%s" is empty or invalid.',
        $bind_password_key_id
      ));
    }

    // Attempt to bind.
    $result = @ldap_bind($connection, $bind_dn, $password);

    if ($result === FALSE) {
      $error = ldap_error($connection);
      throw new \RuntimeException(sprintf('LDAP bind failed: %s', $error));
    }

    $this->loggerFactory->get('sam_ldap')->info('Successfully bound to LDAP server with service account.');
  }

  /**
   * Tests the LDAP connection and service account credentials.
   *
   * @param array $configuration
   *   The complete LDAP configuration array.
   *
   * @throws \RuntimeException
   *   Thrown when the connection test fails.
   */
  public function testConnection(array $configuration): void {
    $connection = $this->connect($configuration);

    try {
      $this->bindServiceAccount(
        $connection,
        $configuration['bind_dn'] ?? '',
        $configuration['bind_password_key'] ?? ''
      );

      // Attempt to read the base DN.
      $base_dn = trim($configuration['base_dn'] ?? '');

      if ($base_dn === '') {
        throw new \RuntimeException('Base DN is required.');
      }

      $result = @ldap_read($connection, $base_dn, '(objectClass=*)', ['dn']);

      if ($result === FALSE) {
        $error = ldap_error($connection);
        throw new \RuntimeException(sprintf('Unable to read Base DN: %s', $error));
      }

      $this->loggerFactory->get('sam_ldap')->info('LDAP connection test successful.');
    }
    finally {
      @ldap_unbind($connection);
    }
  }

}
