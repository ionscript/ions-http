<?php

namespace Ions\Http\Header;

/**
 * Class Date
 * @package Ions\Http\Header
 */
class Date extends AbstractDate
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'Date';
    }
}
