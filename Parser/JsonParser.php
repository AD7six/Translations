<?php
App::uses('Parser', 'Translations.Parser');

class JsonParser extends Parser {

/**
 * parse
 *
 * Load a json file, and assume it contains a flat array of translations
 * OR
 * an array of:
 * 	domain:
 * 	locale
 * 	category:
 * 	translations:
 *
 * @param string $file
 * @param array $defaults
 * @return array
 */
	public static function parse($file, $defaults = array()) {
		$translations = json_decode(file_get_contents($file), true);

		if (isset($translations['translations']) && is_array($translations['translations'])) {
			$defaults += $translations;
			unset($defaults['translations']);

			$translations = $translations['translations'];
		} else {
			if (isset($translations['data']) && is_array($translations['data'])) {
				$translations = $translations['data'];
			}
			if (isset($translations['Translation']) && is_array($translations['Translation'])) {
				$translations = $translations['Translation'];
			}
		}

		return self::_parseArray($translations, $defaults);
	}

/**
 * generate
 *
 * @param array $array
 * @return string
 */
	public static function generate($array = array()) {
		return json_encode($array,  JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}
}
