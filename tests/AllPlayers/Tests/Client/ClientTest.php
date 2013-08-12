<?php
namespace AllPlayers\Tests\Client;

use AllPlayers\Client;
use Guzzle\Tests\GuzzleTestCase;
use AllPlayers\Tests\Client\User\Fixtures\RandomUser;

class ClientTest extends GuzzleTestCase
{
    public $url = NULL;
    public $client = Client;

    /**
     * Do no actions here, this is called for each test method.
     */
    function __construct() {
        $this->url = (isset($_SERVER['API_HOST'])) ? $_SERVER['API_HOST'] : 'https://www.pdup.allplayers.com';
        $this->client = new Client($this->url);
    }

    // Test for creating an AllPlayers client.
    public function testBuilderCreatesClient()
    {
        $this->assertInstanceOf('AllPlayers\Client', $this->client);
    }

    // Test user creating account for self.
    public function testCreateUser()
    {
        $client = $this->client;
        $r_user = new RandomUser();
        $user = $client->userCreateUser($r_user->firstname, $r_user->lastname, $r_user->email, $r_user->gender, $r_user->birthday);
        $this->assertEquals($user->firstname, $r_user->firstname);
        $this->assertEquals($user->lastname, $r_user->lastname);
        $this->assertEquals($user->email, $r_user->email);
        $this->assertEquals(strtoupper(substr($user->gender, 0, 1)), strtoupper($r_user->gender));
    }
}
