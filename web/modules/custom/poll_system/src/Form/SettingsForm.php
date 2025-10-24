<?php

declare(strict_types=1);

namespace Drupal\poll_system\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class SettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames(): array {
    return ['poll_system.settings'];
  }

  public function getFormId(): string {
    return 'poll_system_settings';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('poll_system.settings');

    $form['voting_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable voting globally'),
      '#default_value' => $config->get('voting_enabled') ?? TRUE,
    ];

    $form['show_totals_default'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Default: show totals after vote'),
      '#default_value' => $config->get('show_totals_default') ?? TRUE,
      '#description' => $this->t('Used as default when creating new questions.'),
    ];

    // $form['require_auth'] = [
    //   '#type' => 'checkbox',
    //   '#title' => $this->t('Only authenticated users can vote'),
    //   '#default_value' => $config->get('require_auth') ?? TRUE,
    // ];

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#default_value' => $config->get('api_key') ?? '',
      '#description' => $this->t('If set, external API requests must include header "X-API-Key: <key>".'),
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable('poll_system.settings')
      ->set('voting_enabled', (bool) $form_state->getValue('voting_enabled'))
      ->set('show_totals_default', (bool) $form_state->getValue('show_totals_default'))
      // ->set('require_auth', (bool) $form_state->getValue('require_auth'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->save();
    parent::submitForm($form, $form_state);
  }
}
