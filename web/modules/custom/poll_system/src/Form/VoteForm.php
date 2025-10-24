<?php

declare(strict_types=1);

namespace Drupal\poll_system\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\poll_system\Entity\Question;
use Drupal\poll_system\Repository\PollRepository;
use Drupal\poll_system\Service\VoteService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class VoteForm extends FormBase
{

  public function __construct(
    protected VoteService $voteService,
    protected PollRepository $repo,
  ) {}

  public static function create(ContainerInterface $container): static
  {
    return new static(
      $container->get('poll_system.vote_service'),
      $container->get('poll_system.repository'),
    );
  }

  public function getFormId(): string
  {
    return 'poll_system_vote_form';
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param \Drupal\poll_system\Entity\Question $poll_question
   */
  public function buildForm(array $form, FormStateInterface $form_state, Question $poll_question = NULL): array
  {
    if (!$poll_question || !$poll_question->get('status')->value) {
      $form['msg'] = ['#markup' => $this->t('Question not found or disabled.')];
      return $form;
    }

    $form['question_header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['poll-question__header']],
      'title' => [
        '#markup' => '<h2 class="poll-question__title">' . $poll_question->label() . '</h2>',
      ],
    ];

    $options = $this->repo->loadOptionsForQuestion((int) $poll_question->id());
    if (!$options) {
      $form['msg'] = ['#markup' => $this->t('No options available.')];
      return $form;
    }

    $form['question_id'] = [
      '#type' => 'value',
      '#value' => (int) $poll_question->id(),
    ];

    $form['options'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['poll-options']],
    ];

    $default = $form_state->getValue(['option_id']) ?? NULL;
    $file_url = \Drupal::service('file_url_generator');

    foreach ($options as $opt) {
      $id = (int) $opt->id();
      $title = $opt->label();
      $desc  = (string) $opt->get('description')->value;
      $img   = $opt->get('image')->entity ? $file_url->generateString($opt->get('image')->entity->getFileUri()) : NULL;

      $form['options']["opt_$id"] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['poll-option', 'clearfix']],
        'radio' => [
          '#type' => 'radio',
          '#title' => $title,
          '#return_value' => $id,
          '#parents' => ['option_id'],
          '#default_value' => $default,
          '#required' => TRUE,
        ],
        'meta' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['poll-option__meta']],
          'image' => $img ? [
            '#theme' => 'image',
            '#uri' => $img,
            '#alt' => $title,
            '#attributes' => ['style' => 'max-width:180px;height:auto;display:block;margin:.25rem 0;'],
          ] : [],
          'desc' => $desc ? [
            '#markup' => '<div class="poll-option__desc">' . $this->t('@d', ['@d' => $desc]) . '</div>',
          ] : [],
        ],
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Vote'),
    ];

    $form['#attached']['html_head'][] = [
      [
        '#tag' => 'style',
        '#value' => '
          .poll-options { display:grid; gap:12px; }
          .poll-option { padding:10px; border:1px solid #ddd; border-radius:8px; }
          .poll-option__desc { color:#555; margin-top:.25rem; }
        ',
      ],
      'poll_system_inline_styles',
    ];

    return $form;
  }


  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    $qid = (int) $form_state->getValue('question_id');
    $oid = (int) $form_state->getValue('option_id');

    try {
      $msg = $this->voteService->castVoteById((int) $qid, $oid);
      $this->messenger()->addStatus($msg);
    } catch (\Throwable $e) {
      $this->messenger()->addError($e->getMessage());
    }

    // Recarrega a mesma página
    $form_state->setRebuild(FALSE);
    $form_state->setRedirect('poll_system.vote_ui', ['poll_question' => $qid]);
  }
}
