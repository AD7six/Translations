<?php
App::uses('ExtractBaseTask', 'Translations.Console/Command/Task');

/**
 * ExtractJsTask
 */
class ExtractJsTask extends ExtractBaseTask {

/**
 * execute
 *
 * Setup options to only look for un-minified js files
 *
 * @return void
 */
	public function execute() {
		$this->_getPaths();

		$this->params['output'] = dirname($this->_paths[0]) . DS . 'Locale';

		$this->_exclude[] = DS . '_';
		$this->_exclude[] = '.min.js';

		parent::execute();
	}

/**
 * javascript method
 *
 * Find js files and look for __() calls
 *
 * @return void
 * @access public
 */
	protected function _extractTokens() {
		foreach ($this->_files as $file) {
			$this->_file = $file;
			$string = file($file);
			$this->out(sprintf('Processing %s...', $file), 1, 1);
			if (!$string || strpos(implode($string), '__(') === false) {
				continue;
			}
			$match = false;
			foreach ($string as $i => $line) {
				preg_match_all('@__\(([\'"])(.*)\1\)@U', $line, $matches);
				if ($matches[0]) {
					$match = true;
					foreach ($matches[2] as $text) {
						$details = array(
							'file' => $file,
							'line' => $i + 1
						);
						$this->_addTranslation('LC_MESSAGES', 'javascript', $text, $details);
					}
				}
			}
		}
	}

/**
 * Look in the webroot of the app or plugin
 *
 * If it's the app this will capture any symlinked assets - which is good/fine since that means
 * we can put standard translations in them and they'll be in our apps as we use them
 *
 * @return void
 */
	protected function _getPaths() {
		if ($this->args) {
			$this->_paths = $this->args;
			foreach ($this->_paths as &$path) {
				if (is_dir($path . DS . 'webroot')) {
					$path = rtrim($path, DS) . DS . 'webroot';
				}
			}
		} elseif (isset($this->params['plugin'])) {
			$this->_paths[] = CakePlugin::path($this->params['plugin']) . 'webroot';
		} else {
			$this->_paths[] = APP . 'webroot';
		}
	}

/**
 * Only look for js files
 *
 * @return void
 */
	protected function _searchFiles() {
		$pattern = false;
		if (!empty($this->_exclude)) {
			$exclude = array();
			foreach ($this->_exclude as $e) {
				if (DS !== '\\' && $e[0] !== DS) {
					$e = DS . $e;
				}
				$exclude[] = preg_quote($e, '/');
			}
			$pattern = '/' . implode('|', $exclude) . '/';
		}

		foreach ($this->_paths as $path) {
			$Folder = new Folder($path);
			$files = $Folder->findRecursive('.*\.(js)', true);
			if (!empty($pattern)) {
				foreach ($files as $i => $file) {
					if (preg_match($pattern, $file)) {
						unset($files[$i]);
					}
				}
				$files = array_values($files);
			}
			$this->_files = array_merge($this->_files, $files);
		}
	}
}
