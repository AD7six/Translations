<?php

App::uses('JsonParser', 'Translations.Parser');

class JsonParserTest extends CakeTestCase {

	public function testParse() {
		$path = CakePlugin::path('Translations') . 'Test/Files/simple.json';
		$result = JsonParser::parse($path);

		$expected = array(
			'count' => 1,
			'translations' => array(
				array(
					'domain' => 'default',
					'category' => 'LC_MESSAGES',
					'locale' => 'en',
					'key' => 'foo',
					'value' => 'foo value'
				)
			)
		);
		$this->assertSame($expected, $result);
	}

	public function testParseCake() {
		$path = CakePlugin::path('Translations') . 'Test/Files/cake.json';
		$result = JsonParser::parse($path);

		$next = $result['translations'][0];
		$expected = array(
			'locale' => 'en',
			'domain' => 'cake',
			'category' => 'LC_MESSAGES',
			'key' => ' of ',
			'value' => ' of '
		);
		$this->assertSame($expected, $next);

		$next = $result['translations'][1];
		$expected = array(
			'locale' => 'en',
			'domain' => 'cake',
			'category' => 'LC_MESSAGES',
			'key' => '%.2f GB',
			'value' => '%.2f GB'
		);
		$this->assertSame($expected, $next);
	}
}
