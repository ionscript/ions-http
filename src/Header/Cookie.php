<?php

namespace Ions\Http\Header;

use ArrayObject;

/**
 * Class Cookie
 * @package Ions\Http\Header
 */
class Cookie extends ArrayObject implements HeaderInterface
{
    /**
     * @var bool
     */
    protected $encodeValue = true;

    /**
     * @param array $setCookies
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function fromSetCookieArray(array $setCookies)
    {
        $nvPairs = [];

        foreach ($setCookies as $setCookie) {

            if (!$setCookie instanceof SetCookie) {
                throw new \InvalidArgumentException(sprintf('%s requires an array of SetCookie objects', __METHOD__));
            }

            if (array_key_exists($setCookie->getName(), $nvPairs)) {
                throw new \InvalidArgumentException(sprintf('Two cookies with the same name were provided to %s', __METHOD__));
            }

            $nvPairs[$setCookie->getName()] = $setCookie->getValue();
        }

        return new static($nvPairs);
    }

    /**
     * @param $headerLine
     * @return static
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public static function create($headerLine)
    {
        $header = new static();

        list($name, $value) = Header::splitHeaderLine($headerLine);

        if (strtolower($name) !== 'cookie') {
            throw new \InvalidArgumentException('Invalid header line for Server string: "' . $name . '"');
        }

        $nvPairs = preg_split('#;\s*#', $value);

        $arrayInfo = [];

        foreach ($nvPairs as $nvPair) {
            $parts = explode('=', $nvPair, 2);

            if (count($parts) !== 2) {
                throw new \RuntimeException('Malformed Cookie header found');
            }

            list($name, $value) = $parts;

            $arrayInfo[$name] = urldecode($value);
        }

        $header->exchangeArray($arrayInfo);

        return $header;
    }

    /**
     * Cookie constructor.
     * @param array $array
     */
    public function __construct(array $array = [])
    {
        parent::__construct($array, ArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * @param $encodeValue
     * @return $this
     */
    public function setEncodeValue($encodeValue)
    {
        $this->encodeValue = (bool)$encodeValue;
        return $this;
    }

    /**
     * @return bool
     */
    public function getEncodeValue()
    {
        return $this->encodeValue;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Cookie';
    }

    /**
     * @return string
     */
    public function getValue()
    {
        $nvPairs = [];

        foreach ($this->flattenCookies($this) as $name => $value) {
            $nvPairs[] = $name . '=' . (($this->encodeValue) ? urlencode($value) : $value);
        }

        return implode('; ', $nvPairs);
    }

    /**
     * @param $data
     * @param null $prefix
     * @return array
     */
    protected function flattenCookies($data, $prefix = null)
    {
        $result = [];

        foreach ($data as $key => $value) {
            $key = $prefix ? $prefix . '[' . $key . ']' : $key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenCookies($value, $key));
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @return string
     */
    public function toString()
    {
        return 'Cookie: ' . $this->getValue();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }
}
