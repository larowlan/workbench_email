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

}
