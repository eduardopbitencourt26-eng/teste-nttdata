<?php

declare(strict_types=1);

namespace Drupal\poll_system\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\poll_system\Entity\Question;
use Drupal\poll_system\Repository\PollRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

class OptionAdminController extends ControllerBase
{

  public function __construct(protected PollRepository $repo) {}

  public static function create(ContainerInterface $container): static
  {
    return new static($container->get('poll_system.repository'));
  }

  public function list(Question $poll_question)
  {
    $build['title'] = [
      '#markup' => '<h2>' . $this->t('Options for: @t', ['@t' => $poll_question->label()]) . '</h2>',
    ];

    $add_link = Link::fromTextAndUrl(
      $this->t('Add option'),
      Url::fromRoute('entity.poll_option.add_form', ['poll_question' => $poll_question->id()])
    )->toRenderable();
    $add_link['#attributes']['class'] = ['button', 'button--primary'];

    $build['add'] = $add_link;
    $build['table'] = $this->repo->optionsAdminTable($poll_question);

    return $build;
  }

  public function overview()
  {
    // paginação
    $limit = 5;

    $header = [
      $this->t('ID'),
      $this->t('Title'),
      $this->t('Options'),
      $this->t('Operations'),
    ];

    $storage_q = $this->entityTypeManager()->getStorage('poll_question');
    $ids = $storage_q->getQuery()->accessCheck(FALSE)->pager($limit)->execute();
    $rows = [];

    if ($ids) {
      /** @var \Drupal\poll_system\Entity\Question[] $questions */
      $questions = $storage_q->loadMultiple($ids);
      // Ordem por id
      ksort($questions);

      foreach ($questions as $q) {
        $count = $this->entityTypeManager()->getStorage('poll_option')
          ->getQuery()
          ->accessCheck(FALSE)
          ->condition('question', (int) $q->id())
          ->count()
          ->execute();

        $rows[] = [
          (int) $q->id(),
          $q->label(),
          (int) $count,
          [
            'data' => [
              '#type' => 'operations',
              '#links' => [
                'manage' => [
                  'title' => $this->t('Manage options'),
                  'url' => Url::fromRoute('entity.poll_option.collection', ['poll_question' => $q->id()]),
                ],
                'add' => [
                  'title' => $this->t('Add option'),
                  'url' => Url::fromRoute('entity.poll_option.add_form', ['poll_question' => $q->id()]),
                ],
              ],
            ],
          ],
        ];
      }
    }

    return [
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No poll questions yet.'),
      ],
      'pager' => ['#type' => 'pager'],
    ];
  }
}
