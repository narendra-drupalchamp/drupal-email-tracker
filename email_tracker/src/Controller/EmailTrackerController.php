<?php

namespace Drupal\email_tracker\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Catalog usage in a class method.
 */
class EmailTrackerController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The time interface.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * GuzzleHttp\ClientInterface definition.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  public function __construct(Connection $database, TimeInterface $time, RequestStack $request_stack) {
    $this->database = $database;
    $this->time = $time;
    $this->requestStack = $request_stack;
  }

  /**
   * Creates an instance of this class.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('datetime.time'),
      $container->get('request_stack')
    );
  }

  /**
   * Tracks email open event.
   *
   * @param string $uuid
   *   The tracking UUID.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Returns tracking pixel response.
   */
  public function open($uuid) {
    $request = $this->requestStack->getCurrentRequest();

    $mail = $this->database->select('email_tracker_mail', 'm')
      ->fields('m', ['id'])
      ->condition('uuid', $uuid)
      ->execute()
      ->fetchObject();

    if ($mail) {
      $this->database->update('email_tracker_mail')
        ->expression('opens', 'opens + 1')
        ->condition('id', $mail->id)
        ->execute();

      $this->database->insert('email_tracker_event')
        ->fields([
          'mail_id' => $mail->id,
          'event_type' => 'open',
          'event_time' => $this->time->getRequestTime(),
          'ip' => $request->getClientIp(),
          'user_agent' => $request->headers->get('User-Agent'),
        ])
        ->execute();
    }

    $response = new Response();
    $response->headers->set('Content-Type', 'image/gif');

    return $response;
  }

  /**
   * Tracks email click event.
   */
  public function click($uuid) {
    $request = $this->requestStack->getCurrentRequest();

    $url = base64_decode($request->query->get('url'));

    $mail = $this->database->select('email_tracker_mail', 'm')
      ->fields('m', ['id'])
      ->condition('uuid', $uuid)
      ->execute()
      ->fetchObject();

    if ($mail) {
      $this->database->update('email_tracker_mail')
        ->expression('clicks', 'clicks + 1')
        ->condition('id', $mail->id)
        ->execute();

      $this->database->insert('email_tracker_event')
        ->fields([
          'mail_id' => $mail->id,
          'event_type' => 'click',
          'event_time' => $this->time->getRequestTime(),
          'ip' => $request->getClientIp(),
          'user_agent' => $request->headers->get('User-Agent'),
          'url' => $url,
        ])
        ->execute();
    }

    return new RedirectResponse($url);
  }

}
