<?php
/*
 * @package     Dharma Universal Filter Package
 * @subpackage  pkg_dharma_universal_filter
 * @version     0.2.0
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
				$this->enablePlugin('system', 'dharma_universal_filter');

				return true;
			}

			public function update(InstallerAdapter $adapter): bool
			{
				$this->ensureTables();
				$this->enablePlugin('task', 'dharma_universal_filter');
				$this->enablePlugin('system', 'dharma_universal_filter');

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
				$this->enablePlugin('system', 'dharma_universal_filter');

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

			/**
			 * Creates the index tables from the bundled library schema. The
			 * lib_dharma_universal_filter library (installed first in this package)
			 * owns sql/install.mysql.utf8.sql as the single source of truth.
			 *
			 * @return  void
			 */
			private function ensureTables(): void
			{
				$file = JPATH_LIBRARIES . '/dharma_universal_filter/sql/install.mysql.utf8.sql';
				if (!is_file($file))
				{
					return;
				}

				$db  = Factory::getContainer()->get(DatabaseInterface::class);
				$sql = (string) file_get_contents($file);
				foreach ($db->splitSql($sql) as $statement)
				{
					$statement = trim($statement);
					if ($statement !== '')
					{
						$db->setQuery($statement)->execute();
					}
				}
			}
		});
	}
};
