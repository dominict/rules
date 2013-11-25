<?php

/**
 * @file
 * Contains Drupal\rules\Plugin\rules\RulesAnd.
 */

namespace Drupal\rules\Plugin\rules;

/**
 * Evaluates a group of conditions with a logical AND.
 *
 * @RulesElement(
 *   id = "rules_and",
 *   label = @Translation("A logical And condition")
 * )
 */
class RulesAnd extends RulesConditionContainer {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    foreach ($this->conditions as $condition) {
      // @todo this is obscure, a consdition plugin should include the negated
      // state in its execution implementation.
      if (!($condition->execute() xor $condition->isNegated())) {
        return FALSE;
      }
    }
    // An empty AND should return FALSE, otherwise all conditions evaluated to
    // TRUE and we return TRUE.
    return !empty($this->conditions);
  }

}
