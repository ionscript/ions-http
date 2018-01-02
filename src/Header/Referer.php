<?php

namespace Ions\Http\Header;

/**
 * Class Referer
 * @package Ions\Http\Header
 */
class Referer extends AbstractLocation
{
    /**
     * @param $uri
     * @return $this
     */
    public function setUri($uri)
    {
        parent::setUri($uri);

        $this->uri->setFragment(null);

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Referer';
    }
}
