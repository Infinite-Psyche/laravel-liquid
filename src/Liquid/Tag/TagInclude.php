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

use Illuminate\Support\Collection;
use Liquid\AbstractTag;
use Liquid\Document;
use Liquid\Context;
use Liquid\LiquidCompiler;
use Liquid\LiquidException;
use Liquid\Regexp;

/**
 * Includes another, partial, template
 *
 * Example:
 *
 *     {% include 'foo' %}
 *
 *     Will include the template called 'foo'
 *
 *     {% include 'foo' with 'bar' %}
 *
 *     Will include the template called 'foo', with a variable called foo that will have the value of 'bar'
 *
 *     {% include 'foo' for 'bar' %}
 *
 *     Will loop over all the values of bar, including the template foo, passing a variable called foo
 *     with each value of bar
 */
class TagInclude extends AbstractTag
{
    /**
     * @var string The name of the template
     */
    private $templateName;

    /**
     * @var bool True if the variable is a collection
     */
    private $collection;

    /**
     * @var mixed The value to pass to the child template as the template name
     */
    private $variable;

    /**
     * @var Document The Document that represents the included template
     */
    private $document;

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
        $regex = new Regexp('/(?<template>"[^"]+"|\'[^\']+\')(\s+(?<use>with|for)\s+(?<for>' . $compiler::QUOTED_FRAGMENT . '+))?(?<attributes>.*)?/');

        if ($regex->match($markup)) {
            $this->templateName = substr($regex->matches['template'], 1, strlen($regex->matches['template']) - 2);

            if (isset($regex->matches['template'])) {
                $this->collection = (isset($regex->matches['use'])) ? ($regex->matches['use'] == "for") : null;
                $this->variable = (isset($regex->matches['for'])) ? $regex->matches['for'] : null;
            }

            if(isset($regex->matches['attributes'])) {
                $this->extractAttributes($regex->matches['attributes']);
            }
        } else {
            throw new LiquidException("Error in tag 'include' - Valid syntax: include '[template]' (with|for) [object|collection]");
        }

        parent::__construct($markup, $tokens, $compiler);
    }

    /**
     * Parses the tokens
     *
     * @param array $tokens
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function parse(array &$tokens)
    {
        // read the source of the template and create a new sub document
        $source = $this->compiler->getTemplateSource($this->templateName);

        $templateTokens = $this->tokenize($source);
        $this->document = new Document(null, $templateTokens, $this->compiler);
    }

    /**
     * Renders the node
     *
     * @param Context $context
     *
     * @return string
     * @throws LiquidException
     */
    public function render(Context $context)
    {

        $result = '';
        $variable = $context->get($this->variable);

        $context->push();

        foreach ($this->attributes as $key => $value) {
            $context->set($key, $context->get($value));
        }

        if ($this->collection) {
            if(is_array($variable) || $variable instanceof Collection) {
                foreach ($variable as $item) {
                    $context->set($this->templateName, $item);
                    $result .= $this->document->render($context);
                }
            }

        } else {
            if (!is_null($this->variable)) {
                $context->set($this->templateName, $variable);
            }

            $result .= $this->document->render($context);
        }

        $context->pop();

        return $result;
    }
}
