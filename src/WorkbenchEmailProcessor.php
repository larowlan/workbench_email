<?php
/**
 * @file
 * Contains Drupal\workbench_email\WorkbenchEmailProcessor.
 */

namespace Drupal\workbench_email;


use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\Utility\Error;

class WorkbenchEmailProcessor {

  /**
   * Time to process for.
   */
  const PROCESSING_TIME = 30;

  /**
   * The queue service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The queue plugin manager.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected $queueManager;

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a WorkbencEmailProcessor object.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue service.
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface
   *   The queue plugin manager.
   */
  public function __construct(QueueFactory $queue_factory, QueueWorkerManagerInterface $queue_manager, LoggerChannelFactory $logger_factory) {
    $this->queueFactory = $queue_factory;
    $this->queueManager = $queue_manager;
    $this->logger = $logger_factory->get('workbench_email');
  }

  /**
   * Process the queue for a given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to process.
   */
  public function processEntity(EntityInterface $entity) {
    // Make sure every queue exists. There is no harm in trying to recreate
    // an existing queue.
    $queue_name = 'workbench_email_send' . PluginBase::DERIVATIVE_SEPARATOR . $entity->getEntityTypeId();
    $this->queueFactory->get($queue_name)->createQueue();

    $queue_worker = $this->queueManager->createInstance($queue_name, ['entity_type' => $entity->getEntityTypeId()]);
    $queue = $this->queueFactory->get($queue_name);
    $to_release = [];
    $end = time() + static::PROCESSING_TIME;
    while (time() < $end && ($item = $queue->claimItem())) {
      if ($item->data instanceof QueuedEmail && $item->data->getUuid() === $entity->uuid()) {
        try {
          $queue_worker->processItem($item->data);
          $queue->deleteItem($item);
        }
        catch (\Exception $e) {
          // In case of any exception, just log it.
          $this->logger->log(RfcLogLevel::ERROR, '%type: @message in %function (line %line of %file).', Error::decodeException($e));
        }
      }
      else {
        $to_release[] = $item;
      }
    }
    // Put these back into the queue.
    foreach ($to_release as $item) {
      $queue->releaseItem($item);
    }
  }

}
