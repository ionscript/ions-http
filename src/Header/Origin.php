<?php

namespace Ions\Http\Header;

use Ions\Uri\Http as HttpUri;

/**
 * Class Origin
 * @package Ions\Http\Header
 */
class Origin implements HeaderInterface
{
    /**
     * @var null|string
     */
    protected $value = '';

    /**
     * @param $headerLine
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function create($headerLine)
    {
        list($name, $value) = explode(': ', $headerLine, 2);

        if (strtolower($name) !== 'origin') {
            throw new \InvalidArgumentException('Invalid header line for Origin string: "' . $name . '"');
        }

        $uri = new HttpUri($value);

        if (!$uri->isValid()) {
            throw new \InvalidArgumentException('Invalid header value for Origin key: "' . $name . '"');
        }

        return new static($value);
    }

    /**
     * Origin constructor.
     * @param null $value
     */
    public function __construct($value = null)
    {
        if ($value) {
            Header::assertValid($value);
            $this->value = $value;
        }
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Origin';
    }

    /**
     * @return null|string
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
        return 'Origin: ' . $this->getValue();
    }
}

