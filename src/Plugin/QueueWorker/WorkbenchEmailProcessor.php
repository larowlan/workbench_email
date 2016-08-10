<?php

namespace Drupal\workbench_email\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Utility\Token;
use Drupal\workbench_email\QueuedEmail;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sends emails notifications for workbench events.
 *
 * @QueueWorker(
 *   id = "workbench_email_send",
 *   title = @Translation("Sends workbench email notifications"),
 *   cron = {"time" = 60},
 *   deriver = "\Drupal\workbench_email\Plugin\Derivative\WorkbenchEmailDeriver"
 * )
 */
class WorkbenchEmailProcessor extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Entity type ID.
   *
   * @var string
   */
  protected $targetEntityType;

  /**
   * Drupal\Core\Mail\MailManager definition.
   *
   * @var \Drupal\Core\Mail\MailManager
   */
  protected $mailManager;

  /**
   * Renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Constructs a new WorkbenchEmailProcessor object.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Definition.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   Mail manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MailManagerInterface $mail_manager, EntityRepositoryInterface $entity_repository, Token $token, RendererInterface $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->mailManager = $mail_manager;
    $this->targetEntityType = $configuration['entity_type'];
    $this->entityRepository = $entity_repository;
    $this->token = $token;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.mail'),
      $container->get('entity.repository'),
      $container->get('token'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if ($data instanceof QueuedEmail) {
      $template = $data->getTemplate();
      $uuid = $data->getUuid();
      if ($entity = $this->entityRepository->loadEntityByUuid($this->targetEntityType, $uuid)) {

      }
      $body = $template->getBody();
      $subject = $template->getSubject();
      $body['value'] = $this->token->replace($body['value'], [$entity->getEntityTypeId() => $entity]);
      $body = $this->checkMarkup($body['value'], $body['format']);

      // Send the email.
      $this->mailManager->mail('workbench_email', 'template::' . $template->id(), $data->getTo(), LanguageInterface::LANGCODE_DEFAULT, [
        'body' => $body,
        'template' => $template,
        'subject' => $subject,
      ]);
    }

  }

  /**
   * Converts message body to markup applying filter.
   *
   * @param string $text
   *   Text to filter.
   * @param string $format_id
   *   Format ID.
   * @param string $langcode
   *   Langcode.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   Filtered markup.
   */
  protected function checkMarkup($text, $format_id, $langcode = LanguageInterface::LANGCODE_DEFAULT) {
    $build = array(
      '#type' => 'processed_text',
      '#text' => $text,
      '#format' => $format_id,
      '#filter_types_to_skip' => [],
      '#langcode' => $langcode,
    );
    return $this->renderer->renderPlain($build);
  }

}
