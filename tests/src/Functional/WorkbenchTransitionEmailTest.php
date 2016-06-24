<?php

namespace Drupal\Tests\workbench_email\Functional;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\simpletest\BlockCreationTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use Drupal\workbench_moderation\Entity\ModerationState;

/**
 * Tests the view access control handler for moderation state entities.
 *
 * @group workbench_moderation
 *
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
class WorkbenchTransitionEmailTest extends BrowserTestBase {

  use AssertMailTrait;
  use BlockCreationTrait;

  /**
   * Test node type.
   *
   * @var \Drupal\node\NodeTypeInterface
   */
  protected $nodeType;

  /**
   * Approver role.
   *
   * @var \Drupal\user\RoleInterface
   */
  protected $approverRole;

  /**
   * Editor role.
   *
   * @var \Drupal\user\RoleInterface
   */
  protected $editorRole;

  /**
   * Approver 1.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $approver1;

  /**
   * Approver 2.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $approver2;

  /**
   * Editor.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $editor;

  /**
   * Admin.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $admin;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'workbench_email',
    'workbench_moderation',
    'node',
    'options',
    'user',
    'system',
    'filter',
    'block',
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    // Place some blocks.
    $this->placeBlock('local_tasks_block', ['id' => 'tabs_block']);
    $this->placeBlock('page_title_block');
    $this->placeBlock('local_actions_block', ['id' => 'actions_block']);
    // Create a node-type and make it moderated.
    $this->nodeType = NodeType::create([
      'type' => 'test',
      'name' => 'Test',
    ]);
    $this->nodeType->setThirdPartySetting('workbench_moderation', 'enabled', TRUE);
    $states = array_keys(ModerationState::loadMultiple());
    $this->nodeType->setThirdPartySetting('workbench_moderation', 'allowed_moderation_state', array_combine($states, $states));
    $this->nodeType->save();
    // Create an approver role and two users.
    $this->approverRole = $this->drupalCreateRole([
      'view any unpublished content',
      'access content',
      'edit any test content',
      'create test content',
      'view test revisions',
      'use draft_needs_review transition',
      'use needs_review_published transition',
    ], 'approver', 'Approver');
    $this->approver1 = $this->drupalCreateUser();
    $this->approver1->addRole('approver');
    $this->approver1->save();
    $this->approver2 = $this->drupalCreateUser();
    $this->approver2->addRole('approver');
    $this->approver2->save();
    // Create a editor role and user.
    $this->editorRole = $this->drupalCreateRole([
      'view any unpublished content',
      'access content',
      'edit any test content',
      'create test content',
      'view test revisions',
      'use draft_needs_review transition',
    ], 'editor', 'Editor');
    $this->editor = $this->drupalCreateUser();
    $this->editor->addRole('editor');
    $this->editor->save();
    // Create an admin user.
    $this->admin = $this->drupalCreateUser([
      'administer moderation state transitions',
      'administer workbench_email templates',
      'access administration pages',
    ]);
    // Add an email field notify to the node-type.
    FieldStorageConfig::create([
      'cardinality' => 1,
      'id' => 'node.field_email',
      'entity_type' => 'node',
      'field_name' => 'field_email',
      'type' => 'email',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_email',
      'bundle' => 'test',
      'label' => 'Notify',
      'entity_type' => 'node',
    ]);
  }

  /**
   * Test administration.
   */
  public function testEndToEnd() {
    // Create some templates as admin.
    // - stuff got approved; and
    // - stuff needs review.
    $this->drupalLogin($this->admin);
    $this->drupalGet('admin/structure/workbench-moderation');
    $page = $this->getSession()->getPage();
    $page->clickLink('Email Templates');
    $assert = $this->assertSession();
    $this->assertEquals($this->getSession()->getCurrentUrl(), Url::fromUri('internal:/admin/structure/workbench-moderation/workbench-email-template')->setOption('absolute', TRUE)->toString());
    $assert->pageTextContains('Email Template');
    $page->clickLink('Add Email Template');
    $this->submitForm([
      'id' => 'approved',
      'label' => 'Content approved',
      'body[value]' => 'Content with title [node:title] was approved. You can view it at [node:url].',
      'subject' => 'Content approved',
    ], t('Save'));
    $assert->pageTextContains('Created the Content approved Email Template');
    $page->clickLink('Add Email Template');
    $this->submitForm([
      'id' => 'needs_review',
      'label' => 'Content needs review',
      'body[value]' => 'Content with title [node:title] needs review. You can view it at [node:url].',
      'subject' => 'Content needs review',
    ], t('Save'));
    $assert->pageTextContains('Created the Content needs review Email Template');
    // Edit the template.
    $page->clickLink('Content needs review');
    $this->submitForm([
      'id' => 'needs_review',
      'label' => 'Content needs review',
      'body[value]' => 'Content with title [node:title] needs review. You can view it at [node:url].',
      'subject' => 'Content needs review',
    ], t('Save'));
    $assert->pageTextContains('Saved the Content needs review Email Template');
    // Edit the transition from needs review to published and add email config:
    // - email author
    // - email someone in notifier field
    // Edit the transition from draft to needs review and add email config:
    // - email approver
    // Create a node and add to the notifier field.
    // Transition to needs review.
    // Check mail goes to approvers.
    // Login as approver and transition to approved.
    // Check mail goes to author and notifier.
  }
}

