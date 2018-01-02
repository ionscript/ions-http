<?php

namespace Ions\Http\Header;

/**
 * Class Accept
 * @package Ions\Http\Header
 */
class Accept extends AbstractAccept
{
    /**
     * @var string
     */
    protected $regexAddType = '#^([a-zA-Z+-]+|\*)/(\*|[a-zA-Z0-9+-]+)$#';

    /**
     * @return string
     */
    public function getName()
    {
        return 'Accept';
    }

    /**
     * @return string
     */
    public function toString()
    {
        return 'Accept: ' . $this->getValue();
    }

    /**
     * @param $type
     * @param int $priority
     * @param array $params
     * @return $this
     */
    public function addMediaType($type, $priority = 1, array $params = [])
    {
        return $this->addType($type, $priority, $params);
    }

    /**
     * @param $type
     * @return bool
     */
    public function hasMediaType($type)
    {
        return $this->hasType($type);
    }
}
