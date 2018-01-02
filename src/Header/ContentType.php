<?php

namespace Ions\Http\Header;

/**
 * Class ContentType
 * @package Ions\Http\Header
 */
class ContentType implements HeaderInterface
{
    /**
     * @var null
     */
    protected $mediaType;
    /**
     * @var array
     */
    protected $parameters = [];
    /**
     * @var null
     */
    protected $value;

    /**
     * @param $headerLine
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function create($headerLine)
    {
        list($name, $value) = Header::splitHeaderLine($headerLine);

        if (strtolower($name) !== 'content-type') {
            throw new \InvalidArgumentException(sprintf('Invalid header line for Content-Type string: "%s"', $name));
        }

        $parts = explode(';', $value);

        $mediaType = array_shift($parts);

        $header = new static($value, trim($mediaType));

        if (count($parts) > 0) {
            $parameters = [];
            foreach ($parts as $parameter) {

                $parameter = trim($parameter);

                if (!preg_match('/^(?P<key>[^\s\=]+)\="?(?P<value>[^\s\"]*)"?$/', $parameter, $matches)) {
                    continue;
                }

                $parameters[$matches['key']] = $matches['value'];
            }

            $header->setParameters($parameters);
        }

        return $header;
    }

    /**
     * ContentType constructor.
     * @param null $value
     * @param null $mediaType
     */
    public function __construct($value = null, $mediaType = null)
    {
        if ($value) {
            Header::assertValid($value);
            $this->value = $value;
        }

        $this->mediaType = $mediaType;
    }

    /**
     * @param $matchAgainst
     * @return bool|string
     */
    public function match($matchAgainst)
    {
        if (is_string($matchAgainst)) {
            $matchAgainst = $this->splitMediaTypesFromString($matchAgainst);
        }

        $mediaType = $this->getMediaType();

        $left = $this->getMediaTypeObjectFromString($mediaType);

        foreach ($matchAgainst as $matchType) {

            $matchType = strtolower($matchType);

            if ($mediaType === $matchType) {
                return $matchType;
            }

            $right = $this->getMediaTypeObjectFromString($matchType);

            if ($right->type === '*') {
                if ($this->validateSubtype($right, $left)) {
                    return $matchType;
                }
            }

            if ($right->type == $left->type) {
                if ($this->validateSubtype($right, $left)) {
                    return $matchType;
                }
            }
        }

        return false;
    }

    /**
     * @return string
     */
    public function toString()
    {
        return 'Content-Type: ' . $this->getFieldValue();
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Content-Type';
    }

    /**
     * @return null|string
     */
    public function getValue()
    {
        if (null !== $this->value) {
            return $this->value;
        }
        return $this->assembleValue();
    }

    /**
     * @param $mediaType
     * @return $this
     */
    public function setMediaType($mediaType)
    {
        Header::assertValid($mediaType);
        $this->mediaType = strtolower($mediaType);
        $this->value = null;
        return $this;
    }

    /**
     * @return null
     */
    public function getMediaType()
    {
        return $this->mediaType;
    }

    /**
     * @param array $parameters
     * @return $this
     */
    public function setParameters(array $parameters)
    {
        foreach ($parameters as $key => $value) {
            Header::assertValid($key);
            Header::assertValid($value);
        }

        $this->parameters = array_merge($this->parameters, $parameters);

        $this->value = null;

        return $this;
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @param $charset
     * @return $this
     */
    public function setCharset($charset)
    {
        Header::assertValid($charset);

        $this->parameters['charset'] = $charset;

        $this->value = null;

        return $this;
    }

    /**
     * @return mixed|null
     */
    public function getCharset()
    {
        if (isset($this->parameters['charset'])) {
            return $this->parameters['charset'];
        }

        return null;
    }

    /**
     * @return null|string
     */
    protected function assembleValue()
    {
        $mediaType = $this->getMediaType();

        if (empty($this->parameters)) {
            return $mediaType;
        }

        $parameters = [];

        foreach ($this->parameters as $key => $value) {
            $parameters[] = sprintf('%s=%s', $key, $value);
        }

        return sprintf('%s; %s', $mediaType, implode('; ', $parameters));
    }

    /**
     * @param $criteria
     * @return array
     */
    protected function splitMediaTypesFromString($criteria)
    {
        $mediaTypes = explode(',', $criteria);

        array_walk($mediaTypes, function (&$value) {
            $value = trim($value);
        });

        return $mediaTypes;
    }

    /**
     * @param $string
     * @return object
     * @throws \InvalidArgumentException|\DomainException
     */
    protected function getMediaTypeObjectFromString($string)
    {
        if (!is_string($string)) {
            throw new \InvalidArgumentException(sprintf('Non-string mediatype "%s" provided', (is_object($string) ? get_class($string) : gettype($string))));
        }

        $parts = explode('/', $string, 2);

        if (1 === count($parts)) {
            throw new \DomainException(sprintf('Invalid mediatype "%s" provided', $string));
        }

        $type = array_shift($parts);

        $subtype = array_shift($parts);

        $format = $subtype;

        if (strstr($subtype, '+')) {
            $parts = explode('+', $subtype, 2);
            $subtype = array_shift($parts);
            $format = array_shift($parts);
        }

        $mediaType = (object)['type' => $type, 'subtype' => $subtype, 'format' => $format];

        return $mediaType;
    }

    /**
     * @param $right
     * @param $left
     * @return bool
     */
    protected function validateSubtype($right, $left)
    {
        if ($right->subtype === '*') {
            return $this->validateFormat($right, $left);
        }

        if ($right->subtype === $left->subtype) {
            return $this->validateFormat($right, $left);
        }

        if ('*' === substr($right->subtype, -1)) {
            if (!$this->validatePartialWildcard($right->subtype, $left->subtype)) {
                return false;
            }

            return $this->validateFormat($right, $left);
        }

        if ($right->subtype === $left->format) {
            return true;
        }

        return false;
    }

    /**
     * @param $right
     * @param $left
     * @return bool
     */
    protected function validateFormat($right, $left)
    {
        if ($right->format && $left->format) {
            if ($right->format === '*') {
                return true;
            }

            if ($right->format === $left->format) {
                return true;
            }

            return false;
        }

        return true;
    }

    /**
     * @param $right
     * @param $left
     * @return bool
     */
    protected function validatePartialWildcard($right, $left)
    {
        $requiredSegment = substr($right, 0, strlen($right) - 1);

        if ($requiredSegment === $left) {
            return true;
        }

        if (strlen($requiredSegment) >= strlen($left)) {
            return false;
        }

        if (0 === strpos($left, $requiredSegment)) {
            return true;
        }

        return false;
    }
}
