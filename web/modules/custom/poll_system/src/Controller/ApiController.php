<?php

declare(strict_types=1);

namespace Drupal\poll_system\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\poll_system\Service\VoteService;
use Drupal\poll_system\Repository\PollRepository;
use Drupal\poll_system\Service\ApiTokenService;
use Drupal\poll_system\Service\RateLimiter;
use Drupal\user\UserAuthInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class ApiController extends ControllerBase
{

  public function __construct(
    protected VoteService $voteService,
    protected PollRepository $repo,
    protected ApiTokenService $tokenService,
    protected UserAuthInterface $userAuth,
    protected RateLimiter $rateLimiter,
  ) {}

  public static function create(ContainerInterface $container): static
  {
    return new static(
      $container->get('poll_system.vote_service'),
      $container->get('poll_system.repository'),
      $container->get('poll_system.token_service'),
      $container->get('user.auth'),
      $container->get('poll_system.rate_limiter'),
    );
  }

  /**
   * POST /api/login
   * Body: { "username": "...", "password": "..." }
   */
  public function login(Request $request): JsonResponse
  {
    $data = json_decode($request->getContent() ?: '{}', TRUE);
    if (!is_array($data)) {
      return new JsonResponse(['error' => 'Invalid JSON body.'], 400);
    }
    $username = trim((string) ($data['username'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    if ($username === '' || $password === '') {
      return new JsonResponse(['error' => 'Missing credentials.'], 400);
    }

    $uid = $this->userAuth->authenticate($username, $password);
    if (!$uid) {
      return new JsonResponse(['error' => 'Invalid credentials.'], 401);
    }

    $account = $this->entityTypeManager()->getStorage('user')->load($uid);
    if (!$account || !$account->isActive()) {
      return new JsonResponse(['error' => 'User disabled.'], 403);
    }

    $ttl = 3600; // maximo para expirar
    $scopes = ['poll:read', 'poll:vote'];

    $issued = $this->tokenService->issueForUid((int) $account->id(), $ttl, $scopes);

    return new JsonResponse([
      'access_token' => $issued['token'],
      'token_type' => 'Bearer',
      'expires_in' => $issued['expires_in'],
      'expires_at' => time() + $issued['expires_in'],
      'uid' => (int) $account->id(),
      'name' => $account->getAccountName(),
      'scopes' => $scopes,
    ]);
  }

  public function logout(Request $request): JsonResponse
  {
    $bearer = $this->tokenService->getBearerFromRequest($request);
    if (!$bearer) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'Missing bearer token.'], 401);
    }

    // expira imediatamente
    $this->tokenService->revoke($bearer);

    return new JsonResponse(['ok' => TRUE, 'message' => 'Token revoked.']);
  }



  protected function checkApiKey(Request $request): void
  {
    $cfg = $this->config('poll_system.settings');
    $key = trim((string) ($cfg->get('api_key') ?? ''));
    if ($key !== '') {
      $hdr = (string) $request->headers->get('X-API-Key', '');
      if (!hash_equals($key, $hdr)) {
        throw new \RuntimeException('Unauthorized (invalid API key).');
      }
    }
  }

  public function listQuestions(Request $request): JsonResponse
  {
    try {
      $this->checkApiKey($request);
    } catch (\RuntimeException $e) {
      return new JsonResponse(['error' => 'Unauthorized'], 401);
    }

    // paginação (?page=, ?per_page=)
    [$page, $perPage, $offset] = $this->pagination($request, 20, 100);

    $total = $this->repo->countActiveQuestions();
    $items = $this->repo->listActiveQuestions($offset, $perPage);

    return new JsonResponse([
      'data' => $items,
      'meta' => [
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => (int) ceil($total / max(1, $perPage)),
      ],
    ]);
  }


  public function getQuestion(Request $request, string $question_id): JsonResponse
  {
    try {
      $this->checkApiKey($request);
    } catch (\RuntimeException $e) {
      return new JsonResponse(['error' => 'Unauthorized'], 401);
    }

    [$page, $perPage, $offset] = $this->pagination($request, 20, 100);
    $payload = $this->repo->getQuestionPayload((int) $question_id, $offset, $perPage);
    if (!$payload) {
      return new JsonResponse(['error' => 'Not found'], 404);
    }

    $totalOpts = (int) $payload['_options_total'];
    unset($payload['_options_total']);

    return new JsonResponse([
      'data' => $payload,
      'meta' => [
        'options_total' => $totalOpts,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => (int) ceil($totalOpts / max(1, $perPage)),
      ],
    ]);
  }


  public function postVote(Request $request, int $question_id): JsonResponse
  {
    try {
      $this->checkApiKey($request);
    } catch (\RuntimeException $e) {
      return new JsonResponse(['error' => 'Unauthorized'], 401);
    }

    $bearer = $this->tokenService->getBearerFromRequest($request);
    if (!$bearer) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'Missing bearer token.'], 401);
    }
    $payload = $this->tokenService->validateToken($bearer, 'poll:vote');
    if (!$payload) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'Invalid or expired token.'], 401);
    }
    $uid = (int) $payload['uid'];
    $q_entity = $this->repo->loadQuestionById($question_id);



    // 10 votos em 60s por uid+pergunta
    $questionNumericId = (string) $q_entity->id();
    $rateKey = "vote:uid:{$uid}:q:{$questionNumericId}";
    if (!$this->rateLimiter->allow($rateKey, 10, 60)) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'Rate limit exceeded. Try again later.'], 429);
    }

    $account = $this->entityTypeManager()->getStorage('user')->load($uid);
    if (!$account || !$account->isActive()) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'User disabled.'], 403);
    }
    if (!$account->hasPermission('vote via poll api')) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'Insufficient permission.'], 403);
    }

    $settings = $this->config('poll_system.settings');
    if (!($settings->get('voting_enabled') ?? TRUE)) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'Voting is disabled.'], 503);
    }

    if (!$q_entity) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'Question not found.'], 404);
    }

    $data = json_decode($request->getContent() ?: '{}', TRUE);
    if (!is_array($data)) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'Invalid JSON body.'], 400);
    }

    $option_id = (int) ($data['option_id'] ?? 0);
    if ($option_id <= 0) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'Missing or invalid option_id.'], 400);
    }

    $opt = $this->entityTypeManager()->getStorage('poll_option')->load($option_id);
    if (!$opt || (int) $opt->get('question')->target_id !== (int) $q_entity->id()) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'Option does not belong to this question.'], 400);
    }

    try {
      $msg = $this->voteService->castVoteById((int) $q_entity->id(), $option_id, $uid);
      return new JsonResponse(['ok' => TRUE, 'message' => $msg]);
    } catch (\Throwable $e) {
      $this->getLogger('poll_system')->warning('API vote error: @m', ['@m' => $e->getMessage()]);
      return new JsonResponse(['ok' => FALSE, 'error' => $e->getMessage()], 400);
    }
  }


  public function getResults(Request $request, string $question_id): JsonResponse
  {
    try {
      $this->checkApiKey($request);
    } catch (\RuntimeException $e) {
      return new JsonResponse(['error' => 'Unauthorized'], 401);
    }

    $q = $this->repo->loadQuestionById((int) $question_id);
    if (!$q) {
      return new JsonResponse(['error' => 'Not found'], 404);
    }

    if (!(bool) $q->get('show_results')->value) {
      return new JsonResponse([
        'error' => 'Results are hidden for this question.',
        'show_results' => FALSE,
      ], 403);
    }

    [$page, $perPage, $offset] = $this->pagination($request, 20, 100);

    $results = $this->repo->resultsData((int) $q->id(), $offset, $perPage);

    return new JsonResponse([
      'question' => [
        'id' => (int) $q->id(),
        'title' => (string) $q->label(),
        'show_results' => TRUE,
      ],
      'results' => [
        'total_votes' => (int) $results['total'],
        'options' => $results['options'],
      ],
      'meta' => [
        'options_total' => (int) $results['total_options'],
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => (int) ceil(((int) $results['total_options']) / max(1, $perPage)),
      ],
    ]);
  }


  private function pagination(Request $request, int $defaultPerPage = 20, int $maxPerPage = 100): array
  {
    $page = max(1, (int) $request->query->get('page', 1));
    $perPage = (int) $request->query->get('per_page', $defaultPerPage);
    $perPage = max(1, min($perPage, $maxPerPage));
    $offset = ($page - 1) * $perPage;
    return [$page, $perPage, $offset];
  }
}
