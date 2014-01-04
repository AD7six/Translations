<?php
App::uses('PoParser', 'Translations.Parser');

class PotParser extends PoParser {

	public static function parse($file, $defaults = array()) {
		$return = parent::parse($file, $defaults);
		$return['settings']['overwrite'] = false;

		return $return;
	}

/**
 * Return the string for one translation entry
 *
 * @param mixed $msgid
 * @param mixed $details
 * @param mixed $paths
 * @return string
 */
	protected static function _writeTranslation($msgid, $details, $paths) {
		$details['value'] = '';
		return parent::_writeTranslation($msgid, $details, $paths);
	}

/**
 * The plural rule to use in the pot file header
 *
 * @param string $locale
 * @return string
 */
	protected static function _pluralRule($locale) {
		return 'nplurals=INTEGER; plural=EXPRESSION;';
	}

}
