<?php

namespace Ions\Http\Header;

/**
 * Class Age
 * @package Ions\Http\Header
 */
class Age implements HeaderInterface
{
    /**
     * @var
     */
    protected $deltaSeconds;

    /**
     * @param $headerLine
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function create($headerLine)
    {
        list($name, $value) = Header::splitHeaderLine($headerLine);

        if (strtolower($name) !== 'age') {
            throw new \InvalidArgumentException('Invalid header line for Age string: "' . $name . '"');
        }

        $header = new static($value);

        return $header;
    }

    /**
     * Age constructor.
     * @param null $deltaSeconds
     */
    public function __construct($deltaSeconds = null)
    {
        if ($deltaSeconds) {
            $this->setDeltaSeconds($deltaSeconds);
        }
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Age';
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->getDeltaSeconds();
    }

    /**
     * @param $delta
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setDeltaSeconds($delta)
    {
        if (!is_int($delta) && !is_numeric($delta)) {
            throw new \InvalidArgumentException('Invalid delta provided');
        }
        $this->deltaSeconds = (int)$delta;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDeltaSeconds()
    {
        return $this->deltaSeconds;
    }

    /**
     * @return string
     */
    public function toString()
    {
        return 'Age: ' . (($this->deltaSeconds >= PHP_INT_MAX) ? '2147483648' : $this->deltaSeconds);
    }
}
