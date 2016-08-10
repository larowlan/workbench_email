<?php

namespace Drupal\workbench_email\EventSubscriber;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Mail\MailManager;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Token;
use Drupal\user\EntityOwnerInterface;
use Drupal\workbench_email\QueuedEmail;
use Drupal\workbench_email\TemplateInterface;
use Drupal\workbench_moderation\Event\WorkbenchModerationEvents;
use Drupal\workbench_moderation\Event\WorkbenchModerationTransitionEvent;
use Drupal\workbench_moderation\ModerationStateTransitionInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to transition changes to send notification emails.
 */
class WorkbenchTransitionEventSubscriber implements EventSubscriberInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The queue service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user, QueueFactory $queue_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->queueFactory = $queue_factory;
  }

  /**
   * Event handler.
   *
   * @param \Drupal\workbench_moderation\Event\WorkbenchModerationTransitionEvent $event
   *   The event listened to.
   */
  public function onTransition(WorkbenchModerationTransitionEvent $event) {
    $entity = $event->getEntity();
    /* @var \Drupal\Core\Config\Entity\ConfigEntityInterface $bundle_entity */
    $bundle_entity = $this->entityTypeManager->getStorage($entity->getEntityType()->getBundleEntityType())->load($entity->bundle());
    if (!$event->getStateBefore()) {
      // We need to use the default.
      $from = $bundle_entity->getThirdPartySetting('workbench_moderation', 'default_moderation_state', FALSE);
    }
    else {
      $from = $event->getStateBefore();
    }
    $to = $event->getStateAfter();
    // Load transitions.
    // We don't have the transition available, so we have to load any matching
    // ones.
    if ($transitions = $this->entityTypeManager->getStorage('moderation_state_transition')->loadByProperties([
      'stateFrom' => $from,
      'stateTo' => $to,
    ])) {
      // Filter out any that the user doesn't have access to or that don't have
      // any email templates.
      $transitions = array_filter($transitions, function(ModerationStateTransitionInterface $transition) {
        return $this->currentUser->hasPermission(sprintf('use %s transition', $transition->id())) && $transition->getThirdPartySetting('workbench_email', 'workbench_email_templates', []);
      });
      if (!$transitions) {
        // None remain, nothing to do.
        return;
      }
      // There may be multiple at this point, but given we don't have access
      // to the transition that fired this event, we just pick the first one.
      $transition = reset($transitions);
      /** @var \Drupal\Core\Queue\QueueInterface $queue */
      $queue = $this->queueFactory->get('workbench_email_send' . PluginBase::DERIVATIVE_SEPARATOR . $entity->getEntityTypeId());

      /** @var \Drupal\workbench_email\TemplateInterface $template */
      foreach ($this->entityTypeManager->getStorage('workbench_email_template')->loadMultiple($transition->getThirdPartySetting('workbench_email', 'workbench_email_templates', [])) as $template) {
        if ($template->getBundles() && !in_array($entity->getEntityTypeId() . ':' . $entity->bundle(), $template->getBundles(), TRUE)) {
          // Continue, invalid bundle.
          continue;
        }
        foreach ($this->prepareRecipients($entity, $template) as $to) {
          $queue->createItem(new QueuedEmail($template, $entity->uuid(), $to));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [WorkbenchModerationEvents::STATE_TRANSITION => 'onTransition'];
  }

  /**
   * Prepares the recipient list given the entity and template combination.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity being transitioned.
   * @param \Drupal\workbench_email\TemplateInterface $template
   *   Template being used.
   *
   * @return array
   *   Array of email addresses to send to.
   */
  protected function prepareRecipients(ContentEntityInterface $entity, TemplateInterface $template) {
    $recipients = [];
    if ($template->isAuthor() && $entity instanceof EntityOwnerInterface) {
      if (!$entity->getOwner()->isAnonymous()) {
        $recipients[] = $entity->getOwner()->getEmail();
      }
    }
    foreach ($template->getRoles() as $role) {
      foreach ($this->entityTypeManager->getStorage('user')->loadByProperties([
        'roles' => $role,
        'status' => 1,
      ]) as $account) {
        $recipients[] = $account->getEmail();
      }
    }
    $fields = array_filter($template->getFields(), function($field_name) use ($entity) {
      list($entity_type, $field_name) = explode(':', $field_name, 2);
      return $entity_type === $entity->getEntityTypeId() && $entity->hasField($field_name) && !$entity->{$field_name}->isEmpty();
    });
    foreach ($fields as $field) {
      list(, $field_name) = explode(':', $field, 2);
      /** @var \Drupal\Core\Field\FieldItemInterface $field_item */
      foreach ($entity->{$field_name} as $field_item) {
        $recipients[] = $field_item->get('value')->getValue();
      }
    }
    return array_unique($recipients);
  }


}
