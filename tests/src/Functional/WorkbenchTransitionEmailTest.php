<?php

namespace Drupal\Tests\workbench_email\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeTypeInterface;
use Drupal\simpletest\BlockCreationTrait;
use Drupal\simpletest\NodeCreationTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\workbench_email\Entity\Template;
use Drupal\workbench_moderation\Entity\ModerationState;
use Drupal\workbench_moderation\Entity\ModerationStateTransition;

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
  use NodeCreationTrait;

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
    // Create two node-types and make them moderated.
    $this->nodeType = NodeType::create([
      'type' => 'test',
      'name' => 'Test',
    ]);
    $this->setupModerationForNodeType($this->nodeType);
    $this->nodeType = NodeType::create([
      'type' => 'another',
      'name' => 'Another Test',
    ]);
    $this->setupModerationForNodeType($this->nodeType);
    // Create an approver role and two users.
    $this->approverRole = $this->drupalCreateRole([
      'view any unpublished content',
      'access content',
      'edit any test content',
      'create test content',
      'view test revisions',
      'edit any another content',
      'create another content',
      'view another revisions',
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
      'edit any another content',
      'create another content',
      'view another revisions',
      'use draft_needs_review transition',
      'use draft_draft transition',
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
      'entity_type' => 'node',
      'field_name' => 'field_email',
      'type' => 'email',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_email',
      'bundle' => 'test',
      'label' => 'Notify',
      'entity_type' => 'node',
    ])->save();
    if (!$entity_form_display = EntityFormDisplay::load('node.test.default')) {
      $entity_form_display = EntityFormDisplay::create(array(
        'targetEntityType' => 'node',
        'bundle' => 'test',
        'mode' => 'default',
        'status' => TRUE,
      ));
    }
    $entity_form_display->setComponent('field_email', ['type' => 'email_default'])->save();
  }

  /**
   * Outputs the page for debugging sake.
   */
  protected function outputPage() {
    $url = $this->getSession()->getCurrentUrl();
    $html_output = 'GET request to: ' . $url .
      '<hr />Ending URL: ' . $this->getSession()->getCurrentUrl();
    $html_output .= '<hr />' . $this->getSession()->getPage()->getHtml();
    $html_output .= $this->getHtmlOutputHeaders();
    $this->htmlOutput($html_output);
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
    $this->outputPage();
    $this->submitForm([
      'id' => 'approved',
      'label' => 'Content approved',
      'body[value]' => 'Content with title [node:title] was approved. You can view it at [node:url].',
      'subject' => 'Content approved',
      'fields[node:field_email]' => TRUE,
      'author' => TRUE,
    ], t('Save'));
    $assert->pageTextContains('Created the Content approved Email Template');
    $page->clickLink('Add Email Template');
    $this->outputPage();
    $this->submitForm([
      'id' => 'needs_review',
      'label' => 'Content needs review',
      'body[value]' => 'Content with title [node:title] needs review. You can view it at [node:url].',
      'subject' => 'Content needs review',
      'roles[approver]' => TRUE,
      'bundles[node:test]' => TRUE,
    ], t('Save'));
    $assert->pageTextContains('Created the Content needs review Email Template');
    // Test dependencies.
    $approver = Template::load('needs_review');
    $dependencies = $approver->calculateDependencies()->getDependencies()['config'];
    $this->assertTrue(in_array('user.role.approver', $dependencies, TRUE));
    $this->assertTrue(in_array('node.type.test', $dependencies, TRUE));
    $approver = Template::load('approved');
    $dependencies = $approver->calculateDependencies()->getDependencies()['config'];
    $this->assertTrue(in_array('field.storage.node.field_email', $dependencies, TRUE));
    // Edit the template and test values persisted.
    $page->clickLink('Content approved');
    $this->outputPage();
    $assert->checkboxChecked('Notify (Content)');
    $this->getSession()->back();
    // Test editing a template.
    $page->clickLink('Content needs review');
    $this->outputPage();
    $assert->checkboxChecked('Approver');
    $this->submitForm([
      'label' => 'Content needs review',
      'body[value]' => 'Content with title [node:title] needs review. You can view it at [node:url].',
      'subject' => 'Content needs review',
    ], t('Save'));
    $assert->pageTextContains('Saved the Content needs review Email Template');
    // Edit the transition from needs review to published and use the
    // needs_review email template.
    $this->drupalGet('admin/structure/workbench-moderation/transitions/needs_review_published');
    $this->submitForm([
      'workbench_email_templates[approved]' => TRUE,
    ], t('Save'));
    \Drupal::entityTypeManager()->getStorage('moderation_state_transition')->resetCache();
    $transition = ModerationStateTransition::load('needs_review_published');
    $this->assertEquals(['approved' => 'approved'], $transition->getThirdPartySetting('workbench_email', 'workbench_email_templates'));
    $dependencies = $transition->calculateDependencies()->getDependencies()['config'];
    $this->assertTrue(in_array('workbench_email.workbench_email_template.approved', $dependencies, TRUE));
    // Edit the transition from draft to needs review and add email config:
    // approver template.
    $this->drupalGet('admin/structure/workbench-moderation/transitions/draft_needs_review');
    $this->submitForm([
      'workbench_email_templates[needs_review]' => TRUE,
    ], t('Save'));
    // Create a node and add to the notifier field.
    $this->drupalLogin($this->editor);
    $this->drupalGet('node/add/test');
    $this->submitForm([
      'title[0][value]' => 'Test node',
      'field_email[0][value]' => 'foo@example.com',
    ], 'Save and Create New Draft');
    $node = $this->getNodeByTitle('Test node');
    // Transition to needs review.
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->submitForm([], 'Save and Request Review');
    // Check mail goes to approvers.
    $captured_emails = $this->container->get('state')->get('system.test_mail_collector') ?: [];
    $last = end($captured_emails);
    $prev = prev($captured_emails);
    $this->assertTrue($prev && isset($prev['to']) && $prev['to'] == $this->approver1->mail->value);
    $this->assertTrue($last && isset($last['to']) && $last['to'] == $this->approver2->mail->value);
    $this->assertEquals('Content needs review', $last['subject']);
    $this->assertEquals('Content needs review', $prev['subject']);
    $this->assertContains(sprintf('Content with title %s needs review. You can view it at %s', $node->label(), $node->toUrl('canonical', ['absolute' => TRUE])->toString()), preg_replace('/\s+/', ' ', $prev['body']));
    $this->assertContains(sprintf('Content with title %s needs review. You can view it at %s', $node->label(), $node->toUrl('canonical', ['absolute' => TRUE])->toString()), preg_replace('/\s+/', ' ', $last['body']));
    // Login as approver and transition to approved.
    $this->drupalLogin($this->approver1);
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->submitForm([], 'Save and Publish');
    // Check mail goes to author and notifier.
    $captured_emails = $this->container->get('state')->get('system.test_mail_collector') ?: [];
    $last = end($captured_emails);
    $prev = prev($captured_emails);
    $this->assertTrue($prev && isset($prev['to']) && $prev['to'] == $this->editor->getEmail());
    $this->assertTrue($last && isset($last['to']) && $last['to'] == 'foo@example.com');
    $this->assertEquals('Content approved', $last['subject']);
    $this->assertEquals('Content approved', $prev['subject']);
    $this->assertContains(sprintf('Content with title %s was approved. You can view it at %s', $node->label(), $node->toUrl('canonical', ['absolute' => TRUE])->toString()), preg_replace('/\s+/', ' ', $prev['body']));
    $this->assertContains(sprintf('Content with title %s was approved. You can view it at %s', $node->label(), $node->toUrl('canonical', ['absolute' => TRUE])->toString()), preg_replace('/\s+/', ' ', $last['body']));
    // Try with the other node type, that isn't enabled.
    // Log back in as editor.
    $this->drupalLogin($this->editor);
    // Reset mail.
    $this->container->get('state')->set('system.test_mail_collector', []);
    $this->drupalGet('node/add/another');
    $this->submitForm([
      'title[0][value]' => 'Another test node',
    ], 'Save and Create New Draft');
    $node = $this->getNodeByTitle('Another test node');
    // Transition to needs review.
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->submitForm([], 'Save and Request Review');
    // No mail should be sent.
    $captured_emails = $this->container->get('state')->get('system.test_mail_collector') ?: [];
    $this->assertEmpty($captured_emails);
  }

  /**
   * Enables moderation for a given node type.
   *
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   Node type to enable moderation for.
   */
  protected function setupModerationForNodeType(NodeTypeInterface $node_type) {
    $node_type->setThirdPartySetting('workbench_moderation', 'enabled', TRUE);
    $states = array_keys(ModerationState::loadMultiple());
    $node_type->setThirdPartySetting('workbench_moderation', 'allowed_moderation_states', $states);
    $node_type->setThirdPartySetting('workbench_moderation', 'default_moderation_state', 'draft');
    $node_type->save();
  }

}

