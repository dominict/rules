<?php

/**
 * @file
 * Contains \Drupal\rules\Plugin\RulesExpression\RulesLoop.
 */

namespace Drupal\rules\Plugin\RulesExpression;

use Drupal\Core\TypedData\ListDataDefinitionInterface;
use Drupal\rules\Engine\ActionExpressionContainer;
use Drupal\rules\Engine\ExecutionMetadataStateInterface;
use Drupal\rules\Engine\ExecutionStateInterface;
use Drupal\rules\Engine\ExpressionInterface;
use Drupal\rules\Engine\IntegrityViolationList;
use Drupal\rules\Exception\RulesIntegrityException;

/**
 * Holds a set of actions that are executed over the iteration of a list.
 *
 * @RulesExpression(
 *   id = "rules_loop",
 *   label = @Translation("Loop")
 * )
 */
class RulesLoop extends ActionExpressionContainer {

  /**
   * {@inheritdoc}
   */
  public function executeWithState(ExecutionStateInterface $state) {
    $list_data = $state->fetchDataByPropertyPath($this->configuration['list']);
    // Use a configured list item variable name, otherwise fall back to just
    // 'list_item' as variable name.
    $list_item_name = isset($this->configuration['list_item']) ? $this->configuration['list_item'] : 'list_item';

    foreach ($list_data as $item) {
      $state->setVariableData($list_item_name, $item);
      foreach ($this->actions as $action) {
        $action->executeWithState($state);
      }
    }
    // After the loop the list item is out of scope and cannot be used by any
    // following actions.
    $state->removeVariable($list_item_name);
  }

  /**
   * {@inheritdoc}
   */
  public function checkIntegrity(ExecutionMetadataStateInterface $metadata_state) {
    $violation_list = new IntegrityViolationList();

    if (empty($this->configuration['list'])) {
      $violation_list->addViolationWithMessage($this->t('List variable is missing.'));
      return $violation_list;
    }

    try {
      $list_definition = $metadata_state->fetchDefinitionByPropertyPath($this->configuration['list']);
    }
    catch (RulesIntegrityException $e) {
      $violation_list->addViolationWithMessage($this->t('List variable %list does not exist. @message', [
        '%list' => $this->configuration['list'],
        '@message' => $e->getMessage(),
      ]));
      return $violation_list;
    }

    $list_item_name = isset($this->configuration['list_item']) ? $this->configuration['list_item'] : 'list_item';
    if ($metadata_state->hasDataDefinition($list_item_name)) {
      $violation_list->addViolationWithMessage($this->t('List item name %name conflicts with an existing variable.', [
        '%name' => $list_item_name,
      ]));
      return $violation_list;
    }

    if ($list_definition instanceof ListDataDefinitionInterface) {
      $list_item_definition = $list_definition->getItemDefinition();
      $metadata_state->setDataDefinition($list_item_name, $list_item_definition);

      $violation_list = parent::checkIntegrity($metadata_state);

      // Remove the list item variable after the loop, it is out of scope now.
      $metadata_state->removeDataDefinition($list_item_name);
      return $violation_list;
    }

    $violation_list->addViolationWithMessage($this->t('The data type of list variable %list is not a list.', [
      '%list' => $this->configuration['list'],
    ]));
    return $violation_list;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareExecutionMetadataState(ExecutionMetadataStateInterface $metadata_state, ExpressionInterface $until = NULL) {
    if ($until && $this->getUuid() === $until->getUuid()) {
      return TRUE;
    }

    $list_item_name = isset($this->configuration['list_item']) ? $this->configuration['list_item'] : 'list_item';
    try {
      $list_definition = $metadata_state->fetchDefinitionByPropertyPath($this->configuration['list']);
      $list_item_definition = $list_definition->getItemDefinition();
      $metadata_state->setDataDefinition($list_item_name, $list_item_definition);
    }
    catch (RulesIntegrityException $e) {
      // Silently eat the exception: we just continue without adding the list
      // item definition to the state.
    }

    if ($until) {
      foreach ($this->actions as $action) {
        if ($action->getUuid() === $until->getUuid()) {
          return TRUE;
        }
        $found = $action->prepareExecutionMetadataState($metadata_state, $until);
        if ($found) {
          return TRUE;
        }
      }
      // Remove the list item variable after the loop, it is out of scope now.
      $metadata_state->removeDataDefinition($list_item_name);
      return FALSE;
    }

    foreach ($this->actions as $action) {
      $action->prepareExecutionMetadataState($metadata_state);
    }
    // Remove the list item variable after the loop, it is out of scope now.
    $metadata_state->removeDataDefinition($list_item_name);
    return TRUE;
  }

}