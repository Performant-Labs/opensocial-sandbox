<?php

namespace Drupal\pl_discovery\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\group\Entity\GroupInterface;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for iCal feed endpoints.
 */
class IcalController extends ControllerBase {

  /**
   * Returns all upcoming site events as iCal.
   */
  public function siteEvents(): Response {
    $events = $this->loadEvents();
    return $this->buildIcalResponse($events);
  }

  /**
   * Returns events for a specific group as iCal.
   */
  public function groupEvents($group): Response {
    // Load group entity if numeric ID is passed.
    if (is_numeric($group)) {
      $group = \Drupal::entityTypeManager()->getStorage('group')->load($group);
    }

    if (!$group instanceof GroupInterface) {
      return new Response('Group not found', 404, ['Content-Type' => 'text/plain']);
    }

    $events = $this->loadGroupEvents($group);
    return $this->buildIcalResponse($events);
  }

  /**
   * Returns events that a user has enrolled in as iCal.
   */
  public function userEvents($user): Response {
    if (is_numeric($user)) {
      $user = \Drupal::entityTypeManager()->getStorage('user')->load($user);
    }

    if (!$user instanceof UserInterface) {
      return new Response('User not found', 404, ['Content-Type' => 'text/plain']);
    }

    $events = $this->loadUserEvents($user);
    return $this->buildIcalResponse($events);
  }

  /**
   * Loads all published event nodes.
   */
  protected function loadEvents(int $limit = 100): array {
    $nids = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'event')
      ->condition('status', 1)
      ->sort('field_event_date', 'ASC')
      ->range(0, $limit)
      ->execute();

    return $nids ? \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids) : [];
  }

  /**
   * Loads event nodes belonging to a specific group.
   */
  protected function loadGroupEvents(GroupInterface $group): array {
    $database = \Drupal::database();
    $events = [];

    // Query via group_relationship_field_data for events in this group.
    if ($database->schema()->tableExists('group_relationship_field_data')) {
      $nids = $database->select('group_relationship_field_data', 'gr')
        ->fields('gr', ['entity_id'])
        ->condition('gr.gid', $group->id())
        ->condition('gr.type', '%event%', 'LIKE')
        ->execute()
        ->fetchCol();

      if (!empty($nids)) {
        $node_storage = \Drupal::entityTypeManager()->getStorage('node');
        $nodes = $node_storage->loadMultiple($nids);
        foreach ($nodes as $node) {
          if ($node && $node->getType() === 'event' && $node->isPublished()) {
            $events[] = $node;
          }
        }
      }
    }

    // Fallback: try the Group API if the table query returned nothing.
    if (empty($events)) {
      try {
        $contents = $group->getRelatedEntities();
        foreach ($contents as $entity) {
          if ($entity instanceof \Drupal\node\NodeInterface
              && $entity->getType() === 'event'
              && $entity->isPublished()) {
            $events[] = $entity;
          }
        }
      }
      catch (\Exception $e) {
        // Silently continue — the group may have no content.
      }
    }

    return $events;
  }

  /**
   * Loads events a user has enrolled in.
   *
   * Open Social stores enrollments in the event_enrollment entity type.
   */
  protected function loadUserEvents(UserInterface $user): array {
    $database = \Drupal::database();
    $events = [];

    // Try to query event_enrollment entities.
    if ($database->schema()->tableExists('event_enrollment_field_data')) {
      $nids = $database->select('event_enrollment_field_data', 'e')
        ->fields('e', ['field_event'])
        ->condition('e.user_id', $user->id())
        ->condition('e.status', 1)
        ->execute()
        ->fetchCol();

      if (!empty($nids)) {
        $events = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
      }
    }

    return $events;
  }

  /**
   * Builds an iCal Response from a list of event nodes.
   */
  protected function buildIcalResponse(array $events): Response {
    $site_name = \Drupal::config('system.site')->get('name');
    $host = \Drupal::request()->getHost();

    $lines = [];
    $lines[] = 'BEGIN:VCALENDAR';
    $lines[] = 'VERSION:2.0';
    $lines[] = 'PRODID:-//' . $site_name . '//Events//EN';
    $lines[] = 'CALSCALE:GREGORIAN';
    $lines[] = 'METHOD:PUBLISH';

    foreach ($events as $event) {
      $lines[] = 'BEGIN:VEVENT';
      $lines[] = 'UID:event-' . $event->id() . '@' . $host;
      $lines[] = 'SUMMARY:' . $this->escapeIcalText($event->getTitle());

      // Event date.
      if ($event->hasField('field_event_date') && !$event->get('field_event_date')->isEmpty()) {
        $start_value = $event->get('field_event_date')->value;
        if ($start_value) {
          $dt = new DrupalDateTime($start_value, 'UTC');
          $lines[] = 'DTSTART:' . $dt->format('Ymd\THis\Z');
        }
      }

      // End date.
      if ($event->hasField('field_event_date_end') && !$event->get('field_event_date_end')->isEmpty()) {
        $end_value = $event->get('field_event_date_end')->value;
        if ($end_value) {
          $dt = new DrupalDateTime($end_value, 'UTC');
          $lines[] = 'DTEND:' . $dt->format('Ymd\THis\Z');
        }
      }

      // Description (strip HTML).
      if ($event->hasField('body') && !$event->get('body')->isEmpty()) {
        $description = strip_tags($event->get('body')->value);
        $lines[] = 'DESCRIPTION:' . $this->escapeIcalText(mb_substr($description, 0, 500));
      }

      // URL.
      $lines[] = 'URL:' . $event->toUrl('canonical', ['absolute' => TRUE])->toString();
      $lines[] = 'END:VEVENT';
    }

    $lines[] = 'END:VCALENDAR';

    $output = implode("\r\n", $lines) . "\r\n";

    return new Response($output, 200, [
      'Content-Type' => 'text/calendar; charset=utf-8',
      'Content-Disposition' => 'inline; filename="events.ics"',
    ]);
  }

  /**
   * Escapes text for iCal format.
   */
  protected function escapeIcalText(string $text): string {
    $text = str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $text);
    return $text;
  }

}
