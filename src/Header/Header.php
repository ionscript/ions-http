<?php

namespace Ions\Http\Header;

/**
 * Class Header
 * @package Ions\Http\Header
 */
class Header implements HeaderInterface
{
    /**
     * @var
     */
    protected $name;
    /**
     * @var
     */
    protected $value;

    /**
     * Header constructor.
     * @param null $name
     * @param null $value
     */
    public function __construct($name = null, $value = null)
    {
        if ($name) {
            $this->setName($name);
        }

        if ($value !== null) {
            $this->setValue($value);
        }
    }

    /**
     * @param $headerLine
     * @return static
     */
    public static function create($headerLine)
    {
        list($name, $value) = static::splitHeaderLine($headerLine);

        return new static($name, $value);
    }

    /**
     * @param $headerLine
     * @return array
     * @throws \InvalidArgumentException
     */
    public static function splitHeaderLine($headerLine)
    {
        $parts = explode(':', $headerLine, 2);

        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Header must match with the format "name:value"');
        }

        if (!static::isValid($parts[1])) {
            throw new \InvalidArgumentException('Invalid header value detected');
        }

        $parts[1] = ltrim($parts[1]);

        return $parts;
    }

    /**
     * @param $value
     * @return string
     */
    public static function filter($value)
    {
        $string = '';

        foreach (str_split((string)$value) as $char) {
            $ascii = ord($char);

            if (($ascii < 32 && $ascii !== 9) || $ascii === 127 || $ascii > 254) {
                continue;
            }

            $string .= $char;
        }

        return $string;
    }

    /**
     * @param $value
     * @return bool
     */
    public static function isValid($value)
    {
        foreach (str_split((string)$value) as $char) {
            $ascii = ord($char);

            if (($ascii < 32 && $ascii !== 9) || $ascii === 127 || $ascii > 254) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param $value
     * @throws \InvalidArgumentException
     */
    public static function assertValid($value)
    {
        if (!self::isValid($value)) {
            throw new \InvalidArgumentException('Invalid header value');
        }
    }

    /**
     * @param $name
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setName($name)
    {
        if (!is_string($name) || empty($name)) {
            throw new \InvalidArgumentException('Header name must be a string');
        }

        if (!preg_match("/^[!#$%&'*+\-\.\^_`|~0-9a-zA-Z]+$/", $name)) {
            throw new \InvalidArgumentException('Header name must be a valid RFC 7230 (section 3.2) field-name.');
        }

        $this->name = $name;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param $value
     * @return $this
     */
    public function setValue($value)
    {
        $value = (string)$value;

        static::assertValid($value);

        if (preg_match('/^\s+$/', $value)) {
            $value = '';
        }

        $this->value = $value;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function toString()
    {
        return $this->getName() . ': ' . $this->getValue();
    }
}

