<?php
namespace AllPlayers\Objects;

use stdClass;

/**
 * AllPlayers group.
 */
abstract class Group extends stdClass
{
    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $zip;

    /**
     * @var string
     */
    public $category;

    /**
     * @var string
     */
    public $primary_color;

    /**
     * @var string
     */
    public $secondary_color;
}
