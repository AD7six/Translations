<?php
App::uses('Translation', 'Translations.Model');

/**
 * Translation Test Case
 *
 */
class TranslationTest extends CakeTestCase {

/**
 * Fixtures
 *
 * @var array
 */
	public $fixtures = array(
		'plugin.translations.translation',
	);

/**
 * setUp method
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();

		// Load config
		$this->config = array(
			'Config.defaultLanguage' => Configure::read('Config.defaultLanguage'),
			'Config.language' => Configure::read('Config.language'),
			'Cache.disable' => Configure::read('Cache.disable')
		);
		Configure::write('Config.defaultLanguage', 'en');
		Configure::write('Config.language', 'en');

		ClassRegistry::removeObject('Translation');
		$this->Translation = ClassRegistry::init('Translations.Translation');

		Translation::reset();
		Translation::config(array(
			'useTable' => 'translations',
			'cacheConfig' => false,
			'autoPopulate' => false
		));
	}

/**
 * tearDown method
 *
 * Reapply original config and destroy traces of the translate model
 *
 * @return void
 */
	public function tearDown() {
		foreach ($this->config as $key => $value) {
			Configure::write($key, $value);
		}

		ClassRegistry::removeObject('Translation');
		Translation::reset();

		unset($this->Translation, $this->config);

		parent::tearDown();
	}

/**
 * testCreateDefaultLocale
 *
 * @return void
 */
	public function testCreateDefaultLocale() {
		$this->Translation->deleteAll(true);

		$result = $this->Translation->save(array(
			'locale' => 'en',
			'domain' => 'test',
			'category' => 'LC_MESSAGES',
			'key' => 'yes',
			'value' => 'yes'
		));
		$this->assertTrue((bool)$result);
	}

/**
 * testCreateDefaultNotDefault
 *
 * Make sure that if the site langauge is different from defaultLanguage
 * It's still possible to create translations. This is effectively a regression test
 *
 * @return void
 */
	public function testCreateDefaultNotDefault() {
		$this->Translation->deleteAll(true);

		Configure::write('Config.defaultLanguage', 'en');
		Configure::write('Config.language', 'da');

		$result = $this->Translation->save(array(
			'locale' => 'en',
			'domain' => 'test',
			'category' => 'LC_MESSAGES',
			'key' => 'yes',
			'value' => 'yes'
		));
		$this->assertTrue((bool)$result);
	}

/**
 * testCreateEmpties
 *
 * Shouldn't be able to create empty translations
 *
 * @return void
 */
	public function testCreateEmpties() {
		$this->Translation->deleteAll(true);

		$result = $this->Translation->save(array(
			'locale' => 'en',
			'domain' => 'test',
			'category' => 'LC_MESSAGES',
			'key' => 'yes',
			'value' => ''
		));
		$this->assertTrue((bool)$result);

		$this->Translation->create();
		$result = $this->Translation->save(array(
			'locale' => 'en_GB',
			'domain' => 'test',
			'category' => 'LC_MESSAGES',
			'key' => 'no',
			'value' => '',
		));
		$this->assertFalse($result);
	}

/**
 * testCreateBlocked
 *
 * Unless it's the base language, shouldn't be possible to create empty translations.
 *
 * @return void
 */
	public function testCreateBlocked() {
		$this->Translation->deleteAll(true);

		$result = $this->Translation->save(array(
			'locale' => 'en',
			'domain' => 'test',
			'category' => 'LC_MESSAGES',
			'key' => 'yes',
			'value' => 'yes'
		));
		$this->assertTrue((bool)$result);

		$this->Translation->create();
		$result = $this->Translation->save(array(
			'locale' => 'en_GB',
			'domain' => 'test',
			'category' => 'LC_MESSAGES',
			'key' => 'yes',
			'value' => 'yes',
		));
		$this->assertFalse($result);
	}

/**
 * testUpdateBumpsCacheTs
 *
 * @return void
 */
	public function testUpdateBumpsCacheTs() {
		Configure::write('Cache.disable', false);
		$config = Translation::config(array(
			'cacheConfig' => 'default'
		));

		Cache::write('translations-ts', 42);
		$result = $this->Translation->save(array(
			'locale' => 'en',
			'domain' => 'test',
			'category' => 'LC_MESSAGES',
			'key' => 'yes',
			'value' => 'yes'
		));

		$this->assertTrue((bool)$result);

		$ts = (int)Cache::read('translations-ts');
		$this->assertNotSame(42, $ts);
	}

/**
 * testUpdateDeleted
 *
 * If a translation is edited such that it's the same as the inherited translation - it should be deleted
 *
 * @return void
 */
	public function testUpdateDeleted() {
		$this->Translation->deleteAll(true);

		$result = $this->Translation->save(array(
			'locale' => 'en',
			'domain' => 'test',
			'category' => 'LC_MESSAGES',
			'key' => 'yes',
			'value' => 'yes'
		));
		$this->assertTrue((bool)$result);

		$this->Translation->create();
		$result = $this->Translation->save(array(
			'locale' => 'en_GB',
			'domain' => 'test',
			'category' => 'LC_MESSAGES',
			'key' => 'yes',
			'value' => 'aye',
		));
		$this->assertTrue((bool)$result);

		$result = $this->Translation->save(array(
			'locale' => 'en_GB',
			'domain' => 'test',
			'category' => 'LC_MESSAGES',
			'key' => 'yes',
			'value' => 'yes',
		));

		$all = $this->Translation->find('all', array(
			'fields' => array('locale', 'domain', 'category', 'key', 'value')
		));

		$expected = array(
			array(
				'Translation' => array(
					'locale' => 'en',
					'domain' => 'test',
					'category' => 'LC_MESSAGES',
					'key' => 'yes',
					'value' => 'yes'
				)
			)
		);
		$this->assertSame($expected, $all, 'There should only be one translation');
	}

/**
 * testCategories
 *
 * @return void
 */
	public function testCategories() {
		$categories = Translation::categories();
		$expected = array(
			'LC_ALL' => 'LC_ALL',
			'LC_COLLATE' => 'LC_COLLATE',
			'LC_CTYPE' => 'LC_CTYPE',
			'LC_MONETARY' => 'LC_MONETARY',
			'LC_NUMERIC' => 'LC_NUMERIC',
			'LC_TIME' => 'LC_TIME',
			'LC_MESSAGES' => 'LC_MESSAGES'
		);

		$this->assertSame($expected, $categories);
	}

	public function testForLocaleFlat() {
		$result = $this->Translation->forLocale('en', array('nested' => false));

		$expected = array(
			'...a...b...c...' => 'Dotted key',
			'foo bar 42' => 'Non-namespaced key',
			'key.with.param' => 'Value with {param}',
			'key_one' => 'Value One',
			'key_two' => 'Value Two',
			'nested.key.one' => 'Nested Value One',
			'nested.key.two' => 'Nested Value Two',
			'numerical.key.0' => 'Numerical Value One',
			'numerical.key.1' => 'Numerical Value Two',
			'super.duper.nested.key.of.doom' => 'Super duper nested key of doom'
		);

		$this->assertSame($expected, $result);
	}

	public function testForLocaleNested() {
		$result = $this->Translation->forLocale();

		$expected = array(
			'...a...b...c...' => 'Dotted key',
			'foo bar 42' => 'Non-namespaced key',
			'key' => array(
				'with' => array(
					'param' => 'Value with {param}'
				)
			),
			'key_one' => 'Value One',
			'key_two' => 'Value Two',
			'nested' => array (
				   'key' => array (
					   'one' => 'Nested Value One',
					   'two' => 'Nested Value Two'
				   )
			),
			'numerical' => array (
				   'key' => array (
					   'Numerical Value One',
					   'Numerical Value Two'
				   )
			),
			'super' => array(
				'duper' => array(
					'nested' => array(
						'key' => array(
							'of' => array(
								'doom' => 'Super duper nested key of doom'
							)
						)
					)
				)
			)
		);

		$this->assertSame($expected, $result);
	}

	public function testForLocaleSection() {
		$result = $this->Translation->forLocale('en', array('section' => 'key'));

		$expected = array(
			'with' => array(
				'param' => 'Value with {param}'
			)
		);

		$this->assertSame($expected, $result);
	}

	public function testForSettingLanguageConfig() {
		Configure::write('Config.language', 'no');
		$result = $this->Translation->forLocale();

		$expected = array(
			'...a...b...c...' => 'Prikkete nøkkel',
			'foo bar 42' => 'Ikke-navnplass nøkkel',
			'key' => array(
				'with' => array(
					'param' => 'Verdi med {param}'
				)
			),
			'key_one' => 'Verdi En',
			'key_two' => 'Verdi To',
			'nested' => array (
				   'key' => array (
					   'one' => 'Dyp Verdi En',
					   'two' => 'Dyp Verdi To'
				   )
			),
			'numerical' => array (
				   'key' => array (
					   'Tall Verdi En',
					   'Tall Verdi To'
				   )
			),
			'super' => array(
				'duper' => array(
					'nested' => array(
						'key' => array(
							'of' => array(
								'doom' => 'Super duper nøstet nøkkel av doom'
							)
						)
					)
				)
			)
		);

		$this->assertSame($expected, $result);
	}

	public function testForDefaultTranslate() {
		$result = Translation::translate('key_one');
		$expected = 'Value One';
		$this->assertSame($expected, $result);

		$result = Translation::translate('nested.key.one');
		$expected = 'Nested Value One';
		$this->assertSame($expected, $result);

		$result = Translation::translate('numerical.key.0');
		$expected = 'Numerical Value One';
		$this->assertSame($expected, $result);
	}

	public function testForChangedLocaleTranslate() {
		Configure::write('Config.language', 'no');
		$result = Translation::translate('key_two');
		$expected = 'Verdi To';
		$this->assertSame($expected, $result);

		$result = Translation::translate('nested.key.two');
		$expected = 'Dyp Verdi To';
		$this->assertSame($expected, $result);

		$result = Translation::translate('numerical.key.1');
		$expected = 'Tall Verdi To';
		$this->assertSame($expected, $result);
	}

	public function testForChangingLocale() {
		$result = Translation::translate('key_one');
		$expected = 'Value One';
		$this->assertSame($expected, $result);

		Configure::write('Config.language', 'no');
		$result = Translation::translate('key_one');
		$expected = 'Verdi En';
		$this->assertSame($expected, $result);

		Configure::write('Config.language', 'en');
		$result = Translation::translate('key_one');
		$expected = 'Value One';
		$this->assertSame($expected, $result);
	}

/**
 * testForMissingLocale
 *
 * If there is no language specific translations - it should use use the inheritance.
 * Config.defaultLangauge is always added as a top level fallback
 *
 * @return void
 */
	public function testForMissingLocale() {
		Configure::write('Config.language', 'de');
		$result = Translation::translate('key_one');
		$expected = 'Value One';
		$this->assertSame($expected, $result);

		Configure::write('Config.language', 'THIS IS NOT A LANGUAGE CODE');
		$result = Translation::translate('key_one');
		$expected = 'Value One';
		$this->assertSame($expected, $result);
	}

	public function testForMissingTranslation() {
		$result = Translation::translate('non-existant key');
		$expected = 'non-existant key';
		$this->assertSame($expected, $result);
	}

	public function testForHelperFunction() {
		$result = t('key_one');
		$expected = 'Value One';
		$this->assertSame($expected, $result);

		$result = t('key.with.param', array('param' => 'PARAMETER'));
		$expected = 'Value with PARAMETER';
		$this->assertSame($expected, $result);

		$result = t('key.with.param');
		$expected = 'Value with {param}';
		$this->assertSame($expected, $result);

		Configure::write('Config.language', 'no');
		$result = t('key.with.param');
		$expected = 'Verdi med {param}';
		$this->assertSame($expected, $result);
	}

	public function testForLocales() {
		$result = Translation::locales();
		$expected = array(
			'en' => 'English',
			'no' => 'Norwegian'
		);
		$this->assertSame($expected, $result);
	}

	public function testForCreateLocale() {
		$result = $this->Translation->createLocale('dk');
		$expected = $this->Translation->forLocale();
		$this->assertSame($expected, $result);
	}

	public function testForCreateLocaleBasedOn() {
		$result = $this->Translation->createLocale('dk', 'no');
		$expected = $this->Translation->forLocale('no');
		$this->assertSame($expected, $result);
	}

	public function testForCreateLocaleSettings() {
		$settings = array(
			'basedOn' => 'no',
			'nested' => false
		);
		$result = $this->Translation->createLocale('dk', $settings);
		$expected = $this->Translation->forLocale('no', $settings);
		$this->assertSame($expected, $result);
	}
}
