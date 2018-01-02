<?php

namespace Ions\Http\Header;

/**
 * Class AcceptEncoding
 * @package Ions\Http\Header
 */
class AcceptEncoding extends AbstractAccept
{
    /**
     * @var string
     */
    protected $regexAddType = '#^([a-zA-Z0-9+-]+|\*)$#';

    /**
     * @return string
     */
    public function getName()
    {
        return 'Accept-Encoding';
    }

    /**
     * @return string
     */
    public function toString()
    {
        return 'Accept-Encoding: ' . $this->getValue();
    }

    /**
     * @param $type
     * @param int $priority
     * @return $this
     */
    public function addEncoding($type, $priority = 1)
    {
        return $this->addType($type, $priority);
    }

    /**
     * @param $type
     * @return bool
     */
    public function hasEncoding($type)
    {
        return $this->hasType($type);
    }
}
