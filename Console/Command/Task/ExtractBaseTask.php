<?php
App::uses('ExtractTask', 'Console/Command/Task');

/**
 * ExtractBaseTask
 */
class ExtractBaseTask extends ExtractTask {

/**
 * execute
 *
 * Overriden so all the interactive options take defaults
 *
 * @return void
 */
	public function execute() {
		$this->_getPaths();
		$path = $this->_paths[0];

		$this->params['extract-core'] = 'no';

		if (!isset($this->params['ignore-model-validation'] )) {
			$this->params['ignore-model-validation'] = true;
		}

		$pluginPaths = App::path('plugins');
		if ($this->_isExtractingApp()) {
			$this->_exclude = array_merge($this->_exclude, $pluginPaths);
		}

		if (!isset($this->params['output'] )) {
			$this->params['output'] = $path . 'Locale' . DS;
		}

		$this->params['merge'] = 'no';

		$this->params['overwrite'] = true;

		// set plugin param if a plugin path is given and the default domain is set
		if (preg_match('/Plugin\/(?P<plugin>.+)\//', $path, $matches) && isset($this->_defaultDomain)) {
			$this->params['plugin'] = $matches['plugin'];
		} else {
			$this->params['paths'] = $path;
		}

		parent::execute();
	}

/**
 * Sort translations in a consistent order
 *
 * @return void
 */
	protected function _buildFiles() {
		foreach ($this->_translations as &$translations) {
			ksort($translations);
		}

		parent::_buildFiles();
	}

/**
 * Method to interact with the User and get path selections.
 *
 * @return void
 */
	protected function _getPaths() {
		if ($this->args) {
			$this->_paths = $this->args;
		} elseif (isset($this->params['plugin'])) {
			$this->_paths[] = CakePlugin::path($this->params['plugin']);
		} else {
			$this->_paths[] = APP;
		}
	}

/**
 * Prepare a file to be stored
 *
 * Translations plugin allows you to define a default domain that is not "default".
 * If the extract task finds a default-domain translation - redefine it as "whatever".
 * This is important because the filename of a pot file is used as the domain on import.
 *
 * @param string $domain
 * @param string $header
 * @param string $sentence
 * @return void
 */
	protected function _store($domain, $header, $sentence) {
		if ($domain === 'default') {
			$domain = $this->_defaultDomain;
		}

		parent::_store($domain, $header, $sentence);
	}

/**
 * Write the files that need to be stored
 *
 * Overriden to:
 * * be noneinteractive
 * * create Locale dirs if they don't exist
 * * advise the uer what files are being created
 *
 * @return void
 */
	protected function _writeFiles() {
		$this->hr();

		foreach ($this->_storage as $domain => $sentences) {
			$output = $this->_writeHeader();
			foreach ($sentences as $sentence => $header) {
				$output .= $header . $sentence;
			}

			new Folder($this->_output, true);

			$filename = $domain . '.pot';
			$File = new File($this->_output . $filename);

			$this->out('Writing ' . $File->path);
			$File->write($output);
			$File->close();
		}
	}
}
