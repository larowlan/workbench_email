<?php

namespace Drupal\workbench_email\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

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
    // Add the roles.
    $roles = array_filter(Role::loadMultiple(), function(RoleInterface $role) {
      return !in_array($role->id(), [
        RoleInterface::ANONYMOUS_ID,
        RoleInterface::AUTHENTICATED_ID,
      ], TRUE);
    });
    $role_options = array_map(function (RoleInterface $role) {
      return $role->label();
    }, $roles);
    $form['recipients'] = [
      '#type' => 'details',
      '#title' => t('Recipients'),
      '#open' => TRUE,
    ];
    $form['recipients']['roles'] = [
      '#type' => 'checkboxes',
      '#title' => t('Roles'),
      '#description' => t('Send to all users with selected roles'),
      '#options' => $role_options,
      '#default_value' => $workbench_email_template->getRoles(),
    ];
    // Add the fields.
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager */
    $field_manager = \Drupal::service('entity_field.manager');
    $entity_type_manager = \Drupal::entityTypeManager();
    $fields = $field_manager->getFieldMapByFieldType('email');
    $field_options = [];
    foreach ($fields as $entity_type_id => $entity_type_fields) {
      $base = $field_manager->getBaseFieldDefinitions($entity_type_id);
      $entity_type = $entity_type_manager->getDefinition($entity_type_id);
      foreach ($entity_type_fields as $field_name => $field_detail) {
        if (in_array($field_name, array_keys($base), TRUE)) {
          continue;
        }
        $sample_bundle = reset($field_detail['bundles']);
        $sample_field = FieldConfig::load($entity_type_id . '.' . $sample_bundle . '.' . $field_name);
        $field_options[$entity_type_id . ':' . $field_name] = $sample_field->label() . ' (' . $entity_type->getLabel() . ')';
      }
    }
    $form['recipients']['fields'] = [
      '#type' => 'checkboxes',
      '#title' => t('Email Fields'),
      '#description' => t('Send to mail address found in the selected fields'),
      '#options' => $field_options,
      '#default_value' => $workbench_email_template->getFields(),
    ];
    // Add the author flag.
    $form['recipients']['author'] = [
      '#type' => 'checkbox',
      '#default_value' => $workbench_email_template->isAuthor(),
      '#title' => t('Author'),
      '#description' => t('Send to entity author/owner'),
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

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    parent::copyFormValuesToEntity($entity, $form, $form_state);
    // Filter out unchecked items.
    $entity->set('roles', array_filter($entity->get('roles')));
    $entity->set('fields', array_filter($entity->get('fields')));
  }

}
