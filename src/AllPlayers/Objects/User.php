<?php
namespace AllPlayers\Objects;

use DateTime;
use stdClass;

/**
 * Defines standard user fields.
 */
class User extends stdClass
{
    /**
     * @var string
     */
    public $email;

    /**
     * @var string
     */
    public $password;

    /**
     * @var string
     */
    public $first_name;

    /**
     * @var string
     */
    public $last_name;

    /**
     * @var string
     */
    public $gender;

    /**
     * @var DateTime
     */
    public $birthdate;

    /**
     * @var string
     */
    public $uuid;
}
