<?php

namespace Drupal\workbench_email\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\workbench_email\TemplateInterface;

/**
 * Defines the Email Template entity.
 *
 * @ConfigEntityType(
 *   id = "workbench_email_template",
 *   label = @Translation("Email Template"),
 *   handlers = {
 *     "list_builder" = "Drupal\workbench_email\TemplateListBuilder",
 *     "form" = {
 *       "add" = "Drupal\workbench_email\Form\TemplateForm",
 *       "edit" = "Drupal\workbench_email\Form\TemplateForm",
 *       "delete" = "Drupal\workbench_email\Form\TemplateDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\workbench_email\TemplateHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "workbench_email_template",
 *   admin_permission = "administer workbench_email templates",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/workbench-moderation/workbench-email-template/{workbench_email_template}",
 *     "add-form" = "/admin/structure/workbench-moderation/workbench-email-template/add",
 *     "edit-form" = "/admin/structure/workbench-moderation/workbench-email-template/{workbench_email_template}/edit",
 *     "delete-form" = "/admin/structure/workbench-moderation/workbench-email-template/{workbench_email_template}/delete",
 *     "collection" = "/admin/structure/workbench-moderation/workbench-email-template"
 *   }
 * )
 */
class Template extends ConfigEntityBase implements TemplateInterface {
  /**
   * The Email Template ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Email Template label.
   *
   * @var string
   */
  protected $label;

  /**
   * Body with value and format keys.
   *
   * @var string[]
   */
  protected $body = [];

  /**
   * Message subject.
   *
   * @var string
   */
  protected $subject;

  /**
   * Fields to get email from.
   *
   * @var string[]
   */
  protected $fields = [];

  /**
   * Roles to send to.
   *
   * @var string[]
   */
  protected $roles = [];

  /**
   * Entity bundles.
   *
   * @var string[]
   */
  protected $bundles = [];

  /**
   * Send to entity owner.
   *
   * @var bool
   */
  protected $author = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getSubject() {
    return $this->subject;
  }

  /**
   * {@inheritdoc}
   */
  public function getBody() {
    return $this->body;
  }

  /**
   * {@inheritdoc}
   */
  public function setBody(array $body) {
    $this->body = $body;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setSubject($subject) {
    $this->subject = $subject;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isAuthor() {
    return $this->author;
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthor($author) {
    $this->author = $author;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFields() {
    return $this->fields;
  }

  /**
   * {@inheritdoc}
   */
  public function setFields(array $fields) {
    $this->fields = $fields;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRoles() {
    return $this->roles;
  }

  /**
   * {@inheritdoc}
   */
  public function setRoles(array $roles) {
    $this->roles = $roles;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();
    foreach ($this->roles as $role) {
      $this->addDependency('config', 'user.role.' . $role);
    }
    foreach ($this->fields as $field) {
      list($entity_type, $field_name) = explode(':', $field, 2);
      $this->addDependency('config', "field.storage.$entity_type.$field_name");
    }
    foreach ($this->bundles as $bundle) {
      list($entity_type_id, $bundle_id) = explode(':', $bundle, 2);
      $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
      $bundle_config_dependency = $entity_type->getBundleConfigDependency($bundle_id);
      $this->addDependency($bundle_config_dependency['type'], $bundle_config_dependency['name']);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getBundles() {
    return $this->bundles;
  }

  /**
   * {@inheritdoc}
   */
  public function setBundles($bundles) {
    $this->bundles = $bundles;
    return $this;
  }

}
