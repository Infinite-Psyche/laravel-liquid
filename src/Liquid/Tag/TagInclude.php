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

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\ViewFinderInterface;
use Liquid\AbstractTag;
use Liquid\Document;
use Liquid\Context;
use Liquid\LiquidEngine;
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
     * @var string The Source Hash
     */
    protected $hash;

    /**
     * Constructor
     *
     * @param string $markup
     * @param array $tokens
     * @param ViewFinderInterface $viewFinder
     *
     * @throws \Liquid\LiquidException
     */
    public function __construct($markup, array &$tokens, ViewFinderInterface $viewFinder = null, Filesystem $files = null, $compiled = null)
    {
        $regex = new Regexp('/("[^"]+"|\'[^\']+\')(\s+(with|for)\s+(' . LiquidEngine::QUOTED_FRAGMENT . '+))?/');

        if ($regex->match($markup)) {
            $this->templateName = substr($regex->matches[1], 1, strlen($regex->matches[1]) - 2);

            if (isset($regex->matches[1])) {
                $this->collection = (isset($regex->matches[3])) ? ($regex->matches[3] == "for") : null;
                $this->variable = (isset($regex->matches[4])) ? $regex->matches[4] : null;
            }

            $this->extractAttributes($markup);
        } else {
            throw new LiquidException("Error in tag 'include' - Valid syntax: include '[template]' (with|for) [object|collection]");
        }

        parent::__construct($markup, $tokens, $viewFinder, $files, $compiled);
    }

    /**
     * Parses the tokens
     *
     * @param array $tokens
     *
     * @throws LiquidException
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function parse(array &$tokens)
    {
        if ($this->viewFinder === null) {
            throw new LiquidException("No file system");
        }

        // read the source of the template and create a new sub document
        $source = $this->files->get($this->viewFinder->find($this->templateName));

        $this->hash = md5($source);
        $file = $this->hash . '.liquid';
        $path = $this->compiled . '/' . $file;
        if (!$this->files->exists($path) || !($this->document = @unserialize($this->files->get($path))) || !($this->document->checkIncludes() != true)) {
            $templateTokens = LiquidEngine::tokenize($source);
            $this->document = new Document($templateTokens, $this->viewFinder, $this->files, $this->compiled);
            $this->files->put($path, serialize($this->document));
        }
    }

    /**
     * check for cached includes
     *
     * @return boolean
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function checkIncludes()
    {
        if ($this->document->checkIncludes() == true) {
            return true;
        }

        $source = $this->files->get($this->viewFinder->find($this->templateName));

        $file = md5($source) . '.liquid';
        $path = $this->compiled . '/' . $file;

        if ($this->files->exists($path) && $this->hash == md5($source)) {
            return false;
        }

        return true;
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
            foreach ($variable as $item) {
                $context->set($this->templateName, $item);
                $result .= $this->document->render($context);
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
