<?php
/*
 * @package     Dharma Universal Filter Task Plugin
 * @subpackage  plg_task_dharma_universal_filter
 * @version     0.1.0
 * @author      Dharma Design
 * @copyright   Copyright (c) 2026 Dharma Design. All rights reserved.
 * @license     GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 * @link
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface {
	public function register(Container $container): void
	{
		$container->set(InstallerScriptInterface::class, new class($container->get(AdministratorApplication::class)) implements InstallerScriptInterface {
			public function __construct(protected AdministratorApplication $app)
			{
			}

			public function install(InstallerAdapter $adapter): bool
			{
				$this->ensureTables();
				$this->enablePlugin('task', 'dharma_universal_filter');

				return true;
			}

			public function update(InstallerAdapter $adapter): bool
			{
				$this->ensureTables();
				$this->enablePlugin('task', 'dharma_universal_filter');

				return true;
			}

			public function uninstall(InstallerAdapter $adapter): bool
			{
				return true;
			}

			public function preflight(string $type, InstallerAdapter $adapter): bool
			{
				return true;
			}

			public function postflight(string $type, InstallerAdapter $adapter): bool
			{
				$this->ensureTables();
				$this->enablePlugin('task', 'dharma_universal_filter');

				return true;
			}

			private function enablePlugin(string $folder, string $element): void
			{
				$db = Factory::getContainer()->get(DatabaseInterface::class);
				$query = $db->getQuery(true)
					->update($db->quoteName('#__extensions'))
					->set($db->quoteName('enabled') . ' = 1')
					->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
					->where($db->quoteName('folder') . ' = ' . $db->quote($folder))
					->where($db->quoteName('element') . ' = ' . $db->quote($element));

				$db->setQuery($query)->execute();
			}

			private function ensureTables(): void
			{
				$db = Factory::getContainer()->get(DatabaseInterface::class);
				$db->setQuery(
					'CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__dharma_universal_filter_index') . ' (
						' . $db->quoteName('id') . ' BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
						' . $db->quoteName('category_id') . ' INT UNSIGNED NOT NULL DEFAULT 0,
						' . $db->quoteName('item_id') . ' INT UNSIGNED NOT NULL DEFAULT 0,
						' . $db->quoteName('field_name') . ' VARCHAR(191) NOT NULL DEFAULT \'\',
						' . $db->quoteName('field_value') . ' VARCHAR(255) NOT NULL DEFAULT \'\',
						' . $db->quoteName('field_value_hash') . ' CHAR(40) NOT NULL DEFAULT \'\',
						' . $db->quoteName('language') . ' VARCHAR(7) NOT NULL DEFAULT \'*\',
						' . $db->quoteName('in_stock') . ' TINYINT NOT NULL DEFAULT 0,
						PRIMARY KEY (' . $db->quoteName('id') . '),
						KEY ' . $db->quoteName('idx_duf_category_field_value') . ' (' . $db->quoteName('category_id') . ', ' . $db->quoteName('field_name') . ', ' . $db->quoteName('field_value_hash') . '),
						KEY ' . $db->quoteName('idx_duf_category_item') . ' (' . $db->quoteName('category_id') . ', ' . $db->quoteName('item_id') . '),
						KEY ' . $db->quoteName('idx_duf_field_item') . ' (' . $db->quoteName('field_name') . ', ' . $db->quoteName('item_id') . ')
					) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci'
				)->execute();

				$db->setQuery(
					'CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__dharma_universal_filter_price_index') . ' (
						' . $db->quoteName('id') . ' BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
						' . $db->quoteName('category_id') . ' INT UNSIGNED NOT NULL DEFAULT 0,
						' . $db->quoteName('item_id') . ' INT UNSIGNED NOT NULL DEFAULT 0,
						' . $db->quoteName('currency') . ' VARCHAR(32) NOT NULL DEFAULT \'\',
						' . $db->quoteName('price_min') . ' DECIMAL(20,6) NOT NULL DEFAULT 0,
						' . $db->quoteName('price_max') . ' DECIMAL(20,6) NOT NULL DEFAULT 0,
						' . $db->quoteName('language') . ' VARCHAR(7) NOT NULL DEFAULT \'*\',
						' . $db->quoteName('in_stock') . ' TINYINT NOT NULL DEFAULT 0,
						PRIMARY KEY (' . $db->quoteName('id') . '),
						KEY ' . $db->quoteName('idx_duf_price_category_currency') . ' (' . $db->quoteName('category_id') . ', ' . $db->quoteName('currency') . '),
						KEY ' . $db->quoteName('idx_duf_price_category_item') . ' (' . $db->quoteName('category_id') . ', ' . $db->quoteName('item_id') . ')
					) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci'
				)->execute();
			}
		});
	}
};
