<?php

namespace Drupal\workbench_email\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class TemplateForm.
 *
 * @package Drupal\workbench_email\Form
 */
class TemplateForm extends EntityForm {
  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\workbench_email\TemplateInterface $workbench_email_template */
    $workbench_email_template = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $workbench_email_template->label(),
      '#description' => $this->t("Label for the Email Template."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $workbench_email_template->id(),
      '#maxlength' => EntityTypeInterface::ID_MAX_LENGTH,
      '#machine_name' => [
        'exists' => '\Drupal\workbench_email\Entity\Template::load',
      ],
      '#disabled' => !$workbench_email_template->isNew(),
    ];

    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#maxlength' => 255,
      '#default_value' => $workbench_email_template->getSubject(),
      '#description' => $this->t("Email subject - you can use tokens like [node:title] depending on the entity-type being updated."),
      '#required' => TRUE,
    ];

    $default_body = $workbench_email_template->getBody() + [
      'value' => '',
      'format' => 'plain_text',
    ];
    $form['body'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Body'),
      '#description' => $this->t('Email body, you may use tokens like [node:title] depending on the entity-type being updated.'),
      '#required' => TRUE,
      '#format' => $default_body['format'],
      '#default_value' => $default_body['value'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $workbench_email_template = $this->entity;
    $status = $workbench_email_template->save();

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Email Template.', [
          '%label' => $workbench_email_template->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Email Template.', [
          '%label' => $workbench_email_template->label(),
        ]));
    }
    $form_state->setRedirectUrl($workbench_email_template->urlInfo('collection'));
  }

}
