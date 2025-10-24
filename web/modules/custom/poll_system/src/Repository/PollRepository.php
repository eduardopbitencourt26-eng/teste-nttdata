<?php

declare(strict_types=1);

namespace Drupal\poll_system\Repository;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\poll_system\Entity\Question;
use Drupal\poll_system\Entity\Option;
use Psr\Log\LoggerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\Cache;

class PollRepository
{
  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $etm,
    protected Connection $db,
    protected LoggerInterface $logger,
    protected CacheBackendInterface $cache
  ) {}

  public function countActiveQuestions(): int
  {
    return (int) $this->etm->getStorage('poll_question')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->count()
      ->execute();
  }

  public function countOptionsForQuestion(int $question_id): int
  {
    return (int) $this->etm->getStorage('poll_option')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('question', $question_id)
      ->count()
      ->execute();
  }

  public function loadQuestionById(int $id): ?Question
  {
    $ids = $this->etm->getStorage('poll_question')->getQuery()
      ->accessCheck(FALSE)
      ->condition('id', $id)
      ->condition('status', 1)
      ->execute();
    if (!$ids) return NULL;
    /** @var \Drupal\poll_system\Entity\Question $q */
    $q = $this->etm->getStorage('poll_question')->load(reset($ids));

    return $q;
  }

  public function loadOptionsForQuestion(int $question_id, int $offset = 0, ?int $limit = NULL): array
  {
    $storage = $this->etm->getStorage('poll_option');

    $q = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('question', $question_id)
      ->sort('weight', 'ASC');

    if ($limit !== NULL && $limit > 0) {
      $q->range(max(0, $offset), $limit);
    }

    $ids = $q->execute();
    return $ids ? $storage->loadMultiple($ids) : [];
  }

  public function optionsAdminTable(Question $q): array
  {
    // Quantidade por página
    $limit = 5;

    $storage = $this->etm->getStorage('poll_option');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('question', (int) $q->id())
      ->sort('weight', 'ASC')
      ->pager($limit)
      ->execute();

    /** @var \Drupal\poll_system\Entity\Option[] $options */
    $options = $ids ? $storage->loadMultiple($ids) : [];

    $has_desc = FALSE;
    $has_img  = FALSE;
    foreach ($options as $o) {
      if (!$has_desc) {
        $desc = trim((string) $o->get('description')->value);
        $has_desc = ($desc !== '');
      }
      if (!$has_img) {
        $has_img = (bool) $o->get('image')->entity;
      }
      if ($has_desc && $has_img) {
        break;
      }
    }

    $header = [
      $this->t('ID'),
      $this->t('Title'),
    ];
    if ($has_desc) {
      $header[] = $this->t('Description');
    }
    if ($has_img) {
      $header[] = $this->t('Image');
    }
    $header[] = $this->t('Operations');

    $rows = [];
    foreach ($options as $o) {
      $row = [];
      $row[] = (int) $o->id();
      $row[] = $o->label();

      if ($has_desc) {
        $desc = trim((string) $o->get('description')->value);
        $row[] = $desc !== '' ? $desc : '';
      }

      if ($has_img) {
        $img_cell = '';
        if ($file = $o->get('image')->entity) {
          $img_cell = [
            'data' => [
              '#theme' => 'image',
              '#uri' => $file->getFileUri(),
              '#alt' => $o->label(),
              '#attributes' => [
                'style' => 'max-width:90px;height:auto;display:block;',
              ],
            ],
          ];
        }
        $row[] = $img_cell;
      }

      $row[] = [
        'data' => [
          '#type' => 'operations',
          '#links' => [
            'edit' => [
              'title' => $this->t('Edit'),
              'url' => $o->toUrl('edit-form'),
            ],
            'delete' => [
              'title' => $this->t('Delete'),
              'url' => $o->toUrl('delete-form'),
            ],
          ],
        ],
      ];

      $rows[] = $row;
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No options yet.'),
    ];

    $build['pager'] = ['#type' => 'pager'];

    return $build;
  }

  public function listActiveQuestions(int $offset = 0, ?int $limit = NULL): array
  {
    $storage = $this->etm->getStorage('poll_question');
    $q = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->sort('id', 'ASC');

    if ($limit !== NULL && $limit > 0) {
      $q->range(max(0, $offset), $limit);
    }

    $ids = $q->execute();
    $out = [];
    if ($ids) {
      $list = $storage->loadMultiple($ids);
      foreach ($list as $q) {
        $out[] = [
          'id' => (int) $q->id(),
          'title' => (string) $q->label(),
          'show_results' => (bool) $q->get('show_results')->value,
        ];
      }
    }
    return $out;
  }

  public function getQuestionPayload(int $id, int $offset = 0, ?int $limit = NULL): ?array
  {
    /** @var \Drupal\poll_system\Entity\Question|null $q */
    $q = $this->etm->getStorage('poll_question')->load($id);
    if (!$q || !(bool) $q->get('status')->value) {
      return NULL;
    }

    $total_opts = $this->countOptionsForQuestion((int) $q->id());
    $ops = $this->loadOptionsForQuestion((int) $q->id(), $offset, $limit);

    $opts = [];
    foreach ($ops as $o) {
      $opts[] = [
        'id' => (int) $o->id(),
        'title' => (string) $o->label(),
        'description' => (string) $o->get('description')->value,
        'image' => $o->get('image')->entity?->createFileUrl(FALSE) ?? NULL,
        'weight' => (int) $o->get('weight')->value,
      ];
    }

    return [
      'id' => (int) $q->id(),
      'title' => (string) $q->label(),
      'show_results' => (bool) $q->get('show_results')->value,
      'options' => $opts,
      '_options_total' => $total_opts,
    ];
  }

  public function resultsData(int $question_id, int $offset = 0, ?int $limit = NULL): array
  {
    // Cache HIT/MISS
    $cache_key = 'poll_results:' . $question_id . ':' . (int)$offset . ':' . (int)($limit ?? 0);
    if ($cached = $this->cache->get($cache_key)) {
      $this->logger->debug('resultsData cache HIT @id', ['@id' => $question_id]);
      return $cached->data;
    }

    $totalVotes = (int) $this->db->select('poll_vote', 'v')
      ->condition('v.question', $question_id)
      ->countQuery()
      ->execute()
      ->fetchField();

    $totalOptions = $this->countOptionsForQuestion($question_id);

    $q = $this->db->select('poll_option', 'o')
      ->fields('o', ['id', 'title', 'weight'])
      ->condition('o.question', $question_id);
    $q->leftJoin('poll_vote', 'v', 'v.option = o.id');
    $q->addExpression('COUNT(v.id)', 'votes');
    $q->groupBy('o.id');
    $q->groupBy('o.title');
    $q->groupBy('o.weight');
    $q->orderBy('o.weight', 'ASC');

    if ($limit !== NULL && $limit > 0) {
      $q->range(max(0, $offset), $limit);
    }

    $rows = $q->execute()->fetchAllAssoc('id');

    $out = [];
    foreach ($rows as $id => $row) {
      $v = (int) $row->votes;
      $pct = $totalVotes ? round($v * 100 / $totalVotes, 2) : 0.0;
      $out[] = [
        'option_id' => (int) $id,
        'title' => (string) $row->title,
        'votes' => $v,
        'percentage' => $pct,
      ];
    }

    $data = [
      'total' => $totalVotes,
      'total_options' => $totalOptions,
      'options' => $out,
    ];

    $tags = [
      "poll_results:$question_id",
      "poll_question:$question_id",
      "poll_option_list",
    ];
    $this->logger->debug('resultsData cache MISS @id (gravando)', ['@id' => $question_id]);
    $this->cache->set($cache_key, $data, Cache::PERMANENT, $tags);

    return $data;
  }


  public function renderResultsHtml(Question $q): string
  {
    $data = $this->resultsData((int) $q->id());
    $html = '<div class="poll-results"><div><strong>Total:</strong> ' . (int) $data['total'] . '</div><ul>';
    foreach ($data['options'] as $o) {
      $html .= '<li>' . htmlspecialchars($o['title']) . ' — ' . $o['votes'] . ' (' . $o['percentage'] . '%)</li>';
    }
    $html .= '</ul></div>';
    return $html;
  }
}
