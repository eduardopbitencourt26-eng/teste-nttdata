<?php

declare(strict_types=1);

namespace Drupal\poll_system\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Psr\Log\LoggerInterface;

final class TokenService
{
  private const STORE = 'poll_system_tokens';
  private const CFG   = 'poll_system.settings';

  public function __construct(
    private readonly KeyValueExpirableFactoryInterface $storeFactory,
    private readonly LoggerInterface $logger,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  private function store()
  {
    return $this->storeFactory->get(self::STORE);
  }

  private function secret(): string
  {
    return (string) ($this->configFactory->get(self::CFG)->get('token_secret') ?? '');
  }

  private function ttl(): int
  {
    $ttl = (int) ($this->configFactory->get(self::CFG)->get('token_ttl') ?? 7200);
    return $ttl > 0 ? $ttl : 7200;
  }

  private function randomToken(): string
  {
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
  }

  private function hmac(string $token): string
  {
    $secret = $this->secret();
    if ($secret === '') {
      throw new \RuntimeException('Token secret is not configured.');
    }
    return hash_hmac('sha256', $token, $secret);
  }

  public function issue(int $uid): array
  {
    $token = $this->randomToken();
    $key   = $this->hmac($token);
    $ttl   = $this->ttl();

    $payload = [
      'uid' => $uid,
      'scopes' => ['poll:read', 'poll:vote'],
      'issued_at' => time(),
    ];
    $this->store()->setWithExpire($key, $payload, $ttl);

    return ['access_token' => $token, 'expires_in' => $ttl];
  }

  public function validate(string $token): ?array
  {
    try {
      $key = $this->hmac($token);
      $data = $this->store()->get($key);
      return is_array($data) ? $data : null;
    } catch (\Throwable $e) {
      $this->logger->error('Token validate error: @m', ['@m' => $e->getMessage()]);
      return null;
    }
  }

  /** Revoga token atual (apaga da store). */
  public function revoke(string $token): void
  {
    $key = $this->hmac($token);
    $this->store()->delete($key);
  }
}
