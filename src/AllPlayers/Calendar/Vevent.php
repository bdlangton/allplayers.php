<?php
namespace AllPlayers\Calendar;

use stdClass;
use DateTime;
use DateTimeZone;

/**
 * Defines standard APDate fields.
 */
class Vevent extends stdClass
{
    /**
     * @var DateTime
     */
    public $start;

    /**
     * @var DateTime
     */
    public $end;

    /**
     * From Api.
     *
     * @param stdClass|array
     *
     * @return Vevent
     */
    public static function fromApi($api_data)
    {
        $repeat = array();
        $api_data = (array) $api_data;
        if (!empty($api_data['repeat_info'])) {
            $repeat = (array) $api_data['repeat_info'];
            $repeat['until'] = empty($repeat['until']) ? null : new DateTime('@' . strtotime($repeat['until']));
            if (!empty($repeat['exdate'])) {
                foreach ($repeat['exdate'] as $key => $exdate) {
                    $repeat['exdate'][$key] = new DateTime('@' . strtotime($exdate));
                }
            }
            if (!empty($repeat['rdate'])) {
                foreach ($repeat['rdate'] as $key => $rdate) {
                    $repeat['rdate'][$key] = new DateTime('@' . strtotime($rdate));
                }
            }
        }
        $first_date = current($api_data);
        $start = new DateTime('@' . strtotime($first_date->start));
        $end = new DateTime('@' . strtotime($first_date->end));

        return new self($start, $end, $repeat);
    }

    /**
     * APDateInst Constructor.
     *
     * @param DateTime $start
     *   A DateTime object specifying the start of the event. Will be converted
     *   to UTC.
     * @param DateTime $end
     *   A DateTime object specifying the end of the event. Will be converted to
     *   UTC.
     * @param array $repeat
     *   An array of repeat settings and DateTime objects.
     */
    public function __construct(DateTime $start, DateTime $end, $repeat = array())
    {
        $this->start = $start;
        $this->end = $end;
        if (!empty($repeat)) {
            $this->interval = empty($repeat['interval']) ? null : $repeat['interval'];
            $this->freq = empty($repeat['freq']) ? null : $repeat['freq'];
            $this->until = empty($repeat['until']) ? null : $repeat['until'];
            $this->byMonth = empty($repeat['bymonth']) ? null : $repeat['bymonth'];
            $this->byDay = empty($repeat['byday']) ? null : $repeat['byday'];
            $this->byMonthDay = empty($repeat['bymonthday']) ? null : $repeat['bymonthday'];
            $this->exDate = empty($repeat['exdate']) ? null : $repeat['exdate'];
            $this->rDate = empty($repeat['rdate']) ? null : $repeat['rdate'];
        }
        $this->setTimezone(null, 'UTC');
    }

    /**
     * Sets timezone for the whole object.
     *
     * @param array $properties
     *   Array of DateTime objects and the keys must be valid property names.
     * @param string $timezone_name
     *   The name of the timezone the dates need to be converted to.
     */
    public function setTimezone($properties = null, $timezone_name = 'UTC')
    {
        // If there are properties modify, else run the whole object.
        if (empty($properties)) {
            $properties = get_object_vars($this);
        }

        // We have properties ready, either entire object or just select few.
        // Convert them.
        $timezone = new DateTimeZone($timezone_name);
        foreach ($properties as $prop_name => $prop) {
            if (!empty($prop) && ($prop instanceof DateTime || is_array($prop))) {
                $this->{$prop_name} = $this->setPropTimezone($prop, $timezone);
            }
        }
    }

    /**
     * Builds an array of dates for api creation.
     *
     * @todo Do this as a callback to array_filter.
     */
    public function buildSettings()
    {
        $this->setTimezone(null, 'UTC');
        $vevent = array(
            'start' => $this->toApi($this->start),
            'end' => $this->toApi($this->end),
            'repeat' => array(
                'interval' => empty($this->interval) ? null : $this->interval,
                'freq' => empty($this->freq) ? null : $this->freq,
                'until' => empty($this->until) ? null : $this->toApi($this->until, 'until'),
                'bymonth' => empty($this->byMonth) ? null : $this->byMonth,
                'byday' => empty($this->byDay) ? null : $this->byDay,
                'bymonthday' => empty($this->byMonthDay) ? null : $this->byMonthDay,
                'exdate' => empty($this->exDate) ? null : $this->toApi($this->exDate, 'exdate'),
                'rdate' => empty($this->rDate) ? null : $this->toApi($this->rDate, 'rdate'),
            ),
        );
        $vevent['repeat'] = array_filter($vevent['repeat']);

        return array_filter($vevent);
    }

    /**
     * Sets a timezone for a given property.
     *
     * @param mixed $property
     *   An object property that can either be an array of DateTime objects or a
     *   single one.
     * @param DateTimeZone $timezone
     *   A DateTimeZone object to convert the dates to.
     *
     * @return mixed
     *   Either an array of DateTime objects or a single one.
     */
    private function setPropTimezone($property, DateTimeZone $timezone)
    {
        // Handle exdate and rdate arrays.
        if (!is_array($property)) {
            $property = array($property);
        }
        foreach ($property as $dt) {
            if ($dt instanceof DateTime) {
                if ($dt->getTimezone() != $timezone) {
                    $dt->setTimezone($timezone);
                }
            }
        }

        return (count($property) == 1) ? array_pop($property) : $property;
    }

    /**
     * Formats an array of date objects for API input.
     */
    protected function toApi($date, $type = '')
    {
        $format = ($type == 'until' || $type == 'exdate' || $type == 'rdate')
            ? 'Y-m-d\T\0\0\:\0\0\:\0\0'
            : 'Y-m-d\TH:i:\0\0';
        $return = array();
        if (!is_array($date)) {
            $date = array($date);
        }
        foreach ($date as $key => $single_date) {
            $return[$key] = $single_date->format($format);
        }

        return (count($return) == 1) ? array_pop($return) : $return;
    }

    /**
     * Compares Two Vevents.
     *
     * @param Vevent $otherVevent
     *
     * @todo Use dateTime -> diff for diffing.
     * @todo Fix camel case.
     */
    public function diff($otherVevent)
    {
        $diff = array();
        $start_diff = $this->start->diff($otherVevent->start)->format('%I');
        if ($start_diff > 0) {
            $diff['start'] = $start_diff;
        }
        $end_diff = $this->end->diff($otherVevent->end)->format('%I');
        if ($end_diff > 0) {
            $diff['end'] = $start_diff;
        }

        // Finish checking if this is a simple date.
        if (empty($this->repeat) || empty($otherVevent->repeat)) {
            return $diff;
        }

        $self_r_keys = array_keys($this->repeat);
        $other_r_keys = array_keys($otherVevent->repeat);
        $repeat_diff = array_diff($self_r_keys, $other_r_keys);
        if (!empty($repeat_diff)) {
            $diff['repeat'] = $repeat_diff;
        }
        foreach ($self_r_keys as $key) {
            if (is_array($this->repeat[$key])) {
                // Recurse down.
                foreach ($this->repeat[$key] as $r_item) {
                    if (empty($otherVevent->repeat[$key]) || !in_array($r_item, $otherVevent->repeat[$key])) {
                        $diff[$key][]= $r_item;
                        break;
                    }
                }
            } else {
                if ($this->repeat[$key] != $otherVevent->repeat[$key]) {
                    $diff[$key][] = $this->repeat[$key];
                }
            }
        }

        return $diff;
    }
}
