<?php

declare(strict_types=1);

namespace Drupal\poll_system\Service;

use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

final class ApiTokenService
{

  private const COLLECTION = 'poll_system.tokens';
  private const KEY_PREFIX = 't:'; // evita colisão com outras chaves

  public function __construct(
    private readonly KeyValueExpirableFactoryInterface $kv,
    private readonly LoggerInterface $logger,
  ) {}

  private function store()
  {
    return $this->kv->get(self::COLLECTION);
  }

  private function makeKey(string $token): string
  {
    return self::KEY_PREFIX . hash('sha256', $token);
  }

  /** Gera token aleatório base64url (sem =) */
  private function generateToken(): string
  {
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
  }

  /**
   * Emite um token para um uid.
   * @return array{token:string, expires_in:int}
   */
  public function issueForUid(int $uid, int $ttl = 3600, array $scopes = ['poll:read', 'poll:vote']): array
  {
    $token = $this->generateToken();
    $key = $this->makeKey($token);
    $payload = [
      'uid' => $uid,
      'scopes' => $scopes,
      'created' => time(),
    ];

    $this->store()->set($key, $payload, time() + $ttl);
    return ['token' => $token, 'expires_in' => $ttl];
  }

  /** Extrai o bearer do header Authorization */
  public function getBearerFromRequest(Request $request): ?string
  {
    $hdr = (string) $request->headers->get('Authorization', '');
    if (str_starts_with($hdr, 'Bearer ')) {
      $token = trim(substr($hdr, 7));
      return $token !== '' ? $token : null;
    }
    return null;
  }

  public function validateToken(string $token, ?string $requiredScope = null): ?array
  {
    $key = $this->makeKey($token);
    $data = $this->store()->get($key);
    if (!$data || !is_array($data) || !isset($data['uid'])) {
      return null;
    }
    if ($requiredScope && !in_array($requiredScope, $data['scopes'] ?? [], true)) {
      return null;
    }
    return [
      'uid' => (int) $data['uid'],
      'scopes' => (array) ($data['scopes'] ?? []),
      'created' => (int) ($data['created'] ?? 0),
    ];
  }

  /** Revoga token atual (logout) */
  public function revoke(string $token): void
  {
    $this->store()->delete($this->makeKey($token));
  }
}
