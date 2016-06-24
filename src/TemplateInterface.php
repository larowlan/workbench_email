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

}
