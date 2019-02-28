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

/**
 * Liquid for PHP.
 */
class Liquid
{
	/**
	 * We cannot make settings constants, because we cannot create compound
	 * constants in PHP (before 5.6).
	 *
	 * @var array configuration array
	 */
	public static $config = array(


		// Allow template names with extension in include and extends tags.
		'INCLUDE_ALLOW_EXT' => false,

		// Suffix for include files.
		'INCLUDE_SUFFIX' => 'liquid',

		// Prefix for include files.
		'INCLUDE_PREFIX' => '_',

		// Tag start.
		'TAG_START' => '{%',

		// Tag end.
		'TAG_END' => '%}',

		// Variable start.
		'VARIABLE_START' => '{{',

		// Variable end.
		'VARIABLE_END' => '}}',

		// Variable name.
		'VARIABLE_NAME' => '[a-zA-Z_][a-zA-Z_0-9.-]*',

		'QUOTED_STRING' => '"[^"]*"|\'[^\']*\'',
		'QUOTED_STRING_FILTER_ARGUMENT' => '"[^":]*"|\'[^\':]*\'',

		// Automatically escape any variables unless told otherwise by a "raw" filter
		'ESCAPE_BY_DEFAULT' => false,
	);

	/**
	 * Get a configuration setting.
	 *
	 * @param string $key setting key
	 *
	 * @return string
	 */
	public static function get($key) {
		if (array_key_exists($key, self::$config)) {
			return self::$config[$key];
		} else {
			// This case is needed for compound settings
			switch ($key) {
				case 'QUOTED_FRAGMENT':
					return self::$config['QUOTED_STRING'] . '|(?:[^\s,\|\'"]|' . self::$config['QUOTED_STRING'] . ')+';
				case 'QUOTED_FRAGMENT_FILTER_ARGUMENT':
					return self::$config['QUOTED_STRING_FILTER_ARGUMENT'] . '|(?:[^\s:,\|\'"]|' . self::$config['QUOTED_STRING_FILTER_ARGUMENT'] . ')+';
				case 'TAG_ATTRIBUTES':
					return '/(\w+)\s*\:\s*(' . self::get('QUOTED_FRAGMENT') . ')/';
				case 'TOKENIZATION_REGEXP':
					return '/(' . self::$config['TAG_START'] . '.*?' . self::$config['TAG_END'] . '|' . self::$config['VARIABLE_START'] . '.*?' . self::$config['VARIABLE_END'] . ')/';
				default:
					return null;
			}
		}
	}

	/**
	 * Changes/creates a setting.
	 *
	 * @param string $key
	 * @param string $value
	 */
	public static function set($key, $value) {
		self::$config[$key] = $value;
	}

	/**
	 * Flatten a multidimensional array into a single array. Does not maintain keys.
	 *
	 * @param array $array
	 *
	 * @return array
	 */
	public static function arrayFlatten($array) {
		$return = array();

		foreach ($array as $element) {
			if (is_array($element)) {
				$return = array_merge($return, self::arrayFlatten($element));
			} else {
				$return[] = $element;
			}
		}
		return $return;
	}

	/**
	 * All values in PHP Liquid are truthy except null and false.
	 *
	 * @param mixed $value
	 *
	 * @return bool
	 */
	public static function isTruthy($value) {
		return !self::isFalsy($value);
	}

	/**
	 * The falsy values in PHP Liquid are null and false.
	 *
	 * @param mixed $value
	 *
	 * @return bool
	 */
	public static function isFalsy($value) {
		return $value === false || $value === null;
	}
}
