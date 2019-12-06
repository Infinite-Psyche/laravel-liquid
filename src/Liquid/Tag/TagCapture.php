<?php

/**
 * This file is part of the Liquid package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package Liquid
 */

namespace Liquid\Tag;

use Liquid\AbstractBlock;
use Liquid\Context;
use Liquid\LiquidCompiler;
use Liquid\LiquidException;
use Liquid\Regexp;

/**
 * Captures the output inside a block and assigns it to a variable
 *
 * Example:
 *
 *     {% capture foo %} bar {% endcapture %}
 */
class TagCapture extends AbstractBlock
{
    /**
     * The variable to assign to
     *
     * @var string
     */
    protected $to;
    /**
     * The variable to assign to
     *
     * @var string
     */
    protected $append = false;

    /**
     * Constructor
     *
     * @param string $markup
     * @param array $tokens
     *
     * @param LiquidCompiler|null $compiler
     * @throws LiquidException
     */
    public function __construct($markup, array &$tokens, LiquidCompiler $compiler = null)
    {
        $syntaxRegexp = new Regexp('/[\'\"](\w+)[\'\"]\s*(append)?/');

        if ($syntaxRegexp->match($markup)) {
            $this->to = $syntaxRegexp->matches[1];
            if(!empty($syntaxRegexp->matches[2])) {
                $this->append = $syntaxRegexp->matches[2] == 'append';
            }
            parent::__construct($markup, $tokens, $compiler);
        } else {
            throw new LiquidException("Syntax Error in 'capture' - Valid syntax: capture [var] [value]");
        }
    }

    /**
     * Renders the block
     *
     * @param Context $context
     *
     * @return string
     */
    public function render(Context $context)
    {
        $output = parent::render($context);

        if($this->append) {
            $old = $context->get($this->to);
            if(!is_array($old)) {
                $old = [];
            }

            $old[] = $output;

            $context->set($this->to, $old, true);
        } else {
            $context->set($this->to, trim($output), true);
        }

        return '';
    }
}
