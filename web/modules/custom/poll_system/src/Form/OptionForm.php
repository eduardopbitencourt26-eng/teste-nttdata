<?php

declare(strict_types=1);

namespace Drupal\poll_system\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class OptionForm extends EntityForm {

  public function __construct(
    protected EntityTypeManagerInterface $etm,
    protected FileSystemInterface $fs,
    protected FileUsageInterface $fileUsage
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('file.usage')
    );
  }

  public function getFormId(): string {
    return 'poll_system_option_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\poll_system\Entity\Option $option */
    $option = $this->getEntity();

    // Se veio de /admin/content/polls/{poll_question}/options/add,
    // preenche o question automaticamente.
    // Pode vir como entidade ou como ID numérico
    $route_question = $this->getRouteMatch()->getParameter('poll_question');
    $default_question_id = NULL;

    if (is_object($route_question) && method_exists($route_question, 'id')) {
      $default_question_id = (int) $route_question->id();
    } elseif (is_numeric($route_question)) {
      $default_question_id = (int) $route_question;
    } elseif ($option->get('question')->target_id) {
      $default_question_id = (int) $option->get('question')->target_id;
    }

    $question_storage = $this->etm->getStorage('poll_question');

    if ($default_question_id) {
      // ✅ Visível mas não editável
      $form['question_display'] = [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('Question'),
        '#target_type' => 'poll_question',
        '#default_value' => $question_storage->load($default_question_id),
        '#disabled' => TRUE,
        '#description' => $this->t('This option will be created for the question above.'),
      ];
      // ✅ Hidden com o valor real para o submit
      $form['question'] = [
        '#type' => 'value',
        '#value' => $default_question_id,
      ];
    }
    else {
      // Fallback: tela “solta” sem id na URL → deixa escolher
      $form['question'] = [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('Question'),
        '#target_type' => 'poll_question',
        '#default_value' => NULL,
        '#required' => TRUE,
        '#description' => $this->t('Select the poll question this option belongs to.'),
      ];
    }

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#maxlength' => 255,
      '#required' => TRUE,
      '#default_value' => $option->label() ?? '',
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $option->get('description')->value ?? '',
      '#rows' => 3,
    ];

    // Upload simples via managed_file.
    $form['image'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Image'),
      '#upload_location' => 'public://poll_option',
      '#default_value' => $option->get('image')->target_id ? [$option->get('image')->target_id] : NULL,
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg webp'],
      ],
      '#description' => $this->t('Optional. PNG, JPG, JPEG or WEBP.'),
    ];

    $form['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Weight'),
      '#default_value' => (int) ($option->get('weight')->value ?? 0),
      '#step' => 1,
      '#description' => $this->t('Lower values appear first.'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $option->isNew() ? $this->t('Save option') : $this->t('Update option'),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Apenas sanity checks simples aqui. (peso numérico etc.)
    $weight = $form_state->getValue('weight');
    if ($weight !== '' && !is_numeric($weight)) {
      $form_state->setErrorByName('weight', $this->t('Weight must be a number.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\poll_system\Entity\Option $option */
    $option = $this->getEntity();

    // Question
    $question_target = (int) ($form_state->getValue('question') ?? 0);
    $option->set('question', $question_target);

    // Title / Description
    $option->set('title', trim((string) $form_state->getValue('title')));
    $option->set('description', (string) $form_state->getValue('description'));

    // Image (managed_file retorna array de fids).
    $fid_arr = (array) ($form_state->getValue('image') ?? []);
    if (!empty($fid_arr[0])) {
      $fid = (int) $fid_arr[0];
      /** @var \Drupal\file\FileInterface|null $file */
      $file = $this->etm->getStorage('file')->load($fid);
      if ($file) {
        $file->setPermanent();
        $file->save();
        // Registra uso do arquivo para não ser limpo por garbage collection.
        $this->fileUsage->add($file, 'poll_system', 'poll_option', 0);
        $option->set('image', $fid);
      }
    } else {
      $option->set('image', NULL);
    }

    // Weight
    $option->set('weight', (int) $form_state->getValue('weight'));

    $option->save();

    $this->messenger()->addStatus($this->t('Option %t saved.', ['%t' => $option->label()]));

    // Redireciona para a lista de opções da respectiva pergunta.
    if ($question_target) {
      $form_state->setRedirect('entity.poll_option.collection', ['poll_question' => $question_target]);
    } else {
      $form_state->setRedirect('entity.poll_question.collection');
    }
  }
}
