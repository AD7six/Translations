<?php
App::uses('TranslationsAppModel', 'Translations.Model');
App::uses('Nodes\L10n', 'Translations.Lib');
App::uses('PluralRule', 'Translations.Lib');
App::uses('CakeRequest', 'Network');

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
		),
		'value' => array(
			'notempty' => array(
				'rule' => array('validateNotEmptyValue'),
			),
			'notsame' => array(
				'rule' => array('validateInheritance'),
			),
		)

	);

/**
 * Categories map.
 *
 * used to translate numeric categories (the key) to the string value
 */
	protected static $_categories = array(
		'LC_ALL',
		'LC_COLLATE',
		'LC_CTYPE',
		'LC_MONETARY',
		'LC_NUMERIC',
		'LC_TIME',
		'LC_MESSAGES'
	);

/**
 * Runtime config settings
 */
	protected static $_config = array(
		'autoPopulate' => null
	);

/**
 * Default config settings
 */
	protected static $_defaultConfig = array(
		'configured' => true,
		'locale' => 'en',
		'domain' => 'default',
		'category' => 'LC_MESSAGES',
		'useDbConfig' => 'default',
		'useTable' => 'translations',
		'cacheConfig' => 'default',
		'autoPopulate' => null,
		'supportedDomains' => array(),
		'supportedCategories' => array(),
		'supportedLocales' => array(),
	);

/**
 * Placeholder for the static model instance
 */
	protected static $_model;

/**
 * Placeholder for an array of all locales
 */
	protected static $_locales;

/**
 * Plural rules
 *
 * Incomplete list of gettext plural rules, it's incomplete because we don't need
 * to define rules for languages we'll never use.
 *
 * @link http://translate.sourceforge.net/wiki/l10n/pluralforms
 */
	protected static $_pluralRules = array(
		'default' => 'nplurals=2; plural=(n != 1)',
		'ar' => 'nplurals=6; plural= n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 ? 4 : 5;',
		'be' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2)',
		'fr' => 'nplurals=2; plural=(n > 1)',
		'ja' => 'nplurals=1; plural=0',
		'pl' => 'nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2)',
		'ru' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2)',
		'zh' => 'nplurals=1; plural=0'
	);

/**
 * Indexed array or all translations
 *
 * root
 * 	<domain name>
 * 		<locale>
 * 			<category>
 *				<translation key>
 */
	protected static $_translations = array();

/**
 * autoDetectLocale
 *
 * If a string or array of cancicates are provided  -loop over them
 * otherwise get the candiate locales from the accept-language header
 *
 * Loop over the possible locales, account for regional dialects and
 * set the currentrequest language to that locale, and return that value
 *
 * @param mixed $candidates
 * @return string matched language
 */
	public static function autoDetectLocale($candidates = null) {
		$locales = static::locales();

		if ($candidates) {
			if (is_string($candidates)) {
				$candidates = explode(',', $candidates);
			}
		} else {
			$candidates = CakeRequest::acceptLanguage();
		}

		$candidates = array_filter(
			$candidates,
			function($in) {
				return strpos($in, 'q=') === false;
			}
		);

		$permutations = array();
		foreach ($candidates as $langKey) {
			if (strlen($langKey) === 5) {
				$permutations[] = substr($langKey, 0, 2) . '_' . strtoupper(substr($langKey, -2, 2));
			}
			$permutations[] = substr($langKey, 0, 2);
		}
		$permutations = array_unique($permutations);

		$match = false;
		foreach ($permutations as $langKey) {
			if (!empty($locales[$langKey])) {
				Configure::write('Config.language', $langKey);
				$match = $langKey;
				break;
			}
		}

		return $match;
	}

/**
 * beforeSave
 *
 * Json encode any field which is an array
 *
 * @param array $options
 * @return boolean
 */
	public function beforeSave($options = array()) {
		if (isset($this->data[$this->alias]['value'])) {
			$this->data[$this->alias]['value'] = $this->correctPercent($this->data[$this->alias]['value']);
		}
		$fields = array(
			'references',
			'history'
		);
		foreach ($fields as $field) {
			if (
				!empty($this->data[$this->alias][$field]) &&
				is_array($this->data[$this->alias][$field])
			) {
				$this->data[$this->alias][$field] =
					json_encode($this->data[$this->alias][$field]);
			}
		}
		return true;
	}

/**
 * correctPercent
 *
 * If the translation string contains NO sprintf markers or contains
 * String::replace replaement markers - do nothing. Otherwise ensure
 * that a loose % is escaped as %%
 *
 * @param string $string
 * @return string
 */
	public function correctPercent($string) {
		if (
			strpos($string, '{') !== false ||
			(
				strpos($string, '%s') === false &&
				strpos($string, '%d') === false &&
				strpos($string, '%1$') === false &&
				strpos($string, '%.') === false
			)
		) {
			return $string;
		}

		return preg_replace('/(?<!%)%(?![%bcdeEfFgGosuxX\d\.])/', '%%', $string);
	}

/**
 * validateNotEmptyValue
 *
 * Maintain inheritance, don't create empty translations. Allow updating existing
 * translations to an empty value though
 *
 * @return void
 */
	public function validateNotEmptyValue() {
		if ($this->id) {
			return true;
		}

		if (
			$this->data[$this->alias]['value'] === '' &&
			$this->data[$this->alias]['locale'] !== Configure::read('Config.defaultLanguage')
		) {
			return false;
		}

		return true;
	}

/**
 * validateInheritance
 *
 * Maintain inheritance, don't create duplicate translations
 *
 * @return boolean
 */
	public function validateInheritance() {
		if (
			!empty($this->data[$this->alias]['locale']) &&
			!empty($this->data[$this->alias]['key']) &&
			$this->data[$this->alias]['locale'] !== Configure::read('Config.defaultLanguage')
		) {
			$locales = $this->_fallbackLocales($this->data[$this->alias]['locale']);
			if (count($locales) > 1) {
				$inherited = static::translate(
					$this->data[$this->alias]['key'],
					array('locale' => $locales[1]) + $this->data[$this->alias]
				);
				if ($inherited === $this->data[$this->alias]['value']) {
					if ($this->id) {
						$this->delete();
					}
					return false;
				}
			}
		}
		return true;
	}

/**
 * categories
 *
 * Return the list of all categories
 *
 * @return array
 */
	public static function categories() {
		static::config();

		if (!empty(static::$_config['supportedCategories'])) {
			return static::$_config['supportedCategories'];
		}

		return array_combine(static::$_categories, static::$_categories);
	}

/**
 * Override runtime settings
 *
 * @param array $settings
 * @return array
 */
	public static function config($settings = array()) {
		if (empty($settings) && !empty(static::$_config['configured'])) {
			return static::$_config;
		}

		if (!Configure::read('Config.language')) {
			Configure::write('Config.language', 'en');
		}

		if (!Configure::read('Config.defaultLanguage')) {
			Configure::write('Config.defaultLanguage', Configure::read('Config.language'));
		}

		$settings += array(
			'locale' => Configure::read('Config.language')
		);

		if (defined('CORE_TEST_CASES')) {
			static::$_defaultConfig['useTable'] = false;
			static::$_defaultConfig['autoPopulate'] = false;
		}

		static::$_config = $settings + static::$_config + static::$_defaultConfig;

		if (is_null(static::$_config['autoPopulate'])) {
			static::$_config['autoPopulate'] = !empty(static::$_config['useTable']);
		}
		return static::$_config;
	}

/**
 * Create a localization based on another (existing) set of translations.
 *
 * @param string $locale   The new locale to create
 * @param mixed  $settings (optional) Set of options or existing locale to fascilitate the creation
 * @return array
 */
	public function createLocale($locale, $settings = array()) {
		// Setup settings
		$defaults = array(
			'basedOn' => Configure::read('Config.language'),
			'nested'  => true
		);
		if (is_string($settings)) {
			$settings = array('basedOn' => $settings);
		}
		$settings = array_merge($defaults, $settings);

		// Validation
		if (empty($locale)) {
			$this->invalidate('locale', 'No locale selected');
			return false;
		}
		if (empty($settings['basedOn'])) {
			$this->invalidate('based_on', 'No base locale selected');
			return false;
		}
		if ($locale == $settings['basedOn']) {
			$this->invalidate('based_on', 'New locale and base locale cannot be the same');
			return false;
		}

		// Save new translations
		$translations = static::forLocale($settings['basedOn'], array('nested' => false));
		if (empty($translations)) {
			$this->invalidate('based_on', 'Base locale has no translations');
			return false;
		}
		foreach ($translations as $key => $value) {
			$translation = $this->find('first', array(
				'conditions' => array(
					'locale'         => $locale,
					'key'            => $key
				)
			));
			if (!empty($translation)) {
				continue; // skip it
			}

			$translation = $this->create(array(
				'locale'         => $locale,
				'key'            => $key,
				'value'          => $value
			));
			$this->save($translation);
		}

		// Return complete list
		return static::forLocale($locale, $settings);
	}

/**
 * domains
 *
 * Return the list of all domains in use
 *
 * @return array
 */
	public static function domains() {
		static::config();

		if (!empty(static::$_config['supportedDomains'])) {
			return static::$_config['supportedDomains'];
		}

		if (!static::_loadModel()) {
			return array();
		}
		$domains = Hash::extract(static::$_model->find('all', array(
			'fields' => array('DISTINCT domain as val')
		)), '{n}.Translation.val');
		return array_combine($domains, $domains);
	}

/**
 * forLocale
 *
 * Return the translation definitions for the passed arguments. By default translations
 * are returned as a nested array for the current domain-category-locale. To obtain a flat
 * array - pass 'nested' => false in the settings. E.g. with the following code:
 *
 * $nested = Translation::forLocale();
 * $flat = Translation::forLocale(null, array('nested' => false));
 *
 * Return values would be of the format:
 *
 * $nested = array(
 * 		'key' => array(
 * 			'one' => array(
 *				'two' => 'value'
 * 			),
 * 			'three' => 'value'
 * 		)
 * );
 *
 * $flat = array(
 * 		'key.one.two' => 'value',
 * 		'key.three' => 'value'
 * );
 *
 * @param string $locale
 * @param array $settings
 * @return array
 */
	public static function forLocale($locale = null, $settings = array()) {
		static::config();

		$settings = $settings + array(
			'nested' => true,
			'addDefaults' => true,
			'domain' => static::$_config['domain'],
			'category' => static::$_config['category'],
			'section' => null,
			'locale' => $locale ?: Configure::read('Config.language')
		);

		if (static::$_config['cacheConfig'] && $cacheKey = static::_cacheKey($settings)) {
			$cached = Cache::read($cacheKey, static::$_config['cacheConfig']);
			if (is_array($cached)) {
				return $cached;
			}
		}

		if (
			static::$_config['supportedDomains'] && !in_array($settings['domain'], static::$_config['supportedDomains']) ||
			static::$_config['supportedCategories'] && !in_array($settings['category'], static::$_config['supportedCategories']) ||
			!static::_loadModel()
		) {
			if (isset(static::$_translations[$settings['domain']][$settings['locale']][$settings['category']])) {
				return static::$_translations[$settings['domain']][$settings['locale']][$settings['category']];
			}
			return array();
		}

		$return = static::$_model->_forLocale($settings);

		if (!empty($cacheKey)) {
			Cache::write($cacheKey, $return, static::$_config['cacheConfig']);
		}

		return $return;
	}

/**
 * export
 *
 * @param mixed $file path to create or false to return the string instead
 * @param mixed $settings
 * @return mixed boolean success writing a file - or the string representation
 */
	public static function export($file, $settings = array()) {
		$settings = $settings + array(
			'locale' => Configure::read('Config.language'),
			'nested' => false,
			'addDefaults' => true,
			'domain' => 'default',
			'category' => 'LC_MESSAGES',
			'format' => null,
			'compact' => true
		);

		static::config();
		static::_loadModel();

		if ($settings['format']) {
			$format = $settings['format'];
		} else {
			if ($file) {
				$info = pathinfo($file);
				$format = $info['extension'];
			}
		}
		$settings['translations'] = static::$_model->_export($settings);

		$parserClass = ucfirst($format) . 'Parser';
		App::uses($parserClass, 'Translations.Parser');
		$return = $settings;

		$settings = array_intersect_key($settings, array(
			'locale' => true,
			'domain' => true,
			'category' => true,
			'translations' => true,
			'compact' => true
		));
		$return['count'] = count($settings['translations']);
		$return['string'] = $parserClass::generate($settings);
		$return['compact'] = $settings['compact'];

		$return['success'] = true;

		$dir = dirname($file);
		if (!is_dir($dir)) {
			mkdir($dir, 0777, true);
		}
		if ($file && !file_put_contents($file, $return['string'])) {
			$return['success'] = false;
		}
		return $return;
	}

/**
 * import translation definitions
 *
 * @param mixed $file
 * @param mixed $settings
 * @return array
 */
	public static function import($file, $settings = array()) {
		static::config();
		$settings += array(
			'locale' => Configure::read('Config.language'),
			'domain' => static::$_config['domain'],
			'category' => static::$_config['category'],
			'overwrite' => true,
			'purge' => false
		);

		$return = static::parse($file, $settings);
		if (!$return) {
			return false;
		}
		if (!empty($return['settings'])) {
			$settings = $return['settings'] + $settings;
		}

		if (!empty($settings['purge'])) {
			static::purge($return, $settings);
		}

		foreach ($return['translations'] as $translation) {
			static::update($translation['key'], $translation['value'], $translation + $settings);
		}
		return $return;
	}

/**
 * parse a translations file
 *
 * If $file is an upload, derive from the name the type of file that it is.
 * Look for a parser based on the file extension, and return the output
 *
 * @throws \CakeException if th file doens't exist
 * @param mixed $file
 * @param array $settings
 * @return array
 */
	public static function parse($file, $settings = array()) {
		static::config();

		if (is_array($file)) {
			if (!empty($file['translations'])) {
				return $file;
			}
			if (!empty($file['error'])) {
				return false;
			}
			$info = pathinfo($file['name']);
			$tmpName = $file['tmp_name'];
			$file = TMP . $file['name'];
			move_uploaded_file($tmpName, $file);
		} else {
			if (false !== strstr($file, 'http://') || false !== strstr($file, 'https://')) {
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $file);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_TIMEOUT, 10);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				$content = curl_exec($ch);
				curl_close($ch);

				$info = pathinfo($file);
				$file = TMP . time() . '.json';
				file_put_contents($file, $content);
			} else {
				$content = file_get_contents($file);
			}

			if (!file_exists($file)) {
				throw new \CakeException("File doesn't exist");
			}
			$info = pathinfo($file);
		}

		$parserClass = ucfirst($info['extension']) . 'Parser';
		App::uses($parserClass, 'Translations.Parser');

		return $parserClass::parse($file, $settings);
	}

/**
 * Purge
 *
 * Delete translation entries which do not exist in the import file
 *
 * @param mixed $file
 * @param array $settings
 * @return array
 */
	public static function purge($file, $settings = array()) {
		$return = static::parse($file, $settings);
		$keepIds = Hash::extract($return['translations'], '{n}.key');

		$settings += array(
			'locale' => Configure::read('Config.language'),
			'domain' => static::$_config['domain'],
			'category' => static::$_config['category'],
		);

		extract($settings);
		static::$_translations[$domain][$locale][$category] = array_intersect_key(
			static::$_translations[$domain][$locale][$category],
			array_flip($keepIds)
		);
		if (!static::_loadModel()) {
			return true;
		}

		$conditions = array(
			'locale' => $settings['locale'],
			'domain' => $settings['domain'],
			'category' => $settings['category']
		);
		$toRemove = static::$_model->find('list', array(
			'conditions' => $conditions,
			'fields' => array('key', 'value'),
		));

		foreach ($toRemove as $id => $value) {
			if (in_array($id, $keepIds)) {
				unset($toRemove[$id]);
				continue;
			}
			static::$_model->id = static::$_model->field('id', $conditions + array('key' => $id));
			static::$_model->delete();
		}

		return array_keys($toRemove);
	}

/**
 * Lists the avaliable locales.
 *
 * @param mixed   $restrictTo (optional)
 *                    false show all used locales (default)
 *                    true  show all locales
 *                    array return only these locales
 *                    string comma seperated list of locales
 * @param array   $options (optional) List of options
 * @return array
 */
	public static function locales($restrictTo = false, $options = array()) {
		static::config();

		if (!empty(static::$_config['supportedLocales'])) {
			return static::$_config['supportedLocales'];
		}

		// Setup options
		$defaults = array(
			'query' => array(
				'fields' => 'Translation.locale',
				'group'  => 'Translation.locale'
			)
		);
		$options = array_merge($defaults, $options);

		// Load languages
		if (!static::$_locales) {
			$l10n = new \Nodes\L10n();
			$locales = $l10n->getLocales();
			static::$_locales = array_map(function($v) {
				return $v['language'];
			}, $locales);
		}

		if ($restrictTo === true) {
			return static::$_locales;
		} elseif (!$restrictTo) {
			$restrictTo = Configure::read('Application.locales');
		}

		$return = array();
		if ($restrictTo) {
			if (is_string($restrictTo)) {
				$restrictTo = explode(',', $restrictTo);
			}
			foreach ($restrictTo as $locale) {
				if (isset(static::$_locales[$locale])) {
					$return[$locale] = static::$_locales[$locale];
				}
			}
		} elseif (static::_loadModel()) {
			$localesUsed = static::$_model->find('all', $options['query']);
			foreach ($localesUsed as $locale) {
				$return[$locale['Translation']['locale']] = static::$_locales[$locale['Translation']['locale']];
			}
		}

		return $return;
	}

/**
 * translate
 *
 * Used by the translation functions in override_i18n in this plugin
 * Returns a translated string based on current locale and translations in the db
 *
 * @param string $singular string to translate
 * @param array $options
 * @return string translated string
 */
	public static function translate($singular, $options = array()) {
		static::config();
		$options += array(
			'plural' => null,
			'domain' => static::$_config['domain'],
			'category' => static::$_config['category'],
			'count' => null,
			'locale' => !empty($_SESSION['Config']['language']) ? $_SESSION['Config']['language'] : Configure::read('Config.language'),
			'autoPopulate' => false
		);

		$domain = $options['domain'];
		$locale = $options['locale'];
		$category = $options['category'];

		$pluralCase = false;
		if (isset($options['count']) && (int)$options['count'] !== 1) {
			$pluralCase = static::_pluralCase($options['count'], $options['locale']);
		}

		if ($pluralCase !== false) {
			$options['pluralCase'] = $pluralCase;
			$key = $options['plural'];
		} else {
			$key = $singular;
		}

		if (is_numeric($category)) {
			$category = static::$_categories[$category];
			$options['category'] = $category;
		}

		if (static::hasTranslation($key, $options)) {
			$return = static::$_translations[$domain][$locale][$category][$key];
			if (is_array($return)) {
				if ($pluralCase !== false && isset($return[$pluralCase])) {
					return $return[$pluralCase];
				}
				return current($return);
			}
			return $return;
		}

		if ($options['autoPopulate']) {
			if (!is_null($pluralCase)) {
				static::$_translations[$domain][$locale][$category][$key][$pluralCase] = $key;
			} else {
				static::$_translations[$domain][$locale][$category][$key] = $key;
			}
			static::$_model->create();
			static::$_model->save(array(
				'domain' => $options['domain'],
				'category' => $options['category'],
				'locale' => Configure::read('Config.defaultLanguage'),
				'key' => $key,
				'value' => $key,
				'plural_case' => $pluralCase
			));
		}
		return $key;
	}

/**
 * update
 *
 * Update one translation
 *
 * @param mixed $key
 * @param array $options
 * @return void
 */
	public static function update($key, $value = '', $options = array()) {
		static::config();
		$options += array(
			'domain' => static::$_config['domain'],
			'category' => static::$_config['category'],
			'locale' => Configure::read('Config.language'),
			'overwrite' => true
		);
		extract($options);

		if (isset($plural_case)) {
			$exists = isset(static::$_translations[$domain][$locale][$category][$key][$plural_case]);
			if (!$exists || $overwrite) {
				static::$_translations[$domain][$locale][$category][$key][$plural_case] = $value;
			}
		} else {
			$exists = isset(static::$_translations[$domain][$locale][$category][$key]);
			if (!$exists || $overwrite) {
				static::$_translations[$domain][$locale][$category][$key] = $value;
			}
		}

		if (static::_loadModel()) {
			$update = compact('key') + array_intersect_key(
				$options,
				array_flip(array('domain', 'locale', 'category', 'plural_case'))
			);
			static::$_model->create();
			static::$_model->id = static::$_model->field('id', $update);

			if (!$overwrite && static::$_model->id) {
				return false;
			}

			$update += array_intersect_key(
				$options,
				array_flip(array('comments', 'references'))
			);
			return static::$_model->save($update + array('value' => $value));
		}
		return false;
	}

/**
 * reset
 *
 * Reset the static state
 *
 * @return void
 */
	public static function reset() {
		static::$_config = static::$_defaultConfig;
		static::$_model = null;
		static::$_translations = null;
	}

/**
 * hasTranslation
 *
 * Check if a translation exists for the given translation key.
 *
 * @param mixed $key
 * @param array $options
 * @return boolean
 */
	public static function hasTranslation($key, $options = array()) {
		static::config();
		$options += array(
			'domain' => static::$_config['domain'],
			'category' => static::$_config['category'],
			'locale' => !empty($_SESSION['Config']['language']) ? $_SESSION['Config']['language'] : Configure::read('Config.language'),
		);

		$domain = $options['domain'];
		$category = $options['category'];
		$locale = $options['locale'];

		if (!isset(static::$_translations[$domain][$locale][$category])) {
			$options['nested'] = false;
			static::$_translations[$domain][$locale][$category] = static::forLocale($locale, $options);
		}

		if (array_key_exists($key, static::$_translations[$domain][$locale][$category])) {
			if (isset($options['pluralCase'])) {
				return array_key_exists(
					$options['pluralCase'],
					static::$_translations[$domain][$locale][$category][$key]
				);
			} else {
				return true;
			}
		}
		return false;
	}

/**
 * cacheKey
 *
 * Get the cache key to use for the given settings. Returns false if caching is disabled/badly configured
 *
 * @param array $settings
 * @return string
 */
	protected static function _cacheKey($settings) {
		$ts = Cache::read('translations-ts', static::$_config['cacheConfig']);
		if (!is_numeric($ts)) {
			$ts = time();
			if (!Cache::write('translations-ts', $ts, static::$_config['cacheConfig'])) {
				return false;
			};
		}

		$settings['nested'] = $settings['nested'] ? 'nested' : 'flat';
		$settings['addDefaults'] = $settings['addDefaults'] ? 'defaults' : 'nodefaults';

		$return = array();
		foreach (array('locale', 'domain', 'category', 'nested', 'addDefaults', 'section') as $key) {
			if ($key === 'section' && !$settings[$key]) {
				continue;
			}

			$return[] = $settings[$key];
		}
		$return[] = $ts;

		$return = strtolower(implode('-', $return));
		return $return;
	}

/**
 * _clearCache
 *
 * Whenever something touches the data bump the translations timestamp
 * This prevents the need to clear the cache, instead just rely on older cache entries
 * being cycled or gc-ed
 *
 * @param mixed $type
 * @return void
 */
	protected function _clearCache($type = null) {
		Cache::write('translations-ts', time(), static::$_config['cacheConfig']);
		parent::_clearCache();
	}

/**
 * _fallbackLocales
 *
 * Get the list of locales to itterate through when looking for translations
 *
 * @param mixed $locale
 * @return array
 */
	protected function _fallbackLocales($locale = null) {
		if ($locale) {
			$locales[] = $locale;
		} elseif (!empty($_SESSION['Config']['language'])) {
			$locales[] = $_SESSION['Config']['language'];
		}
		$locales[] = Configure::read('Config.defaultLanguage');
		$locales[] = Configure::read('Config.language');
		$locales = array_unique(array_filter($locales));

		$return = array();
		foreach ($locales as $locale) {
			if (strlen($locale) === 5) {
				$generic = substr($locale, 0, 2);
				if (!in_array($generic, $return)) {
					$return[] = $locale;
					$return[] = substr($locale, 0, 2);
				}
			} else {
				$return[] = $locale;
			}
		}
		return $return;
	}

/**
 * _loadModel
 *
 * Load the model instance, using the configured settings
 * If configured to not use a table, or attempting to load the model fails - return false
 *
 * @return void
 */
	protected static function _loadModel() {
		if (!static::$_config['useTable']) {
			return false;
		}
		if (static::$_model) {
			return true;
		}

		try {
			static::$_model = ClassRegistry::init(array(
				'class' => 'Translations.Translation',
				'table' => static::$_config['useTable'],
				'ds' => static::$_config['useDbConfig'],
			));
			static::$_model->setSource(static::$_config['useTable']);
		} catch (Exception $e) {
			static::$_config['useTable'] = false;
			return false;
		}

		return true;
	}

/**
 * _forLocale
 *
 * @param mixed $settings
 * @return array
 */
	protected function _forLocale($settings) {
		if ($settings['addDefaults']) {
			$settings['addDefaults'] = false;
			$locales = $this->_fallbackLocales($settings['locale']);
			$return = array();
			foreach ($locales as $locale) {
				$return += $this->_forLocale(compact('locale') + $settings);
			}
			return $return;
		}

		$conditions = array(
			'locale' => $settings['locale'],
			'domain' => $settings['domain'],
			'category' => $settings['category']
		);
		if (!empty($settings['section'])) {
			$conditions['key LIKE'] = $settings['section'] . '%';
		}

		$all = $this->find('all', array(
			'fields' => array('key', 'value', 'plural_case'),
			'conditions' => $conditions,
			'order' => array('key' => 'ASC')
		));

		$data = array();
		foreach ($all as $row) {
			$row = current($row);
			if (is_null($row['plural_case'])) {
				$data[$row['key']] = $row['value'];
			} else {
				$data[$row['key']][$row['plural_case']] = $row['value'];
			}
		}

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

/**
 * _export
 *
 * @param mixed $settings
 * @return array
 */
	protected function _export($settings) {
		if ($settings['addDefaults']) {
			$settings['addDefaults'] = false;
			$locales = $this->_fallbackLocales($settings['locale']);
			$return = array();
			foreach ($locales as $locale) {
				$last = $this->_export(compact('locale') + $settings);
				$return += $last;
			}

			if (!$settings['compact']) {
				foreach($last as $i => $details) {
					$return[$i] = array_filter($return[$i]) + $details;
				}
			}
			return $return;
		}

		$conditions = array(
			'locale' => $settings['locale'],
			'domain' => $settings['domain'],
			'category' => $settings['category']
		);
		if (!empty($settings['section'])) {
			$conditions['key LIKE'] = $settings['section'] . '%';
		}

		$all = $this->find('all', array(
			'conditions' => $conditions,
			'order' => array('key' => 'ASC')
		));

		$data = array();
		$keys = array_flip(array(
			'value', 'plural_case', 'comments', 'references', 'history'
		));
		foreach ($all as $row) {
			$row = current($row);
			$key = $row['key'];
			$plural = $row['plural_case'];
			$row['references'] = json_decode($row['references']);
			if ($settings['compact']) {
				$row = $row['value'];
			} else {
				$row = array_intersect_key($row, $keys);
			}
			if (is_null($plural)) {
				$data[$key] = $row;
			} else {
				$data[$key][$plural] = $row;
			}
		}

		ksort($data);

		return $data;
	}

/**
 * expand dot notation to a nested array
 *
 * @param mixed $array
 * @return array
 */
	protected function _expand($array) {
		$return = array();
		foreach ($array as $key => $value) {
			if (preg_match('/^(\w+\.)(\w+\.?)*$/', $key)) { // for keys of format xxx.yyy.zzz
				$keys = explode('.', $key);
			} else {
				$keys = array($key);
			}

			if (count($keys) > 1) {
				$this->_recursiveInsert($return, $keys, $value);
			} else {
				$return[$key] = $value;
			}
		}
		return $return;
	}

/**
 * Plural case
 *
 * Which plural form should be used
 *
 * Gettext formulas are eval-able, substitute n in the formula, and treat as a php expression
 *
 * @param string  locale
 * @return int
 */
	protected static function _pluralCase($n, $locale = null) {
		$rule = static::pluralRule($locale);

		return PluralRule::check($rule, $n);
	}

/**
 * Plural cases
 *
 * How many plural forms afer there for the given locale?
 * Assume the rule is welformed, and written as:
 *
 *	npurals=x
 *
 * Where x is the number of plural cases that exist for this locale
 *
 * @param string  locale
 * @return int
 */
	protected static function _pluralCases($locale = null) {
		$rule = static::pluralRule($locale);
		return (int)substr($rule, 9, 1);
	}

/**
 * Pluralrule
 *
 * What is the plural rule (expressed as a gettext formula) for the requested locale?
 *
 * @param string  locale
 */
	public static function pluralRule($locale = null) {
		$locale = substr($locale, 0, 2);

		if (array_key_exists($locale, static::$_pluralRules)) {
			return static::$_pluralRules[$locale];
		}
		return static::$_pluralRules['default'];
	}

/**
 * _recursiveInsert
 *
 * @param mixed $array
 * @param mixed $keys
 * @param mixed $value
 */
	protected function _recursiveInsert(&$array, $keys, $value) {
		if (!is_array($array)) {
			return;
		}
		$key = array_shift($keys);
		if (empty($keys)) {
			$array[$key] = $value;
		} else {
			if (!isset($array[$key])) {
				$array[$key] = array();
			}
			$this->_recursiveInsert($array[$key], $keys, $value);
			foreach ($array[$key] as $k => $v) {
				$array[$key][$k] = $v; // array_merge treats string and number keys differently so we have to do it manually
			}
		}
	}
}
