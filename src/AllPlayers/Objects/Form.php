<?php
namespace AllPlayers\Objects;

use stdClass;

/**
 * AllPlayers webform.
 */
abstract class Form extends stdClass {

  // Setup some constant field states.
  const DO_NOT_COLLECT = 0;
  const OPTIONAL = 1;
  const REQUIRED = 2;

  /**
   * @var string
   */
  public $title;

  /**
   * @var array
   */
  public $groups;

  /**
   * @var array
   */
  public $fields = array(
    'firstname' => Form::DO_NOT_COLLECT,
    'lastname' => Form::DO_NOT_COLLECT,
    'middle-name' => Form::DO_NOT_COLLECT,
    'nickname' => Form::DO_NOT_COLLECT,
    'address' => Form::DO_NOT_COLLECT,
    'email' => Form::DO_NOT_COLLECT,
    'household' => Form::DO_NOT_COLLECT,
    'siblings' => Form::DO_NOT_COLLECT,
    'lives-with' => Form::DO_NOT_COLLECT,
    'computer-access' => Form::DO_NOT_COLLECT,
    'birth-date' => Form::DO_NOT_COLLECT,
    'user-gender' => Form::DO_NOT_COLLECT,
    'phone' => Form::DO_NOT_COLLECT,
    'phone-cell' => Form::DO_NOT_COLLECT,
    'height' => Form::DO_NOT_COLLECT,
    'weight' => Form::DO_NOT_COLLECT,
    'school-grade' => Form::DO_NOT_COLLECT,
    'school' => Form::DO_NOT_COLLECT,
    'ethnicity' => Form::DO_NOT_COLLECT,
    'preferred-contact-via' => Form::DO_NOT_COLLECT,
    'pickup-child' => Form::DO_NOT_COLLECT,
    'pickup-child-passwd' => Form::DO_NOT_COLLECT
    /** @todo:  Fill the rest of the fields out. */
  );

  /**
   *  Constructs new self from passed parameters
   *
   * @param string $uuid
   *   Form uuid.
   * @param string $title
   *   Form title.
   * @param array $groups
   *   Form groups.
   * @param array $fields
   *   Form fields
   */
  public function __construct($title, $groups = array(), $fields = array()) {
    $this->title = $title;
    $this->groups = array();
    if ($groups) {
      foreach ($groups as $group) {
        $this->addGroup($group);
      }
    }
    if ($fields) {
      $this->fields = array_merge($this->fields, $fields);
    }
  }

  /**
   * Add the group to the groups array.
   */
  public function addGroup($group) {
    if ($group) {
      $this->groups[] = is_object($group) ? $group->uuid : $group;
    }
  }
}
