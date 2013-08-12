<?php
namespace AllPlayers\Service;

use AllPlayers\Client;

/**
 * Abstract base class for API Services using Client.
 */
abstract class ClientService
{
  /**
   * @var Client
   */
  protected $client;

  /**
   * @param Client $client
   *   Client to handle service requests.
   */
  public function __construct(Client $client)
  {
      $this->client = $client;
  }
}
