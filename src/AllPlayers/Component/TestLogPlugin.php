<?php
namespace AllPlayers\Component;

use Guzzle\Plugin\Log\LogPlugin;
use Guzzle\Log\MonologLogAdapter;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * Quick Guzzle LogPlugin for automated testing or CLI scripts.
 *
 * Intended for use with tools like PHPUnit, should probably be reserved for
 * use only when the user passes the --debug flag or analogue.
 *
 * Requires Monolog
 * @link https://github.com/Seldaek/monolog
 */
class TestLogPlugin extends LogPlugin {

  /**
   * @param string $name
   *   Logger name, will be prefixed to messgages.
   * @param int $settings
   *   Bitwise LogPlugin verbosity level.
   *   @see LogPlugin::LOG_CONTEXT = 1;
   *   @see LogPlugin::LOG_HEADERS = 2;
   *   @see LogPlugin::LOG_BODY = 4;
   *   @see LogPlugin::LOG_DEBUG = 8;
   *   @see LogPlugin::LOG_VERBOSE = 15;
   */
  public function __construct($name = 'AllPlayers', $settings = LogPlugin::LOG_CONTEXT, Logger $logger = null) {
    if (!$logger) {
      $logger = new Logger($name);
      $logger->pushHandler(new StreamHandler('php://stdout'));
    }
    parent::__construct(new MonologLogAdapter($logger), $settings);
  }
}
