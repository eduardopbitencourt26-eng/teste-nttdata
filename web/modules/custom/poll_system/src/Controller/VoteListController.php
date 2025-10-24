<?php

declare(strict_types=1);

namespace Drupal\poll_system\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\poll_system\Repository\PollRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class VoteListController extends ControllerBase {

  public function __construct(private readonly PollRepository $repo) {}

  public static function create(ContainerInterface $c): static {
    return new static($c->get('poll_system.repository'));
  }

  public function list(): array {
    $items = [];
    foreach ($this->repo->listActiveQuestions() as $q) {
      $url = Url::fromRoute('poll_system.vote_ui', ['poll_question' => $q['id']]);
      $items[] = Link::fromTextAndUrl($q['title'], $url)->toRenderable();
    }

    return [
      '#theme' => 'item_list',
      '#title' => $this->t('Polls'),
      '#items' => $items ?: [$this->t('No polls available.')],
    ];
  }
}
