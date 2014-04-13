<?php
App::uses('AppShell', 'Console/Command');
App::uses('Translation', 'Translations.Model');

/**
 * ExportShell
 */
class ExportShell extends AppShell {

/**
 * _settings
 *
 * @var array
 */
	protected $_settings = array(
		'compact' => false
	);

/**
 * Gets the option parser instance and configures it.
 * By overriding this method you can configure the ConsoleOptionParser before returning it.
 *
 * @return ConsoleOptionParser
 * @link http://book.cakephp.org/2.0/en/console-and-shells.html#Shell::getOptionParser
 */
	public function getOptionParser() {
		$this->_settings = Translation::config();
		$parser = parent::getOptionParser();
		return $parser
			->addArgument('file', array(
				'help' => 'relative or abs path to translations file',
				'required' => true
			))
			->addOption('locale', array(
				'help' => sprintf('the locale to export, defaults to "%s"', Configure::read('Config.language'))
			))
			->addOption('domain', array(
				'help' => 'the domain to export, defaults to "default"'
			))
			->addOption('category', array(
				'help' => 'the category to export, defaults to "LC_MESSAGES"'
			))
			->addOption('compact', array(
				'help' => 'Use compact format (do not include meta information)',
				'boolean' => true,
				'default' => false
			));
	}

/**
 * Export translations to the specified path
 * Currently supports:
 * 	json
 * 	po
 *
 * @throws \Exception if the file specified is not writable
 */
	public function main() {
		$file = $this->args[0];
		foreach ($this->params as $key => $val) {
			$this->_settings[$key] = $val;
		}

		$settings = $this->_settings;
		$files = $this->permutateFiles($this->args);
		foreach($files as $i => $file) {
			if (!is_numeric($i)) {
				$this->_settings = $file + $settings;
				$file = $i;
			}
			$this->out(sprintf(
				'<info>Processing %s (%s, %s, %s)</info>',
				$file,
				$this->_settings['locale'],
				$this->_settings['domain'],
				$this->_settings['category']
			));
			$this->processFile($file);
		}
	}

	public function processFile($file) {
		$return = Translation::export($file, $this->_settings);

		if ($return['success']) {
			$this->out(sprintf('Wrote %d translations', count($return['translations'])));
		} else {
			$this->out(sprintf('Error creating %s', $file));
		}
		$this->out('Done');
	}

	public function permutateFiles($files) {
		if (count($files) === 1 && strpos($files[0], '.') === false) {

			$dir = rtrim($files[0], DS);
			if ($dir === 'Locale') {
				$extension = 'po';
			} else {
				$extension = 'json';
			}

			$locales = array_keys(Translation::locales());
			$categories = array('LC_MESSAGES');
			$domains = Translation::domains();
			if ($extension === 'json') {
				$domains = array('javascript');
			}

			$return = array();
			foreach($domains as $domain) {

				if ($extension === 'po') {
					$return["$dir/$domain.pot"] = compact('domain');
				}

				foreach($categories as $category) {
					foreach($locales as $locale) {

						if ($extension === 'po') {

							$fileLocale = $locale;
							if(strlen($fileLocale) === 2) {
								$map = (new L10n())->map();
								$fileLocale = array_search($fileLocale, $map);
							}
							$path = "$dir/$fileLocale/$category/$domain.$extension";

						} else {

							$path = "$dir/$locale.$extension";

						}

						$return[$path] = compact(
							'locale',
							'category',
							'domain'
						);;
					}
				}
			}

			ksort($return);
			return $return;
		}

		$return = array();

		foreach($files as $file) {
			if (preg_match('@Locale/(\w+)/(\w+)/(\w+)\.po$@', $file, $matches)) {
				list(, $locale, $category, $domain) = $matches;

				if(strlen($locale) === 3) {
					$map = (new L10n())->map();
					$locale = $map[$locale];
				}

				$return[$file] = compact('locale', 'category', 'domain');
			} else {
				$return[] = $file;
			}
		}

		return $return;
	}
}
