<?php

namespace Drupal\pl_opensocial_wiki\Plugin\Filter;

use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\node\Entity\Node;

/**
 * Provides a filter to transform [[title]] into wiki links.
 *
 * @Filter(
 *   id = "wikilink_filter",
 *   title = @Translation("Wiki Link Filter"),
 *   description = @Translation("Transforms [[title]] into clickable links to existing nodes."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 * )
 */
class WikiLinkFilter extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    // Regex for [[title]]
    $text = preg_replace_callback('/\[\[(.*?)\]\]/', function ($matches) {
      $title = trim($matches[1]);
      
      // Case-insensitive search for node with this title.
      $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery();
      $nids = $query->condition('title', $title)
        ->accessCheck(FALSE)
        ->range(0, 1)
        ->execute();

      if ($nids) {
        $node = Node::load(reset($nids));
        return '<a href="' . $node->toUrl()->toString() . '">' . $title . '</a>';
      }

      return $matches[0];
    }, $text);

    return new FilterProcessResult($text);
  }

}
