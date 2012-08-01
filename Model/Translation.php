<?php
App::uses('TranslationsAppModel', 'Translations.Model');
/**
 * Translation Model
 *
 * @property Application $Application
 */
class Translation extends TranslationsAppModel {

/**
 * Validation rules
 *
 * @var array
 */
	public $validate = array(
		'locale' => array(
			'notempty' => array(
				'rule' => array('notempty'),
			),
		),
		'key' => array(
			'notempty' => array(
				'rule' => array('notempty'),
			),
		)
	);

	protected static $_locale = 'en';

	protected static $_model;

	protected static $_translations = array();

/**
 * forLocale
 *
 * @param string $locale
 * @param mixed $addDefaults
 * @return
 */
	public function forLocale($locale = "en", $settings = array()) {
		$settings = $settings + array('nested' => true, 'addDefaults' => true, 'section' => null);

		if ($settings['addDefaults']) {
			$locales = array_unique(array(
				$locale,
				substr($locale, 0, 2),
				'en',
			));

			$settings['addDefaults'] = false;
			$return = array();
			foreach ($locales as $locale) {
				$return += $this->forLocale($locale, $settings);
			}
			return $return;
		}

		$conditions = array(
			'locale' => $locale
		);
		if (!empty($settings['section'])) {
			$conditions['key LIKE'] = $settings['section'] . '%';
		}
		$data = $this->find('list', array(
			'fields' => array('key', 'value'),
			'conditions' => $conditions,
			'order' => array('key' => 'ASC')
		));

		if (!$settings['section']) {
			ksort($data);
		}

		if ($settings['nested'] && $data) {
			$data = $this->_expand($data);
			if ($settings['section']) {
				$keys = explode('.', $settings['section']);

				while ($keys) {
					$key = array_shift($keys);
					if (!array_key_exists($key, $data)) {
						$data = array();
						break;
					}
					$data = $data[$key];
				}
			}
		}
		return $data;
	}

	static public function translate($key, $pluralKey = null, $options = array()) {
		if (is_array($pluralKey)) {
			$options = $pluralKey;
			$pluralKey = null;
		}
		$options += array(
			'domain' => 'default',
			'category' => 'LC_MESSAGES',
			'count' => null,
			'locale' => null,
			'autoPopulate' => Nodes\Environment::isDevelopment()
		);

		if ($options['locale']) {
			self::$_locale = $locale;
		}
		$locale = self::$_locale;

		if (!self::$_model) {
			self::$_model = ClassRegistry::init('Translations.Translation');
		}
		if (!array_key_exists($locale, self::$_translations)) {
			self::$_translations[$locale] = self::$_model->forLocale($locale, array('nested' => false));
		}

		if (array_key_exists($key, self::$_translations[$locale])) {
			return self::$_translations[$locale][$key];
		}

		if ($options['autoPopulate']) {
			self::$_model->create();
			self::$_model->save(array(
				'locale' => $locale,
				'key' => $key,
				'value' => $key
			));
		}
		return $key;
	}

	public function loadPlist($file, $locale, $options = array()) {
		$doc = new DomDocument();
		if (!$doc->load($file)) {
			throw new \Exception("File could not be loaded");
		}
		$return = array(
			'create' => array(),
			'update' => array(),
			'delete' => array(),
		);

		if (!empty($options['reset'])) {
			$return['delete'] = $this->find('list', array(
				'conditions' => array('locale' => $locale),
				'fields' => array('key', 'key'),
			));
			$this->deleteAll(array('locale' => $locale));
		}

		$array = $this->_parsePlist($doc);
		$parsed = array();
		$this->_flatten($array, $parsed);

		foreach ($parsed as $key => $value) {
			$this->create();
			$this->id = $this->field('id', array(
				'key' => $key,
				'locale' => $locale
			));
			if (!empty($return['delete'][$key])) {
				$return['update'][] = $key;
				unset($return['delete'][$key]);
			} elseif ($this->id) {
				$return['update'][] = $key;
			} else {
				$return['create'][] = $key;
			}

			$this->save(array(
				'key' => $key,
				'value' => $value,
				'locale' => $locale
			));
		}

		return $return;
	}

/**
 * expand dot notation to a nested array
 *
 * TODO move all this plist parsing stuff into a seperate class
 *
 * @throws \Exception if there's too much nesting in a translation key
 * @param mixed $array
 * @return array
 */
	protected function _expand($array) {
		$return = array();
		foreach ($array as $key => $value) {
			$keys = explode('.', $key);
			$count = count($keys);
			if ($count === 1) {
				$return[$key] = $value;
			} elseif ($count === 2) {
				$return[$keys[0]][$keys[1]] = $value;
			} elseif ($count === 3) {
				$return[$keys[0]][$keys[1]][$keys[2]] = $value;
			} elseif ($count === 4) {
				$return[$keys[0]][$keys[1]][$keys[2]][$keys[3]] = $value;
			} else {
				throw new \Exception ("unhandled translation for $key");
			}
		}
		return $return;
	}

	protected function _parseValue( $valueNode ) {
		$valueType = $valueNode->nodeName;

		$transformerName = "_parse_$valueType";

		if ( is_callable(array($this, $transformerName))) {
			// there is a transformer protected function _for this node type
			return call_user_func(array($this, $transformerName), $valueNode);
		}

		// if no transformer was found
		return null;
	}

	protected function _parsePlist( $document ) {
		$plistNode = $document->documentElement;

		$root = $plistNode->firstChild;

		// skip any text nodes before the first value node
		while ( $root->nodeName == "#text" ) {
			$root = $root->nextSibling;
		}

		return $this->_parseValue($root);
	}

	protected function _flatten($array, &$return, $prefix = '') {
		if (!$array) {
			return;
		}
		$keys = array_keys($array);
		foreach ($array as $key => $value) {
			if ($keys[0] === 0) {
				$key += 1;
			}
			if (is_array($value)) {
				$this->_flatten($value, $return, ltrim($prefix . ".$key", '.'));
				continue;
			}
			if ($prefix) {
				$return[$prefix . '.' . $key] = $value;
			} else {
				$return[$key] = $value;
			}
		}
	}

	protected function _parse_integer( $integerNode ) {
		return $integerNode->textContent;
	}

	protected function _parse_string( $stringNode ) {
		return $stringNode->textContent;
	}

	protected function _parse_date( $dateNode ) {
		return $dateNode->textContent;
	}

	protected function _parse_true( $trueNode ) {
		return true;
	}

	protected function _parse_false( $trueNode ) {
		return false;
	}

	protected function _parse_dict( $dictNode ) {
		$dict = array();

		// for each child of this node
		for (
			$node = $dictNode->firstChild;
			$node != null;
			$node = $node->nextSibling
		) {
			if ( $node->nodeName == "key" ) {
				$key = $node->textContent;

				$valueNode = $node->nextSibling;

				// skip text nodes
				while ( $valueNode->nodeType == XML_TEXT_NODE ) {
					$valueNode = $valueNode->nextSibling;
				}

				// recursively parse the children
				$value = $this->_parseValue($valueNode);

				$dict[$key] = $value;
			}
		}

		return $dict;
	}

	protected function _parse_array( $arrayNode ) {
		$array = array();

		for (
			$node = $arrayNode->firstChild;
			$node != null;
			$node = $node->nextSibling
		) {
			if ( $node->nodeType == XML_ELEMENT_NODE ) {
				array_push($array, $this->_parseValue($node));
			}
		}

		return $array;
	}
}