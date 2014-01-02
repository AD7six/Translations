<?php
App::uses('Parser', 'Translations.Parser');
App::uses('Translation', 'Translations.Model');

abstract class Parser {

/**
 * parse
 *
 * @throws \NotImplementedException if the file cannot be loaded
 * @param string $file
 * @param array $defaults
 * @return array
 */
	public static function parse($file, $defaults = array()) {
		throw new \NotImplementedException(__CLASS__ . "::parse is not been implemented");
	}

/**
 * generate
 *
 * @throws \NotImplementedException if this method is not overriden
 * @param array $array
 * @return string
 */
	public static function generate($array = array()) {
		throw new \NotImplementedException(__CLASS__ . "::generate is not implemented");
	}

/**
 * _parseArray
 *
 * @param array $translations
 * @param array $defaults
 * @return array
 */
	protected static function _parseArray($translations, $defaults) {
		$defaults = array_intersect_key(
			$defaults,
			array_flip(array('domain', 'locale', 'category'))
		);

		$return = array();
		foreach ($translations as $key => $value) {
			if (preg_match('/^[a-z]+([A-Z][a-z]+)+$/', $key)) {
				$key = str_replace('_', '.', Inflector::underscore($key));
			}

			static::_parseArrayItem($key, $value, $defaults, $return);
		}

		return array(
			'count' => count($return),
			'translations' => $return
		);
	}

/**
 * Add found translations to the return array
 *
 * Handle the permutations value may have
 *
 *  - string
 *  - array of strings (plural case indexed)
 *  - array of details
 *  - array of array of details (plural case indexed)
 *
 * @param string $key
 * @param mixed $value
 * @param array $defaults
 * @param array $return
 */
	protected static function _parseArrayItem($key, $value, $defaults, &$return) {
		if (is_array($value) && isset($value[0])) {
			foreach ($value as $case => $val) {
				static::_parseArrayItem($key, $value[$case], array('plural_case' => $case) + $defaults, $return);
			}
			return;
		}

		if (is_array($value)) {
			$value['key'] = $key;
		} else {
			$value = compact('key', 'value');
		}

		$return[] = $defaults + $value;
	}
}
