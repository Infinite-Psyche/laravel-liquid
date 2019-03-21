<?php
/**
 * Created by PhpStorm.
 * User: joro
 * Date: 21.3.2019 г.
 * Time: 17:35 ч.
 */

namespace Liquid\Filters;

use Iterator;
use Traversable;
use Illuminate\Support\Collection;

class Arr
{

    /**
     * Joins elements of an array with a given character between them
     *
     * @param array|Traversable $input
     * @param string $glue
     *
     * @return string
     */
    public static function join($input, $glue = ' ')
    {
        if($input instanceof Collection) {
            return $input->implode($glue);
        } elseif ($input instanceof Traversable) {
            $str = '';
            foreach ($input as $elem) {
                if ($str) {
                    $str .= $glue;
                }
                $str .= $elem;
            }
            return $str;
        }
        return is_array($input) ? implode($glue, $input) : $input;
    }

    /**
     * Returns the first element of an array
     *
     * @param array|\Iterator $input
     *
     * @return mixed
     */
    public static function first($input)
    {
        if($input instanceof Collection) {
            return $input->first();
        } elseif ($input instanceof Iterator) {
            $input->rewind();
            return $input->current();
        }
        return is_array($input) ? reset($input) : $input;
    }

    /**
     * Returns the last element of an array
     *
     * @param array|Traversable $input
     *
     * @return mixed
     */
    public static function last($input)
    {
        if($input instanceof Collection) {
            return $input->last();
        } elseif ($input instanceof Traversable) {
            $last = null;
            foreach ($input as $elem) {
                $last = $elem;
            }
            return $last;
        }
        return is_array($input) ? end($input) : $input;
    }

    /**
     * Map/collect on a given property
     *
     * @param array|Traversable $input
     * @param string $property
     *
     * @return string
     */
    public static function map($input, $property)
    {
        if($input instanceof Collection) {
            $input = $input->all();
        } elseif ($input instanceof Traversable) {
            $input = iterator_to_array($input);
        }
        if (!is_array($input)) {
            return $input;
        }
        return array_map(function ($elem) use ($property) {
            if (is_callable($elem)) {
                return $elem();
            } elseif (is_array($elem) && array_key_exists($property, $elem)) {
                return $elem[$property];
            }
            return null;
        }, $input);
    }

    /**
     * Reverse the elements of an array
     *
     * @param array|Traversable $input
     *
     * @return array
     */
    public static function reverse($input)
    {
        if($input instanceof Collection) {
            $input = $input->all();
        } elseif ($input instanceof Traversable) {
            $input = iterator_to_array($input);
        }
        return array_reverse($input);
    }


    /**
     * Sort the elements of an array
     *
     * @param array|Traversable|Collection $input
     * @param string $property use this property of an array element
     *
     * @return array
     */
    public static function sort($input, $property = null)
    {
        if($input instanceof Collection) {
            $input = $input->all();
        } elseif ($input instanceof \Traversable) {
            $input = iterator_to_array($input);
        }
        if ($property === null) {
            asort($input);
        } else {
            $first = reset($input);
            if ($first !== false && is_array($first) && array_key_exists($property, $first)) {
                uasort($input, function ($a, $b) use ($property) {
                    if ($a[$property] == $b[$property]) {
                        return 0;
                    }

                    return $a[$property] < $b[$property] ? -1 : 1;
                });
            }
        }

        return $input;
    }

    /**
     * Remove duplicate elements from an array
     *
     * @param array|\Traversable $input
     *
     * @return array
     */
    public static function uniq($input)
    {
        if($input instanceof Collection) {
            $input = $input->all();
        } elseif ($input instanceof \Traversable) {
            $input = iterator_to_array($input);
        }
        return array_unique($input);
    }

}