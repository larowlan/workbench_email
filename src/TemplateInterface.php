<?php

namespace Drupal\workbench_email;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining Email Template entities.
 */
interface TemplateInterface extends ConfigEntityInterface {

  /**
   * Gets the template subject.
   *
   * @return string
   *   Template subject.
   */
  public function getSubject();

  /**
   * Gets the template body - array with keys value and format.
   *
   * @return string[]
   *   Template body.
   */
  public function getBody();

  /**
   * Sets the body.
   *
   * @param string[] $body
   *   Body with keys value and format.
   *
   * @return self
   *   Called instance
   */
  public function setBody(array $body);

  /**
   * Sets the subject.
   *
   * @param string $subject
   *   Template subject.
   *
   * @return self
   *   Called instance.
   */
  public function setSubject($subject);

  /**
   * Gets value of author.
   *
   * @return boolean
   *   Value of author
   */
  public function isAuthor();

  /**
   * Sets author.
   *
   * @param boolean $author
   *   New value for author.
   *
   * @return self
   *   Instance called.
   */
  public function setAuthor($author);

  /**
   * Gets value of fields.
   *
   * @return string[]
   *   Value of fields
   */
  public function getFields();

  /**
   * Sets fields.
   *
   * @param string[] $fields
   *   New value for fields.
   *
   * @return self
   *   Instance called.
   */
  public function setFields(array $fields);

  /**
   * Gets value of roles.
   *
   * @return string[]
   *   Value of roles
   */
  public function getRoles();

  /**
   * Sets roles.
   *
   * @param string[] $roles
   *   New value for roles.
   *
   * @return self
   *   Instance called.
   */
  public function setRoles(array $roles);

  /**
   * Gets value of bundles.
   *
   * @return string[]
   *   Value of bundles
   */
  public function getBundles();

  /**
   * Sets bundles this template applies to.
   *
   * @param string[] $bundles
   *   Bundles this template applies to in {entity_type_id}:{bundle} format.
   *
   * @return self
   *   Called instance.
   */
  public function setBundles($bundles);

}
