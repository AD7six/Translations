<?php
App::uses('Parser', 'Translations.Parser');

class PoParser extends Parser {

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
		$filename = preg_replace('@\.pot?$@', '', basename($file));
		$defaults = array('domain' => $filename) + $defaults + Translation::config();
		$file = fopen($file, 'r');
		$isHeader = true;
		$type = 0;
		$return = array(
			'count' => 0,
			'translations' => array(),
			'settings' => array(
				'domain' => $filename
			)
		);
		$comments = $extractedComments = $references = $flags = $previous = $translations = array();
		$msgid = $msgid_plural = "";
		$plural = 0;

		do {
			$line = trim(fgets($file));
			if (!$line) {
				continue;
			} elseif ($line[0] == "#") {
				if (!empty($line[1])) {
					if ($line[1] === '.') {
						$extractedComments[] = trim(substr($line, 2));
					} elseif ($line[1] === ':') {
						$references[] = trim(substr($line, 2));
					} elseif ($line[1] === ',') {
						//$flags[trim(substr($line, 2))] = true;
					} elseif ($line[1] === '|') {
						$previous[] = trim(substr($line, 2));
					}
				} elseif (trim(substr($line, 1))) {
					if ($isHeader) {
						$return['comments'][] = substr($line, 2);
					} else {
						$comments[] = substr($line, 2);
					}
				}
				continue;
			}

			if (preg_match("/msgid\s+\"(.+)\"$/i", $line, $regs)) {
				$type = 1;
				$msgid = stripcslashes($regs[1]);
			} elseif (preg_match("/msgid\s+\"\"$/i", $line, $regs)) {
				$type = 2;
				$msgid = "";
			} elseif (preg_match("/^\"(.*)\"$/i", $line, $regs) && ($type == 1 || $type == 2 || $type == 3)) {
				$type = 3;
				$msgid .= stripcslashes($regs[1]);
			} elseif (preg_match("/msgstr\s+\"(.+)\"$/i", $line, $regs) && ($type == 1 || $type == 3) && $msgid) {
				$translations[$msgid] = array(
					'locale' => $defaults['locale'],
					'domain' => $defaults['domain'],
					'category' => $defaults['category'],
					'key' => $msgid,
					'value' => stripcslashes($regs[1]) ?: $msgid
				) + array_filter(array(
					'comments' => $comments,
					'extractedComments' => $extractedComments,
					'references' => $references,
					'flags' => $flags,
					'previous' => $previous,
				));
				$isHeader = false;
				$comments = $extractedComments = $references = $flags = $previous =array();
				$type = 4;
			} elseif (preg_match("/msgstr\s+\"\"$/i", $line, $regs) && ($type == 1 || $type == 3) && $msgid) {
				$type = 4;
				$translations[$msgid] = array(
					'locale' => $defaults['locale'],
					'domain' => $defaults['domain'],
					'category' => $defaults['category'],
					'key' => $msgid,
					'value' => $msgid
				) + array_filter(array(
					'comments' => $comments,
					'extractedComments' => $extractedComments,
					'references' => $references,
					'flags' => $flags,
					'previous' => $previous,
				));
				$isHeader = false;
				$comments = $extractedComments = $references = $flags = $previous =array();
			} elseif (preg_match("/^\"(.*)\"$/i", $line, $regs) && $type == 4 && $msgid) {
				$translations[$msgid]['msgstr'] .= stripcslashes($regs[1]);
			} elseif (preg_match("/msgid_plural\s+\"(.+)\"$/i", $line, $regs)) {
				$type = 6;
				$msgid_plural = stripcslashes($regs[1]);
			} elseif (preg_match("/^\"(.*)\"$/i", $line, $regs) && $type == 6 && $msgid) {
				$type = 6;
			} elseif (preg_match("/msgstr\[(\d+)\]\s+\"(.+)\"$/i", $line, $regs) && ($type == 6 || $type == 7) && $msgid) {
				if (!$regs[1]) {
					$translations[$msgid] = array(
						'locale' => $defaults['locale'],
						'domain' => $defaults['domain'],
						'category' => $defaults['category'],
						'key' => $msgid,
						'value' => $regs[2] ?: $msgid
					) + array_filter(array(
						'comments' => $comments,
						'extractedComments' => $extractedComments,
						'references' => $references,
						'flags' => $flags,
						'previous' => $previous,
					));
				}

				$key = sprintf('%s[%d]', $msgid, $regs[1]);
				$translations[$key] = array(
					'locale' => $defaults['locale'],
					'domain' => $defaults['domain'],
					'category' => $defaults['category'],
					'key' => $msgid_plural,
					'value' => $regs[2] ?: $msgid_plural,
					'single_key' => $msgid,
					'plural_case' => (int)$regs[1]
				) + array_filter(array(
					'comments' => $comments,
					'extractedComments' => $extractedComments,
					'references' => $references,
					'flags' => $flags,
					'previous' => $previous,
				));

				$isHeader = false;
				if ($regs[1]) { // @todo temporary fix, only clear these variables for the not-0-case plural
					$comments = $extractedComments = $references = $flags = $previous = array();
				}
				$type = 7;
			} elseif (preg_match("/msgstr\[(\d+)\]\s+\"\"$/i", $line, $regs) && ($type == 6 || $type == 7) && $msgid) {
				$plural = 'msgstr_' . $regs[1];

				$translations[$msgid] = array(
					'locale' => $defaults['locale'],
					'domain' => $defaults['domain'],
					'category' => $defaults['category'],
					'key' => $msgid,
					'value' => $msgid,
				) + array_filter(array(
					'comments' => $comments,
					'extractedComments' => $extractedComments,
					'references' => $references,
					'flags' => $flags,
					'previous' => $previous,
				));

				$translations[$msgid_plural . '[' . $regs[1] . ']'] = array(
					'locale' => $defaults['locale'],
					'domain' => $defaults['domain'],
					'category' => $defaults['category'],
					'key' => $msgid_plural,
					'value' => $msgid_plural,
					'single_key' => $msgid,
					'plural_case' => (int)$regs[1]
				) + array_filter(array(
					'comments' => $comments,
					'extractedComments' => $extractedComments,
					'references' => $references,
					'flags' => $flags,
					'previous' => $previous,
				));

				$isHeader = false;
				$type = 7;
			} elseif (preg_match("/^\"(.*)\"$/i", $line, $regs) && $type == 7 && $msgid) {
				//$translations[$msgid][$plural] .= stripcslashes($regs[1]);
			} elseif (preg_match("/msgstr\s+\"(.+)\"$/i", $line, $regs) && $type == 2 && !$msgid) {
				$type = 5;
			} elseif (preg_match("/msgstr\s+\"\"$/i", $line, $regs) && !$msgid) {
				$type = 5;
			} elseif (preg_match("/^\"(.*?):(.*)\"$/i", $line, $regs) && $type == 5) {
				//$return[$regs[1]] = stripcslashes($regs[2]);
			} else {
				unset($translations[$msgid]);
				$type = 0;
				$msgid = "";
				$plural = null;
			}
		} while (!feof($file));

		fclose($file);

		foreach ($return as &$val) {
			if (is_string($val)) {
				$val = trim($val);
			}
		}
		$return['translations'] = array_values($translations);
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
		$pluralRule = static::_pluralRule($array['locale']);

		$output = "# LANGUAGE translation for domain '$domain'\n";
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

	protected static function _pluralRule($locale) {
		return Translation::pluralRule($locale);
	}
}
