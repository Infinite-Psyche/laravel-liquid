<?php

namespace Liquid\Filters;

use Liquid\Context;
use Liquid\Contracts\DropCollectionContract;
use Liquid\Exceptions\FilterValidateError;

abstract class AbstractFilters
{
    /**
     * @var Context
     */
    protected $context;

    final public function __construct(Context $context)
    {
        $this->context = $context;
    }

    final protected function __validate($parameters, int $total_parameters, array $validation = null)
    {
        if(($given = count($parameters)) < $total_parameters) {
            throw new FilterValidateError(sprintf(
                'wrong number of arguments (given %d, expected %d)',
                $given - 1,
                $total_parameters - 1
            ));
        }

        if($validation) {
            array_map(function($parameter, $key) use($validation) {
                if(!empty($validation[$key]) && ($rules = explode('|', $validation[$key]))) {
                    array_map(function($rule) use($parameter) {
                        if(method_exists($this, $method = sprintf('__validate%s', $rule))) {
                            $this->$method($parameter);
                        }
                    }, $rules);
                }
            }, $parameters, array_keys($parameters));
        }
    }

    private function __validateArray($input)
    {
        if(!$this->__isArray($input)) {
            throw new FilterValidateError(
                'filter requires an array argument'
            );
        }
    }

    private function __validateScalar($input)
    {
        if(!$this->__isString($input)) {
            throw new FilterValidateError(
                'filter requires an scalar argument'
            );
        }
    }

    private function __validateNumeric($input)
    {
        if(!is_numeric($input)) {
            throw new FilterValidateError(
                'filter requires an numeric argument'
            );
        }
    }

    private function __validateInt($input)
    {
        if(!preg_match('/^\d+$/', $input)) {
            throw new FilterValidateError(
                'filter requires an integer argument'
            );
        }
    }

    private function __validateNInt($input)
    {
        if(!preg_match('/^-?\d+$/', $input)) {
            throw new FilterValidateError(
                'filter requires an integer argument'
            );
        }
    }

    /**
     * Check data is string or object with toString implementation string
     *
     * @param mixed $input
     *
     * @return bool
     */
    protected function __isString($input)
    {
        return method_exists($input, '__toString') || is_scalar($input);
    }

    /**
     * Check data is array or object implement DropCollectionContract
     *
     * @param mixed $input
     *
     * @return bool
     */
    protected function __isArray($input)
    {
        return is_array($input) || $this->__isCollection($input);
    }

    /**
     * Check data is object implement DropCollectionContract
     *
     * @param mixed $input
     *
     * @return bool
     */
    protected function __isCollection($input)
    {
        return $input instanceof DropCollectionContract;
    }

}
