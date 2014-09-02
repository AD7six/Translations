CREATE TABLE IF NOT EXISTS `translations` (
  `id` char(36) NOT NULL,
  `locale` char(5) NOT NULL DEFAULT 'en' COMMENT 'ISO 3166-1 alpha-2 country code + optional (_ + Region subtag). e.g. en_US',
  `domain` varchar(20) DEFAULT 'default',
  `category` char(11) DEFAULT 'LC_MESSAGES',
  `key` text,
  `value` text,
  `single_key` text,
  `plural_case` tinyint(2) DEFAULT NULL COMMENT 'Only relevant for plural translations. 0-6',
  `comments` varchar(255) default NULL COMMENT 'description for the translation in the default locale, translator\'s own notes for other locales',
  `references` text COMMENT 'Where is this translation used',
  `history` text COMMENT 'timestamp:value - Stack of previous versions of this translation',
  PRIMARY KEY (`id`),
  UNIQUE KEY `locale` (`locale`,`domain`,`category`,`key`(250), `plural_case`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
