<?php

App::uses('ModelBehavior', 'Model');
App::uses('TranslateInjector', 'Translations.Iterator');

/**
 * Translate behavior
 */
class TranslateBehavior extends ModelBehavior {

/**
 * runtime settings
 *
 * @var array
 */
	public $settings = array();

/**
 * _defaultSettings
 *
 * @var array
 */
	protected $_defaultSettings = array(
		'domain' => 'data',
		'fields' => array()
	);

/**
 * _pendingTranslations
 *
 * Stashed in beforesave, translations that are pending committing to the translations model
 *
 * @var array
 */
	protected $_pendingTranslations = array();

/**
 * setup
 *
 * Store model-alias indexed settings
 *
 * @param Model $Model
 * @param array $config
 * @return void
 */
	public function setup(Model $Model, $config = array()) {
		if (isset($config[0])) {
			$config = array(
				'fields' => $config
			);
		}

		$config += $this->_defaultSettings;
		if (!is_array($config['fields'])) {
			$config['fields'] = array($config['fields']);
		}

		foreach ($config['fields'] as &$field) {
			if (!strpos($field, '.')) {
				$field = $Model->alias . '.' . $field;
			}
		}

		$this->settings[$Model->alias] = $config;
	}

/**
 * beforeFind
 *
 * If any of the translated fields are in the query - make sure the primary key is too
 * Otherwise it's not possible to translate the entries
 *
 * @param Model $Model
 * @param array $query
 * @return mixed
 */
	public function beforeFind(Model $Model, $query) {
		if ($query['fields'] || !array_intersect($this->settings[$Model->alias]['fields'], (array)$query['fields'])) {
			return true;
		}

		$query['fields'] = (array)$query['fields'];

		$pk = $Model->alias . '.' . $Model->primaryKey;
		if (!in_array($pk, $query['fields'])) {
			$query['fields'][] = $pk;
		}
		return $query;
	}

/**
 * afterFind Callback
 *
 * @param Model $Model Model find was run on
 * @param array $results Array of model results.
 * @param boolean $primary Did the find originate on $Model.
 * @return array Modified results
 */
	public function afterFind(Model $Model, $results, $primary = false) {
		if (empty($this->settings[$Model->alias]['fields']) || empty($results[0][$Model->alias][$Model->primaryKey])) {
			return $results;
		};

		$settings = array(
			'modelAlias' => $Model->alias,
			'modelName' => $Model->name,
			'domain' => $this->settings[$Model->alias]['domain']
		);

		$iterator = new TranslateInjector($results, $this->settings[$Model->alias]['fields'], $settings);
		$results = iterator_to_array($iterator);

		return $results;
	}

/**
 * beforeSave callback.
 *
 * Prevent updating the actual db value for translated fields. _Unless_ the
 * record doesn't exist yet; in this case use the not-default value and populate
 * the default langauge with it.
 * Stash updates to a property on this behavior for retrieval in after save
 *
 * @param Model $Model Model save was called on.
 * @return boolean true.
 */
	public function beforeSave(Model $Model, $options = array()) {
		$locale = Configure::read('Config.language');
		$defaultLocale = Configure::read('Config.defaultLanguage');

		foreach ($this->settings[$Model->alias]['fields'] as $field) {
			list($alias, $field) = explode('.', $field);

			if (isset($Model->data[$Model->alias][$field])) {
				$key = sprintf('%s.%s.%s', $Model->name, ($Model->id ?: '{id}'), $field);
				$value = $Model->data[$Model->alias][$field];
				if ($locale !== $defaultLocale) {
					if ($Model->id) {
						unset($Model->data[$Model->alias][$field]);
					} else {
						$this->_pendingTranslations[$defaultLocale][$key] = $value;
					}
				}

				$this->_pendingTranslations[$locale][$key] = $value;
			}
		}
		return true;
	}

/**
 * afterSave Callback
 *
 * Loop on pending translations, and update translations
 *
 * @param Model $Model Model the callback is called on
 * @param boolean $created Whether or not the save created a record.
 * @return void */
	public function afterSave(Model $Model, $created, $options = array()) {
		if ($this->_pendingTranslations) {
			foreach ($this->_pendingTranslations as $locale => $translations) {
				$params = array(
					'domain' => $this->settings[$Model->alias]['domain'],
					'locale' => $locale,
					'autoPopulate' => true
				);

				foreach ($translations as $key => $value) {
					if (strpos($key, '{') !== false) {
						if (!$Model->id) {
							continue;
						}
						$key = String::insert($key, array('id' => $Model->id), array('before' => '{', 'after'  => '}'));
					}

					Translation::update($key, $value, $params);
				}
			}

			$this->_pendingTranslations = array();
		}
	}

}
