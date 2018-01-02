<?php

namespace Ions\Http\Header;

/**
 * Class CacheControl
 * @package Ions\Http\Header
 */
class CacheControl implements HeaderInterface
{
    /**
     * @var
     */
    protected $value;

    /**
     * @var array
     */
    protected $directives = [];

    /**
     * @param $headerLine
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function create($headerLine)
    {
        list($name, $value) = Header::splitHeaderLine($headerLine);

        if (strtolower($name) !== 'cache-control') {
            throw new \InvalidArgumentException(sprintf('Invalid header line for Cache-Control string: "%s"', $name));
        }

        Header::assertValid($value);

        $directives = static::parseValue($value);

        $header = new static();

        foreach ($directives as $key => $value) {
            $header->addDirective($key, $value);
        }

        return $header;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Cache-Control';
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->directives);
    }

    /**
     * @param $key
     * @param bool $value
     * @return $this
     */
    public function addDirective($key, $value = true)
    {
        Header::assertValid($key);

        if (!is_bool($value)) {
            Header::assertValid($value);
        }

        $this->directives[$key] = $value;

        return $this;
    }

    /**
     * @param $key
     * @return bool
     */
    public function hasDirective($key)
    {
        return array_key_exists($key, $this->directives);
    }

    /**
     * @param $key
     * @return mixed|null
     */
    public function getDirective($key)
    {
        return array_key_exists($key, $this->directives) ? $this->directives[$key] : null;
    }

    /**
     * @param $key
     * @return $this
     */
    public function removeDirective($key)
    {
        unset($this->directives[$key]);
        return $this;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        $parts = [];
        ksort($this->directives);

        foreach ($this->directives as $key => $value) {
            if (true === $value) {
                $parts[] = $key;
            } else {
                if (preg_match('#[^a-zA-Z0-9._-]#', $value)) {
                    $value = '"' . $value . '"';
                }
                $parts[] = $key . '=' . $value;
            }
        }

        return implode(', ', $parts);
    }

    /**
     * @return string
     */
    public function toString()
    {
        return 'Cache-Control: ' . $this->getValue();
    }

    /**
     * @param $value
     * @return array
     * @throws \InvalidArgumentException
     */
    protected static function parseValue($value)
    {
        $value = trim($value);

        $directives = [];

        if ($value === '') {
            return $directives;
        }

        $lastMatch = null;

        state_directive:
        switch (static::match(['[a-zA-Z][a-zA-Z_-]*'], $value, $lastMatch)) {
            case 0:
                $directive = $lastMatch;
                goto state_value;
            default:
                throw new \InvalidArgumentException('expected DIRECTIVE');
        }

        state_value:
        switch (static::match(['="[^"]*"', '=[^",\s;]*'], $value, $lastMatch)) {
            case 0:
                $directives[$directive] = substr($lastMatch, 2, -1);
                goto state_separator;
            case 1:
                $directives[$directive] = rtrim(substr($lastMatch, 1));
                goto state_separator;
            default:
                $directives[$directive] = true;
                goto state_separator;
        }

        state_separator:
        switch (static::match(['\s*,\s*', '$'], $value, $lastMatch)) {
            case 0:
                goto state_directive;
            case 1:
                return $directives;
            default:
                throw new \InvalidArgumentException('expected SEPARATOR or END');
        }
    }

    /**
     * @param $tokens
     * @param $string
     * @param $lastMatch
     * @return int|string
     */
    protected static function match($tokens, &$string, &$lastMatch)
    {
        $value = (string)$string;
        foreach ($tokens as $i => $token) {
            if (preg_match('/^' . $token . '/', $value, $matches)) {
                $lastMatch = $matches[0];
                $string = substr($value, strlen($matches[0]));
                return $i;
            }
        }
        return -1;
    }
}
