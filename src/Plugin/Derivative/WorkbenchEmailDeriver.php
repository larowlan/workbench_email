<?php

namespace Drupal\workbench_email\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\workbench_moderation\ModerationInformationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver to define a queue for each entity type.
 *
 * @see plugin_api
 */
class WorkbenchEmailDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * Moderation info service.
   *
   * @var \Drupal\workbench_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * Creates an EntityMatcherDeriver object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\workbench_moderation\ModerationInformationInterface $moderation_info
   *   Moderation info service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModerationInformationInterface $moderation_info) {
    $this->entityManager = $entity_type_manager;
    $this->moderationInfo = $moderation_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('workbench_moderation.moderation_information')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach ($this->entityManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($this->moderationInfo->isModeratableEntityType($entity_type)) {
        $this->derivatives[$entity_type_id] = $base_plugin_definition;
        $this->derivatives[$entity_type_id]['id'] = $base_plugin_definition['id'] . ':' . $entity_type_id;
        $this->derivatives[$entity_type_id]['title'] = (string) $base_plugin_definition['title'] . ':' . $entity_type->getLabel();
        $this->derivatives[$entity_type_id]['entity_type'] = $entity_type_id;
        $this->derivatives[$entity_type_id]['base_plugin_title'] = (string) $base_plugin_definition['title'];
      }
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
