<?php

namespace Drupal\workbench_email\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Drupal\workbench_moderation\ModerationInformationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class TemplateForm.
 *
 * @package Drupal\workbench_email\Form
 */
class TemplateForm extends EntityForm {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Entity Bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityBundleInfo;

  /**
   * Moderation info.
   *
   * @var \Drupal\workbench_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * Constructs a new TemplateForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Entity field manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $entity_bundle_info, ModerationInformationInterface $moderation_info) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityBundleInfo = $entity_bundle_info;
    $this->moderationInfo = $moderation_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'), $container->get('entity_field.manager'), $container->get('entity_type.bundle.info'), $container->get('workbench_moderation.moderation_information'));
  }

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
    $roles = array_filter($this->entityTypeManager->getStorage('user_role')
      ->loadMultiple(), function (RoleInterface $role) {
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
      '#title' => $this->t('Recipients'),
      '#open' => TRUE,
    ];
    $form['recipients']['roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles'),
      '#description' => $this->t('Send to all users with selected roles'),
      '#options' => $role_options,
      '#default_value' => $workbench_email_template->getRoles(),
    ];
    // Add the fields.
    $fields = $this->entityFieldManager->getFieldMapByFieldType('email');
    $field_options = [];
    foreach ($fields as $entity_type_id => $entity_type_fields) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      if (!$this->moderationInfo->isModeratableEntityType($entity_type)) {
        // These fields are irrelevant, the entity type isn't moderated.
        continue;
      }
      $base = $this->entityFieldManager->getBaseFieldDefinitions($entity_type_id);
      foreach ($entity_type_fields as $field_name => $field_detail) {
        if (in_array($field_name, array_keys($base), TRUE)) {
          continue;
        }
        $sample_bundle = reset($field_detail['bundles']);
        $sample_field = $this->entityTypeManager->getStorage('field_config')
          ->load($entity_type_id . '.' . $sample_bundle . '.' . $field_name);
        $field_options[$entity_type_id . ':' . $field_name] = $sample_field->label() . ' (' . $entity_type->getLabel() . ')';
      }
    }
    $form['recipients']['fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Email Fields'),
      '#description' => $this->t('Send to mail address found in the selected fields'),
      '#options' => $field_options,
      '#default_value' => $workbench_email_template->getFields(),
    ];
    // Add the author flag.
    $form['recipients']['author'] = [
      '#type' => 'checkbox',
      '#default_value' => $workbench_email_template->isAuthor(),
      '#title' => $this->t('Author'),
      '#description' => $this->t('Send to entity author/owner'),
    ];
    $bundle_options = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      if (!$this->moderationInfo->isModeratableEntityType($entity_type) || !($bundle_entity_type = $entity_type->getBundleEntityType())) {
        // Irrelevant - continue.
        continue;
      }
      $entity_type_id = $entity_type->id();
      $bundles = $this->entityBundleInfo->getBundleInfo($entity_type_id);
      $bundle_storage = $this->entityTypeManager->getStorage($bundle_entity_type);
      $bundle_entities = $bundle_storage->loadMultiple(array_keys($bundles));
      foreach ($bundle_entities as $bundle_id => $bundle) {
        if ($bundle->getThirdPartySetting('workbench_moderation', 'enabled', FALSE)) {
          $bundle_options["$entity_type_id:$bundle_id"] = $bundle->label() . ' (' . $entity_type->getLabel() . ')';
        }
      }
    }
    $form['bundles'] = [
      '#type' => 'checkboxes',
      '#options' => $bundle_options,
      '#access' => !empty($bundle_options),
      '#default_value' => $workbench_email_template->getBundles(),
      '#title' => $this->t('Bundles'),
      '#description' => $this->t('Limit to the following bundles. Select none to include all bundles.'),
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
    $entity->set('bundles', array_filter($entity->get('bundles')));
  }

}
