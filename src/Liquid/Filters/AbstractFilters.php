<?php

namespace Liquid\Filters;

use Liquid\Context;

abstract class AbstractFilters
{
    /**
     * @var Context
     */
    protected $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

}
