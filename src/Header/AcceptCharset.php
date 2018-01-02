<?php

namespace Ions\Http\Header;

/**
 * Class AcceptCharset
 * @package Ions\Http\Header
 */
class AcceptCharset extends AbstractAccept
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
        return 'Accept-Charset';
    }

    /**
     * @return string
     */
    public function toString()
    {
        return 'Accept-Charset: ' . $this->getFieldValue();
    }

    /**
     * @param $type
     * @param int $priority
     * @return $this
     */
    public function addCharset($type, $priority = 1)
    {
        return $this->addType($type, $priority);
    }

    /**
     * @param $type
     * @return bool
     */
    public function hasCharset($type)
    {
        return $this->hasType($type);
    }
}
