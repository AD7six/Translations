<?php
$this->element('top_actions');
$locale = !empty($locale) ? $locale : Configure::read('Config.defaultLanguage');
$row_actions = array(
	'10_view' => false,
	'20_edit' => false,
	'20_edit_locale' => array(
		'url' 	=> array('action' => 'edit_locale', $locale, '{{Translation.domain}}', '{{Translation.category}}', '{{Translation.ns}}'),
		'label'	=> 'Edit',
		'title'	=> '<i class="icon-app-edit"></i>'
	),
);
$this->set('row_actions', $row_actions);

echo $this->element('Shared.Crud/index', array(
	'model' => 'Translation',
	'title' => sprintf('%s texts for %s domain', $locales[$locale], $domain),
	'columns' => array(
		'key',
		'value' => array(
			'name' => 'value',
			'callback' => function($view, $item, $model, $baseUrl) use ($locale, $domain, $category) {
				return $view->Text->truncate(Translation::translate($item['Translation']['key'], compact('locale', 'domain', 'category')), 100);
			}
		)
	),
));
