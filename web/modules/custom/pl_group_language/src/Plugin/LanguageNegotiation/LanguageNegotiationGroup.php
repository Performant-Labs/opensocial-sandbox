<?php

namespace Drupal\pl_group_language\Plugin\LanguageNegotiation;

use Drupal\language\LanguageNegotiationMethodBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Language negotiation based on group context.
 *
 * Reads the group ID from the URL path (e.g. /group/123 or /group/123/topics)
 * and returns the group's configured language if set. Falls through to the
 * next negotiation method if no group context or if group language is "und".
 *
 * Note: This plugin parses the raw URL path rather than using route parameters
 * because language negotiation runs before Drupal's route matching.
 *
 * @LanguageNegotiation(
 *   id = "language-group",
 *   name = @Translation("Group language"),
 *   description = @Translation("Use the language configured on the current group."),
 *   weight = 5,
 *   types = {\Drupal\Core\Language\LanguageInterface::TYPE_INTERFACE}
 * )
 */
class LanguageNegotiationGroup extends LanguageNegotiationMethodBase {

  const METHOD_ID = 'language-group';

  /**
   * {@inheritdoc}
   */
  public function getLangcode(Request $request = NULL) {
    if (!$request) {
      return FALSE;
    }

    // Parse the group ID from the URL path.
    // Matches /group/123, /group/123/topics, /group/123/edit, etc.
    $path = $request->getPathInfo();
    if (!preg_match('#^/group/(\d+)#', $path, $matches)) {
      return FALSE;
    }

    $group_id = $matches[1];
    $group = \Drupal::entityTypeManager()->getStorage('group')->load($group_id);

    if (!$group || !method_exists($group, 'hasField')) {
      return FALSE;
    }

    if (!$group->hasField('field_group_language') || $group->get('field_group_language')->isEmpty()) {
      return FALSE;
    }

    $langcode = $group->get('field_group_language')->value;

    // "und" (Language neutral) means no language override.
    if ($langcode === 'und' || $langcode === 'zxx') {
      return FALSE;
    }

    return $langcode;
  }

}
