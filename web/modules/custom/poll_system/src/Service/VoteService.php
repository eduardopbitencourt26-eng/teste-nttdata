<?php

declare(strict_types=1);

namespace Drupal\poll_system\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\poll_system\Repository\PollRepository;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Psr\Log\LoggerInterface;

final class VoteService {

  public function __construct(
    private readonly PollRepository $repo,
    private readonly AccountProxyInterface $currentUser,
    private readonly EntityTypeManagerInterface $etm,
    private readonly Connection $db,
    private readonly LoggerInterface $logger,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly TimeInterface $time,
    private readonly UuidInterface $uuid,
    protected CacheTagsInvalidatorInterface $tagInvalidator
  ) {}

  /**
   * Registra um voto para a pergunta $id na opção $option_id.
   * Se $uid for null, usa o usuário logado (UI). Para API, passe o uid do token.
   */
  public function castVoteById(int $id, int $option_id, ?int $uid = null): string {
    $cfg = $this->configFactory->get('poll_system.settings');
    if (!($cfg->get('voting_enabled') ?? true)) {
      throw new \RuntimeException('Voting is disabled.');
    }

    $uid = $uid ?? (int) $this->currentUser->id();
    if ($uid <= 0) {
      throw new \RuntimeException('Authentication required to vote.');
    }

    $q = $this->repo->loadQuestionById($id);
    if (!$q || !$q->get('status')->value) {
      throw new \RuntimeException('Question not found or disabled.');
    }

    // Garante que a opção pertence à pergunta.
    $opt_storage = $this->etm->getStorage('poll_option');
    $option = $opt_storage->load($option_id);
    if (!$option || (int) $option->get('question')->target_id !== (int) $q->id()) {
      throw new \RuntimeException('Invalid option for this question.');
    }

    $this->db->startTransaction();
    try {
      // Checagem de idempotência por (question, uid).
      $exists = $this->db->select('poll_vote', 'v')
        ->fields('v', ['id'])
        ->condition('question', (int) $q->id())
        ->condition('uid', $uid)
        ->range(0, 1)
        ->execute()
        ->fetchField();

      if ($exists) {
        throw new \RuntimeException('You have already voted on this question.');
      }

      $this->db->insert('poll_vote')
        ->fields([
          'uuid'     => $this->uuid->generate(),
          'question' => (int) $q->id(),
          'option'   => (int) $option->id(),
          'uid'      => $uid,
          'created'  => $this->time->getRequestTime(),
        ])
        ->execute();

      $this->logger->info(
        'User @u voted on question @q option @o',
        ['@u' => $uid, '@q' => $q->id(), '@o' => $option->id()]
      );

      // Invalida cache de pergunta
      $this->tagInvalidator->invalidateTags([
        "poll_results:{$q->id()}",
      ]);

      return 'Vote registered.';
    }
    catch (DatabaseExceptionWrapper $e) {
      // Se a unique (question, uid) disparar numa corrida, normalize a mensagem.
      if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate')) {
        throw new \RuntimeException('You have already voted on this question.');
      }
      $this->logger->error('Vote DB error: @m', ['@m' => $e->getMessage()]);
      throw $e;
    }
    catch (\Throwable $e) {
      $this->logger->error('Vote error: @m', ['@m' => $e->getMessage()]);
      throw $e;
    }
  }
}
