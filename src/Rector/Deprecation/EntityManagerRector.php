<?php

namespace DrupalRector\Rector\Deprecation;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;

/**
 * Replaces deprecated \Drupal::entityManager() calls.
 *
 * See https://www.drupal.org/node/2549139 for change record.
 */
final class EntityManagerRector extends AbstractRector {

  /**
   * @inheritdoc
   */
  public function getDefinition(): RectorDefinition {
    return new RectorDefinition('Fixes deprecated \Drupal::entityManager() calls',[
      new CodeSample(
        <<<'CODE_BEFORE'
$entity_manager = \Drupal::entityManager();
CODE_BEFORE
        ,
        <<<'CODE_AFTER'
$entity_manager = \Drupal::entityTypeManager();
CODE_AFTER
      )
    ]);
  }

  /**
   * @inheritdoc
   */
  public function getNodeTypes(): array {
    return [
      Node\Expr\StaticCall::class,
// TODO: Can we try to update non-static calls as well such as with controllers that extend ControllerBase?.
//      Node\Expr\FuncCall::class,
//      Node\Expr\MethodCall::class,
    ];
  }

  /**
   * @inheritdoc
   */
  public function refactor(Node $node): ?Node {

    if ($node instanceof Node\Expr\StaticCall) {
      /** @var Node\Expr\StaticCall $node */
      if ($node->name instanceof Node\Identifier && (string) $node->class === 'Drupal' && (string) $node->name === 'entityManager') {
        $service = 'entity_type.manager';

        // If we call a method on `entityManager`, we need to check that method and we can call the correct service that the method uses.
        // TODO: Generalize this if we are going to test non-static calls too.
        if ($node->hasAttribute('nextNode')) {
          $next_node = $node->getAttribute('nextNode');

          switch ($this->getName($next_node)) {
            case 'getEntityTypeLabels':
            case 'getEntityTypeFromClass':
              $service = 'entity_type.repository';
              break;

            case 'getAllBundleInfo':
            case 'getBundleInfo':
            case 'clearCachedBundles':
              $service = 'entity_type.bundle.info';
              break;

            case 'getAllViewModes':
            case 'getViewModes':
            case 'getAllFormModes':
            case 'getFormModes':
            case 'getViewModeOptions':
            case 'getFormModeOptions':
            case 'getViewModeOptionsByBundle':
            case 'getFormModeOptionsByBundle':
            case 'clearDisplayModeInfo':
              $service = 'entity_display.repository';
              break;

            case 'getBaseFieldDefinitions':
            case 'getFieldDefinitions':
            case 'getFieldStorageDefinitions':
            case 'getFieldMap':
            case 'setFieldMap':
            case 'getFieldMapByFieldType':
            case 'clearCachedFieldDefinitions':
            case 'useCaches':
            case 'getExtraFields':
              $service = 'entity_field.manager';
              break;

            case 'onEntityTypeCreate':
            case 'onEntityTypeUpdate':
            case 'onEntityTypeDelete':
              $service = 'entity_type.listener';
              break;

            case 'getLastInstalledDefinition':
            case 'getLastInstalledFieldStorageDefinitions':
              $service = 'entity_definition.repository';
              break;

            case 'loadEntityByUuid':
            case 'loadEntityByConfigTarget':
            case 'getTranslationFromContext':
              $service = 'entity.repository';
              break;

            case 'onBundleCreate':
            case 'onBundleRename':
            case 'onBundleDelete':
              $service = 'entity_bundle.listener';
              break;

            case 'onFieldStorageDefinitionCreate':
            case 'onFieldStorageDefinitionUpdate':
            case 'onFieldStorageDefinitionDelete':
              $service = 'field_storage_definition.listener';
              break;

            case 'onFieldDefinitionCreate':
            case 'onFieldDefinitionUpdate':
            case 'onFieldDefinitionDelete':
              $service = 'field_definition.listener';
              break;

            case 'getAccessControlHandler':
            case 'getStorage':
            case 'getViewBuilder':
            case 'getListBuilder':
            case 'getFormObject':
            case 'getRouteProviders':
            case 'hasHandler':
            case 'getHandler':
            case 'createHandlerInstance':
            case 'getDefinition':
            case 'getDefinitions':
            default:
              $service = 'entity_type.manager';
              break;
          }
        }

        // This creates a service call like `\Drupal::service('entity_type.manager').
        // This doesn't use dependency injection, but it should work.
        $node = new Node\Expr\StaticCall(new Node\Name\FullyQualified('Drupal'), 'service', [new Node\Arg(new Node\Scalar\String_($service))], $node->getAttributes());
      }
    }

    return $node;
  }
}
