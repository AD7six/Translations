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

		$return = Translation::export($file, $this->_settings);

		if ($return['success']) {
			$this->out(sprintf('Wrote %d translations', count($return['translations'])));
		} else {
			$this->out(sprintf('Error creating %s', $file));
		}
		$this->out('Done');
	}
}
