<?php

declare(strict_types=1);

namespace Drupal\poll_system\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class QuestionForm extends EntityForm
{

  public function __construct(
    protected EntityTypeManagerInterface $etm,
    protected AccountProxyInterface $currentUser,
  ) {}

  public static function create(ContainerInterface $container): static
  {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  public function getFormId(): string
  {
    return 'poll_system_question_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    /** @var \Drupal\poll_system\Entity\Question $question */
    $question = $this->getEntity();

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#maxlength' => 255,
      '#required' => TRUE,
      '#default_value' => $question->label() ?? '',
    ];

    $form['show_results'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show results after vote'),
      '#default_value' => (int) ($question->get('show_results')->value ?? 1),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => (int) ($question->get('status')->value ?? 1),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $question->isNew() ? $this->t('Save question') : $this->t('Update question'),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void
  {
    $form_state->setValue('title', trim((string) $form_state->getValue('title')));
  }


  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    /** @var \Drupal\poll_system\Entity\Question $question */
    $question = $this->getEntity();

    $question->set('title', trim((string) $form_state->getValue('title')));
    $question->set('show_results', (int) $form_state->getValue('show_results'));
    $question->set('status', (int) $form_state->getValue('status'));

    if ($question->isNew() && !$question->get('uid')->target_id) {
      $question->set('uid', (int) $this->currentUser->id());
    }

    try {
      $result = $question->save();

      $this->messenger()->addStatus(
        $result === SAVED_NEW
          ? $this->t('Question %t created.', ['%t' => $question->label()])
          : $this->t('Question %t updated.', ['%t' => $question->label()])
      );

      // Redireciona para tela de opções se for nova pergunta
      if ($result === SAVED_NEW) {
        $form_state->setRedirect('entity.poll_option.collection', ['poll_question' => $question->id()]);
        return;
      }

      $form_state->setRedirect('entity.poll_question.collection');
    } catch (\Throwable $e) {
      $this->getLogger('poll_system')->error('Failed to save question: @m', ['@m' => $e->getMessage()]);
      $this->messenger()->addError($this->t('Failed to save the question.'));
    }
  }
}
