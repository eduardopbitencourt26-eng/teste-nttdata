<?php

declare(strict_types=1);

namespace Drupal\poll_system\Service;

use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;

final class RateLimiter
{

  public function __construct(
    private readonly KeyValueExpirableFactoryInterface $kv,
    private readonly TimeInterface $time,
  ) {}

  private function bin()
  {
    return $this->kv->get('poll_system.ratelimit');
  }

  public function allow(string $key, int $max, int $windowSeg): bool
  {
    $now = $this->time->getRequestTime();
    $bucketKey = $key . ':' . (int) floor($now / $windowSeg);

    $current = (int) ($this->bin()->get($bucketKey) ?? 0);
    if ($current >= $max) {
      return false;
    }
    $this->bin()->set($bucketKey, $current + 1, $this->time->getRequestTime() + $windowSeg);
    return true;
  }
}
