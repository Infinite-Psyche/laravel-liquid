<?php

/**
 * JS: https://github.com/harttle/liquidjs
 *
 * This file is part of the Liquid package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package Liquid
 */

namespace Liquid;

use ErrorException;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\Compiler;
use Illuminate\View\Compilers\CompilerInterface;
use Illuminate\View\ViewFinderInterface;
use Liquid\Traits\TokenizeTrait;
use Liquid\ViewFinders\DatabaseViewFinder;
use Throwable;

/**
 * The Template class.
 *
 * http://cheat.markdunkley.com/
 * https://stackoverflow.com/questions/29123188/enabling-liquid-templating-syntax-highlight-in-webstorm-phpstorm/29337624#29337624
 * https://github.com/Shopify/liquid
 *
 */
class LiquidCompiler extends Compiler implements CompilerInterface
{

    use TokenizeTrait;

    /**
     * @var Document The root of the node tree
     */
    protected $root;

    /**
     * @var array Globally included filters
     */
    protected $filters = array();

    /**
     * @var array Custom tags
     */
    protected $tags = [];

    /**
     * @var bool $auto_escape
     */
    protected $auto_escape = true;

    /**
     * @var TemplateContent
     */
    protected $path;

    /**
     * @var array
     */
    protected $filemtime = [];

    // Operations tags.
    const OPERATION_TAGS = ['{%', '%}'];

    // Variable tags.
    const VARIABLE_TAG = ['{{', '}}'];

    const ANY_STARTING_TAG = self::VARIABLE_TAG[0] . '|' . self::OPERATION_TAGS[0];

    const PARTIAL_TEMPLATE_PARSER = self::VARIABLE_TAG[0] . '.*?' . self::VARIABLE_TAG[1] . '|' . self::OPERATION_TAGS[0] . '.*?' . self::OPERATION_TAGS[1];

    // Variable name.
    const VARIABLE_NAME = '[a-zA-Z_][a-zA-Z_0-9.-]*';

    const QUOTED_FRAGMENT = '"[^"]*"|\'[^\']*\'|(?:[^\s,\|\'"]|"[^"]*"|\'[^\']*\')+';

    const TAG_ATTRIBUTES = '/(\w+)\s*\:\s*(' . self::QUOTED_FRAGMENT . ')/';

    public function __construct(array $options = [])
    {
        foreach($options AS $key => $value) {
            if(method_exists($this, $method = Str::camel('set_' . $key))) {
                $this->$method($value);
            }
        }
    }

    /**
     * Get the path currently being compiled.
     *
     * @return TemplateContent
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Set the path currently being compiled.
     *
     * @param  string  $path
     * @return void
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * @return ViewFinderManager
     */
    public function getFinder()
    {
        return app('liquid.view.finder')->driver();
    }

    /**
     * @return bool
     */
    public function getAutoEscape()
    {
        return $this->auto_escape;
    }

    /**
     * Set tags
     *
     * @param bool $value
     * @return LiquidCompiler
     */
    public function setAutoEscape($value)
    {
        $this->auto_escape = $value;
        return $this;
    }

    /**
     * Set tags
     *
     * @param array $tags
     * @return LiquidCompiler
     */
    public function setTags(array $tags)
    {
        foreach($tags AS $key => $value) {
            $this->registerTag($key, $value);
        }
        return $this;
    }

    /**
     * Register custom Tags
     *
     * @param string $name
     * @param string $class
     * @return LiquidCompiler
     */
    public function registerTag($name, $class)
    {
        if($class instanceof \Closure) {
            throw new \InvalidArgumentException('Type "Closure" is not allowed for tag!');
        }
        $this->tags[$name] = $class;
        return $this;
    }

    /**
     * @return array
     */
    public function getTags()
    {
        return $this->tags ? : [];
    }

    /**
     * Register the filter
     *
     * @param string $filter
     * @return LiquidCompiler
     */
    public function registerFilter($filter)
    {
        if($filter instanceof \Closure) {
            throw new \InvalidArgumentException('Type "Closure" is not allowed for filter!');
        }

        $this->filters[] = $filter;
        return $this;
    }

    /**
     * Set the filters
     *
     * @param array $filters
     * @return LiquidCompiler
     */
    public function setFilters(array $filters)
    {
        array_map([$this, 'registerFilter'], $filters);
        return $this;
    }

    /**
     * Get the filters
     *
     * @return array
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * @param TemplateContent $path
     * @return void
     */
    public function setFileMtime($path)
    {
        if($path && !array_key_exists($path->getPath(), $this->filemtime)) {
            $this->filemtime[$path->getPath()] = $path->getFileMtime();
        }
    }

    /**
     * @param $path
     * @return TemplateContent
     */
    public function getTemplateSource($path)
    {
        $path = $this->getFinder()->find($path);
        $this->setFileMtime($path);
        return $path;
    }

    /**
     * Compile the view at the given path.
     *
     * @param  TemplateContent $path
     * @return void
     * @throws FileNotFoundException
     */
    public function compile($path)
    {
        $this->setPath($path);

        $this->setFileMtime($path);

        $templateTokens = $this->tokenize($path);

        $this->root = new Document(null, $templateTokens, null, $this);

        if($this->isExpired($path)) {
            $this->getCacheStore()->forever($this->getCompiledKey($path), (object)[
                'filemtime' => time(),
                'content' => $this->root
            ]);
        }
    }

    /**
     * Get the path to the compiled version of a view.
     *
     * @param  TemplateContent  $path
     * @return string
     */
    public function getCompiledKey($path)
    {
        return sha1($path->getPath());
    }

    /**
     * Renders the current template
     *
     * @param TemplateContent $path
     * @param array $assigns an array of values for the template
     *
     * @return string
     * @throws ErrorException
     * @throws FileNotFoundException
     * @throws LiquidException
     * @throws \ReflectionException
     */
    public function render($path, array $assigns = [])
    {
        $context = new Context($assigns);

        if($this->filters) {
            foreach ($this->filters as $filter) {
                $context->addFilter($filter);
            }
        }

        $this->root = $this->getCacheStore()->get($this->getCompiledKey($path))->content;

        $result = $this->root->render($context);

        return $result;
    }

    /**
     * Determine if the view at the given path is expired.
     *
     * @param  TemplateContent  $path
     * @return bool
     */
    public function isExpired($path)
    {
        $compiled = $this->getCompiledKey($path);

        // If the compiled file doesn't exist we will indicate that the view is expired
        // so that it can be re-compiled. Else, we will verify the last modification
        // of the views is less than the modification times of the compiled views.
        if (! $this->getCacheStore()->has($compiled)) {
            return true;
        }

        $compiledData = $this->getCacheStore()->get($compiled);
        if(empty($compiledData->filemtime) || !isset($compiledData->content)) {
            return true;
        }

        $pathLastModify = count($this->filemtime) > 0 ? max($this->filemtime) : $path->getFileMtime();

        return $pathLastModify >= $compiledData->filemtime;
    }


    public function getTextLine($text)
    {
        $pattern = '/' . preg_quote($text, '/') . '/i';
        $lineNumber = 0;
        if ($this->getPath() && preg_match($pattern, $content = $this->getPath()->getContent(), $matches, PREG_OFFSET_CAPTURE)) {
            //PREG_OFFSET_CAPTURE will add offset of the found string to the array of matches
            //now get a substring of the offset length and explode it by \n
            $lineNumber = count(explode(PHP_EOL, substr($content, 0, $matches[0][1])));
        }

        return $lineNumber;
    }

    /**
     * @return Repository|\Illuminate\Contracts\Cache\Repository
     */
    public function getCacheStore()
    {
        return cache()->store(config('liquid.compiled_store', 'file'));
    }

    /**
     * Get the evaluated contents of the view.
     *
     * @param TemplateContent $path
     * @param array $data
     * @return string|null
     * @throws Throwable
     */
    public function get($path, array $data = [])
    {
        $obLevel = ob_get_level();
        try {
            $this->compile($path);

            return $this->render($path, $data);
        } catch (Throwable $e) {
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }

            throw $e;
        }
    }
}
