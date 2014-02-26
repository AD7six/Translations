#Translations

A database driven solution for translations

See the docs folder for more detailed information.

##Features

 * Can transparently replace cake's own translation functions
 * Human-readable replacement markers
 * Locale inheritance supported
 * Plural rules supported
 * Translate behavior for dynamic data
 * Export to multiple file formats
 * Import from multiple file formats

##Installation

Install like any other CakePHP plugin, e.g.:

    git submodule add git@github.com:AD7six/cakephp-translations.git app/Plugin/Translations

OR

    cd app/Plugin
    git clone git://github.com/AD7six/cakephp-translations.git Translations

Load the db schema:

	mysql mydb < app/Plugin/Translations/Config/schema.sql

Then add the following to `app/Config/bootstrap.php` file

	CakePlugin::load('Translations', array('bootstrap' => true));

To have this plugin take over all translation function from cake - you MUST use CakePHP 2.3+
and you MUST include `Config/override_i18n.php` BEFORE loading CakePHP. To do this add the
following code to the beginning of each of these files:

    include_once dirname(__DIR__) . '/Plugin/Translations/Config/override_i18n.php';

 * 	In app/webroot/index.php
 * 	In app/webroot/test.php
 * 	In app/Console/cake.php

##TODO

 * For Cake 3.0 - make this compatible with the proposed translations engine API (not yet defined)
