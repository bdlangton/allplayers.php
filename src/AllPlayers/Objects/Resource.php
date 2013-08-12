<?php
namespace AllPlayers\Objects;

use stdClass;
use AllPlayers\Calendar\Vevent;

/**
 * Defines standard Resource fields.
 */
class Resource extends stdClass
{
    /**
     * @var string
     */
    public $title;

    /**
     * @var array
     */
    public $location;

    /**
     * @var array
     */
    public $groups;
    /**
     * @var string
     */
    public $uuid;

    /**
     * @param stdClass|array $api_data
     *
     * @return Resource
     */
    public static function fromApi($api_data)
    {
        if (!is_array($api_data)) {
            $api_data = (array) $api_data;
        }
        $availability = null;
        if (!empty($api_data['availability'])) {
            $availability = array();
            foreach ($api_data['availability'] as $av_uuid => $av) {
                $availability[$av_uuid] = Vevent::fromApi($av);
            }
        }
        $external_id = empty($api_data['external_id']) ? null : $api_data['external_id'];

        return new self(
            $api_data['uuid'],
            $api_data['title'],
            $api_data['location'],
            $api_data['groups'],
            $availability,
            $external_id
        );
    }

    /**
     * Constructor.
     *
     * @param string $uuid
     * @param string $title
     * @param array $location
     * @param array $groups
     * @param array $availability
     * @param string $external_id
     */
    public function __construct($uuid, $title, $location, $groups, $availability = null, $external_id = null)
    {
        $this->uuid = $uuid;
        $this->title = $title;
        $this->location = $location;
        $this->groups = $groups;
        $this->availability = $availability;
        $this->external_id = $external_id;
    }

    /**
     * Compares two resources against each other.
     *
     * @param Resource $otherResource
     *   The resource being compared.
     *
     * @return array
     *   Returns a diff array.
     */
    public function diff($otherResource)
    {
        $diff = array();
        $properties = get_object_vars($this);
        $simple_compare = array();
        foreach ($properties as $key => $prop) {
            if (is_string($prop) || is_bool($prop) || is_int($prop)) {
                $simple_compare[$key] = $prop;
            }
        }
        foreach ($simple_compare as $name => $value) {
            if (empty($otherResource->$name) || $otherResource->$name != $value) {
                $diff[$name] = array($value, $otherResource->$name);
            }
        }
        if (!empty($this->groups)) {
            foreach ($this->groups as $group) {
                if (!in_array($group, $this->groups)) {
                    $diff['groups'][] = $group;
                    break;
                }
            }
        } else {
            $diff['groups'] = 'groups';
        }

        // $otherResource's availability should match at least one.
        // TODO: Fix.
        if (!empty($this->availability)) {
            foreach ($this->availability as $availability) {
                // Availability could actually be empty here, in case an update
                // decided to clear it out. To prevent failure we will not run
                // if empty.
                if (!empty($availability)) {
                    $success = 0;
                    $diff_data = array();
                    $count = 0;
                    foreach ($otherResource->availability as $otherAvailability) {
                        // RandomDate::compare($availability, $apiAvailability);
                        $date_diff = $availability->diff($otherAvailability);
                        if (empty($date_diff)) {
                            $success++;
                            unset($diff_data[$count]);
                            break;
                        } else {
                            $diff_data[$count] = $date_diff;
                        }
                        $count++;
                    }
                    if ($success === 0) {
                        // Fail.
                        $diff['date'][] = $diff_data;
                    }
                }
            }
        }

        return $diff;
    }
}
