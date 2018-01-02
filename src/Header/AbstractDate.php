<?php

namespace Ions\Http\Header;

use DateTime;
use DateTimeZone;

/**
 * Class AbstractDate
 * @package Ions\Http\Header
 */
abstract class AbstractDate implements HeaderInterface
{
    const DATE_RFC1123 = 0;
    const DATE_RFC1036 = 1;
    const DATE_ANSIC = 2;

    /**
     * @var
     */
    protected $date;

    /**
     * @var string
     */
    protected static $dateFormat = 'D, d M Y H:i:s \G\M\T';

    /**
     * @var array
     */
    protected static $dateFormats = [
        self::DATE_RFC1123 => 'D, d M Y H:i:s \G\M\T',
        self::DATE_RFC1036 => 'D, d M y H:i:s \G\M\T',
        self::DATE_ANSIC => 'D M j H:i:s Y'
    ];

    /**
     * @param $headerLine
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function create($headerLine)
    {
        $dateHeader = new static();

        list($name, $date) = Header::splitHeaderLine($headerLine);

        if (strtolower($name) !== strtolower($dateHeader->getName())) {
            throw new \InvalidArgumentException('Invalid header line for "' . $dateHeader->getName() . '" header string');
        }

        $dateHeader->setDate($date);

        return $dateHeader;
    }

    /**
     * @param $time
     * @return AbstractDate
     */
    public static function fromTimeString($time)
    {
        return static::fromTimestamp(strtotime($time));
    }

    /**
     * @param $time
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function fromTimestamp($time)
    {
        $dateHeader = new static();

        if (!$time || !is_numeric($time)) {
            throw new \InvalidArgumentException('Invalid time for "' . $dateHeader->getName() . '" header string');
        }

        $dateHeader->setDate(new DateTime('@' . $time));

        return $dateHeader;
    }

    /**
     * @param $format
     * @throws \InvalidArgumentException
     */
    public static function setDateFormat($format)
    {
        if (!isset(static::$dateFormats[$format])) {
            throw new \InvalidArgumentException(sprintf('No constant defined for provided date format: %s', $format));
        }

        static::$dateFormat = static::$dateFormats[$format];
    }

    /**
     * @return string
     */
    public static function getDateFormat()
    {
        return static::$dateFormat;
    }

    /**
     * @param $date
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setDate($date)
    {
        if (is_string($date)) {

            try {
                $date = new DateTime($date, new DateTimeZone('GMT'));
            } catch (\Exception $e) {
                throw new \InvalidArgumentException(sprintf('Invalid date passed as string (%s)', (string)$date), $e->getCode(), $e);
            }

        } elseif (!($date instanceof DateTime)) {
            throw new \InvalidArgumentException('Date must be an instance of \DateTime or a string');
        }

        $date->setTimezone(new DateTimeZone('GMT'));
        $this->date = $date;
        return $this;
    }

    /**
     * @return string
     */
    public function getDate()
    {
        return $this->date()->format(static::$dateFormat);
    }

    /**
     * @return DateTime
     */
    public function date()
    {
        if ($this->date === null) {
            $this->date = new DateTime(null, new DateTimeZone('GMT'));
        }

        return $this->date;
    }

    /**
     * @param $date
     * @return int
     * @throws \InvalidArgumentException
     */
    public function compareTo($date)
    {
        if (is_string($date)) {
            try {
                $date = new DateTime($date, new DateTimeZone('GMT'));
            } catch (\Exception $e) {
                throw new \InvalidArgumentException(sprintf('Invalid Date passed as string (%s)', (string)$date), $e->getCode(), $e);
            }
        } elseif (!($date instanceof DateTime)) {
            throw new \InvalidArgumentException('Date must be an instance of \DateTime or a string');
        }
        $dateTimestamp = $date->getTimestamp();
        $thisTimestamp = $this->date()->getTimestamp();
        return ($thisTimestamp === $dateTimestamp) ? 0 : (($thisTimestamp > $dateTimestamp) ? 1 : -1);
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->getDate();
    }

    /**
     * @return string
     */
    public function toString()
    {
        return $this->getName() . ': ' . $this->getDate();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }
}
