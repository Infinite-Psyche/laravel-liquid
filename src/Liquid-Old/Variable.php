<?php

/**
 * This file is part of the Liquid package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package Liquid
 */

namespace Liquid;

use Liquid\Traits\HelpersTrait;

/**
 * Implements a template variable.
 */
class Variable
{

    use HelpersTrait;

    /**
     * @var array The filters to execute on the variable
     */
    private $filters;

    /**
     * @var string The name of the variable
     */
    private $name;

    /**
     * @var string The markup of the variable
     */
    private $markup;

    /**
     * Constructor
     *
     * @param string $markup
     */
    public function __construct($markup)
    {
        $this->markup = $markup;

        $quotedFragmentRegexp = new Regexp('/\s*(' . LiquidEngine::QUOTED_FRAGMENT . ')/');
        $filterSeperatorRegexp = new Regexp('/' . LiquidEngine::FILTER_SEPARATOR . '\s*(.*)/');
        $filterSplitRegexp = new Regexp('/' . LiquidEngine::FILTER_SEPARATOR . '/');
        $filterNameRegexp = new Regexp('/\s*(\w+)/');
        $filterArgumentRegexp = new Regexp('/(?:' . LiquidEngine::FILTER_ARGUMENT_SEPARATOR . '|' . LiquidEngine::ARGUMENT_SEPARATOR . ')\s*(' . LiquidEngine::QUOTED_FRAGMENT_FILTER_ARGUMENT . ')/');

        $quotedFragmentRegexp->match($markup);

        $this->name = (isset($quotedFragmentRegexp->matches[1])) ? $quotedFragmentRegexp->matches[1] : null;

        if ($filterSeperatorRegexp->match($markup)) {
            $filters = $filterSplitRegexp->split($filterSeperatorRegexp->matches[1]);

            foreach ($filters as $filter) {
                $filterNameRegexp->match($filter);
                $filtername = $filterNameRegexp->matches[1];

                $filterArgumentRegexp->matchAll($filter);
                $matches = $this->arrayFlatten($filterArgumentRegexp->matches[1]);

                $this->filters[] = array($filtername, $matches);
            }

        } else {
            $this->filters = array();
        }

        if (LiquidEngine::ESCAPE_BY_DEFAULT) {
            // if auto_escape is enabled, and
            // - there's no raw filter, and
            // - no escape filter
            // - no other standard html-adding filter
            // then
            // - add a mandatory escape filter

            $addEscapeFilter = true;

            foreach ($this->filters as $filter) {
                // with empty filters set we would just move along
                if (in_array($filter[0], array('escape', 'escape_once', 'raw', 'newline_to_br'))) {
                    // if we have any raw-like filter, stop
                    $addEscapeFilter = false;
                    break;
                }
            }

            if ($addEscapeFilter) {
                $this->filters[] = array('escape', array());
            }
        }
    }

    /**
     * Gets the variable name
     *
     * @return string The name of the variable
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Gets all Filters
     *
     * @return array
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * Renders the variable with the data in the context
     *
     * @param Context $context
     *
     * @return mixed|string
     * @throws LiquidException
     */
    public function render(Context $context)
    {
        $output = $context->get($this->name);

        foreach ($this->filters as $filter) {
            list($filtername, $filterArgKeys) = $filter;

            $filterArgValues = array();

            foreach ($filterArgKeys as $arg_key) {
                $filterArgValues[] = $context->get($arg_key);
            }

            $output = $context->invoke($filtername, $output, $filterArgValues);
        }

        if (is_float($output)) {
            if ($output == (int)$output) {
                return number_format($output, 1);
            }
        }

        return $output;
    }
}
