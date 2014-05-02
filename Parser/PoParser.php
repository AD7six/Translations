<?php
App::uses('Parser', 'Translations.Parser');

class PoParser extends Parser {

	protected static $_translations = [];

/**
 * parse
 *
 * Force the domain to the filename
 *
 * @param string $file
 * @param array $defaults
 * @return array
 */
	public static function parse($file, $defaults = array()) {
		$domain = pathinfo($file, PATHINFO_FILENAME);
		if (preg_match('@Locale/(\w+)/(\w+)/(\w+)\.po$@', $file, $matches)) {
			list(, $locale, $category, $domain) = $matches;
		}
		$defaults = compact('locale', 'cateogry', 'domain') + $defaults + Translation::config();

		$file = fopen($file, 'r');
		$type = 0;
		$return = array(
			'count' => 0,
			'translations' => array(),
			'settings' => array(
				'domain' => $domain
			)
		);

		$defaultItem = $item = array(
			'locale' => $defaults['locale'],
			'domain' => $defaults['domain'],
			'category' => $defaults['category'],
			'key' => null,
			'value' => null
		);
		$plural = 0;
		$section = 'header';
		static::$_translations = [];

		do {
			$line = trim(fgets($file));

			if ($line === '') {
				$section = null;

				static::_store($item);
				$item = $defaultItem;
				continue;
			}

			if ($section === 'header') {
		   		if (preg_match("/msg(id|str)\s+\"\"$/i", $line)) {
					continue;
				}

				if ($line[0] == "#") {
					if (strlen($line) > 1 && $line[1] === ',') {
						//$return['flags'][trim(substr($line, 2))] = true;
					} else {
						//$return['comments'][] = (string)substr($line, 2);
					}
				} elseif (preg_match("/^\"(.*?):(.*)\"$/i", $line, $regs)) {
					//$return['meta'][$regs[1]] = trim(stripcslashes($regs[2]));
				}

				continue;
			}

			if ($line[0] == "#") {
				$section = 'comments';

				if ($line[1] === '.') {
					$item['extractedComments'][] = trim(substr($line, 2));
				} elseif ($line[1] === ':') {
					$item['references'][] = trim(substr($line, 2));
				} elseif ($line[1] === ',') {
					$item['flags'][trim(substr($line, 2))] = true;
				} elseif ($line[1] === '|') {
					$item['previous'][] = trim(substr($line, 2));
				} elseif (trim(substr($line, 1))) {
					$item['comments'][] = substr($line, 2);
				}
				continue;
			}

		   	if (preg_match("/msgid\s+\"(.*)\"$/i", $line, $regs)) {
				$section = 'key';

				$item['key'] = stripcslashes($regs[1]);
				continue;
			}

			if (preg_match("/msgid_plural\s+\"(.*)\"$/i", $line, $regs)) {
				$section = 'plural';

				$item['plural'] = stripcslashes($regs[1]);
				continue;
			}

			if (preg_match("/msgstr\s+\"(.*)\"$/i", $line, $regs)) {
				$section = 'value';

				$item['value'] = stripcslashes($regs[1]);
				continue;
			}

			if (preg_match("/msgstr\[(\d+)\]\s+\"(.*)\"$/i", $line, $regs)) {
				$section = 'piural_value';

				$pluralCase = $regs[1];
				$item['plural_value'][$pluralCase] = stripcslashes($regs[2]);
				continue;
			}

			if (preg_match("/^\"(.*)\"$/i", $line, $regs)) {
				if ($section === 'plural_value') {
					$item['plural_value'][$pluralCase] .= stripcslashes($regs[1]);
					continue;
				}

				$item[$section] .= stripcslashes($regs[1]);
				continue;
			}
		} while (!feof($file));
		fclose($file);

		if (isset($item['key'])) {
			static::_store($item);
		}

		foreach ($return as &$val) {
			if (is_string($val)) {
				$val = trim($val);
			}
		}
		$return['translations'] = array_values(static::$_translations);
		$return['count'] = count($return['translations']);

		return $return;
	}

/**
 * generate
 *
 * @param array $array
 * @return string
 */
	public static function generate($array = array()) {
		$paths[] = realpath(APP) . DS;
		$paths[] = realpath(ROOT) . DS;

		extract($array);

		$return = static::_writeHeader($array);

		foreach ($translations as $msgid => $details) {
			$return .= static::_writeTranslation($msgid, $details, $paths);
		}

		return $return;
	}

/**
 * Return a po/pot file header
 *
 * @param array $array
 * @return string
 */
	protected static function _writeHeader($array = array()) {
		$domain = $array['domain'];
		$locale = $array['locale'];
		$pluralRule = static::_pluralRule($array['locale']);

		$output = "# $locale translations for '$domain'\n";
		$output .= "# Copyright YEAR NAME <EMAIL@ADDRESS>\n";
		$output .= "#\n";
		//$output .= "#, fuzzy\n";
		$output .= "msgid \"\"\n";
		$output .= "msgstr \"\"\n";
		$output .= "\"Project-Id-Version: PROJECT VERSION\\n\"\n";
		$output .= "\"POT-Creation-Date: " . date("Y-m-d H:iO") . "\\n\"\n";
		$output .= "\"PO-Revision-Date: YYYY-mm-DD HH:MM+ZZZZ\\n\"\n";
		$output .= "\"Last-Translator: NAME <EMAIL@ADDRESS>\\n\"\n";
		$output .= "\"Language-Team: LANGUAGE <EMAIL@ADDRESS>\\n\"\n";
		$output .= "\"MIME-Version: 1.0\\n\"\n";
		$output .= "\"Content-Type: text/plain; charset=utf-8\\n\"\n";
		$output .= "\"Content-Transfer-Encoding: 8bit\\n\"\n";
		$output .= "\"Plural-Forms: $pluralRule\\n\"\n\n";
		return $output;
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
		$plural = false; // TODO $details['msgid_plural'];

		if (!is_array($details)) {
			$details = array(
				'value' => $details
			);
		}

		$value = $details['value'];
		if ($value === $msgid) {
			$value = '';
		}
		$header = '';

		if (!empty($details['references'])) {
			$occurrences = implode("\n#: ", $details['references']);
			$header = '#: ' . str_replace(DS, '/', str_replace($paths, '', $occurrences)) . "\n";
		}

		if ($plural === false) {
			$sentence = "msgid \"$msgid\"\n";
			$sentence .= "msgstr \"$value\"\n\n";
		} else {
			$sentence = "msgid \"$msgid\"\n";
			$sentence .= "msgid_plural \"{$plural}\"\n";
			$sentence .= "msgstr[0] \"\"\n";
			$sentence .= "msgstr[1] \"\"\n\n";
		}

		return $header . $sentence;
	}

/**
 * The plural rule to use in the pot file header
 *
 * @param string $locale
 * @return string
 */
	protected static function _pluralRule($locale) {
		return Translation::pluralRule($locale);
	}

	protected static function _store($item) {
		if (isset($item['plural'])) {
			$id = $item['plural'];

			$template = $item;
			$template = array_intersect_key($template, array_flip(['locale', 'domain', 'category']));

			foreach ($item['plural_value'] as $case => $value) {

				if ($case === 0) {
					static::$_translations[$item['key']] = $template + [
						'key' => $item['key'],
						'value' => $value ?: $item['key'],
					];
				}

				static::$_translations[$id . '[' . $case . ']'] = $template + [
					'key' => $id,
					'value' => $value ?: $id,
					'single_key' => $item['key'],
					'plural_case' => (int)$case
				];
			}

			return;
		}

		$id = $item['key'];
		if (!$id) {
			return;
		}

		$item['value'] = (isset($item['value']) && $item['value'] !== '') ? $item['value'] : $id;
		static::$_translations[$id] = $item;
	}
}
