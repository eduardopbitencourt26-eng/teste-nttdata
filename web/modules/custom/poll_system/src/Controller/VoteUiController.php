<?php

declare(strict_types=1);

namespace Drupal\poll_system\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\poll_system\Entity\Question;
use Drupal\poll_system\Form\VoteForm;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\poll_system\Service\VoteService;
use Drupal\poll_system\Repository\PollRepository;

class VoteUiController extends ControllerBase {

  public function __construct(
    protected VoteService $voteService,
    protected PollRepository $repo
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('poll_system.vote_service'),
      $container->get('poll_system.repository')
    );
  }

  public function view(Question $poll_question) {
    $settings = $this->config('poll_system.settings');
    if (!($settings->get('voting_enabled') ?? TRUE)) {
      return ['#markup' => $this->t('Voting is disabled.')];
    }
    if (!$poll_question->get('status')->value) {
      return ['#markup' => $this->t('Question not found.')];
    }
  
    $build['form'] = \Drupal::formBuilder()->getForm(VoteForm::class, $poll_question);
    if ((bool) $poll_question->get('show_results')->value) {
      $build['results'] = [
        '#type' => 'details',
        '#open' => TRUE,
        '#title' => $this->t('Results'),
        'content' => ['#markup' => $this->repo->renderResultsHtml($poll_question)],
      ];
    }
  
    return $build;
  }
  
  

  public function submit(Question $poll_question, Request $request) {
    $payload = json_decode($request->getContent() ?: '{}', TRUE);
    $option_id = (int) ($payload['option_id'] ?? 0);
  
    try {
      $res = $this->voteService->castVoteById((string)$poll_question->id(), $option_id);
      return $this->json(['ok' => TRUE, 'message' => $res]);
    }
    catch (\Throwable $e) {
      $this->getLogger('poll_system')->error('Vote error: @m', ['@m' => $e->getMessage()]);
      return $this->json(['ok' => FALSE, 'error' => $e->getMessage()], 400);
    }
  }
}
