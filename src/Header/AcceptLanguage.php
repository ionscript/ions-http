<?php

namespace Ions\Http\Header;

/**
 * Class AcceptLanguage
 * @package Ions\Http\Header
 */
class AcceptLanguage extends AbstractAccept
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
        return 'Accept-Language';
    }

    /**
     * @return string
     */
    public function toString()
    {
        return 'Accept-Language: ' . $this->getValue();
    }

    /**
     * @param $type
     * @param int $priority
     * @return $this
     */
    public function addLanguage($type, $priority = 1)
    {
        return $this->addType($type, $priority);
    }

    /**
     * @param $type
     * @return bool
     */
    public function hasLanguage($type)
    {
        return $this->hasType($type);
    }
}
