<?php

namespace Drupal\Tests\workbench_email\Functional;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\node\Entity\NodeType;
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
  ];

  /**
   * Test administration.
   */
  public function testEndToEnd() {
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
    ]);
    // Create some templates as admin.
    // - stuff got approved
    // - stuff needs review
    // Add an email field notify to the node-type.

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

