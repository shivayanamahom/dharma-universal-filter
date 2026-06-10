<?php
/*
 * @package     Dharma Universal Filter Library
 * @subpackage  lib_dharma_universal_filter
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
				$this->runSqlFile('install.mysql.utf8.sql');

				return true;
			}

			public function update(InstallerAdapter $adapter): bool
			{
				$this->runSqlFile('install.mysql.utf8.sql');

				return true;
			}

			public function uninstall(InstallerAdapter $adapter): bool
			{
				// Keep indexed data on uninstall by default; drop only when explicitly desired.
				return true;
			}

			public function preflight(string $type, InstallerAdapter $adapter): bool
			{
				return true;
			}

			public function postflight(string $type, InstallerAdapter $adapter): bool
			{
				if (in_array($type, ['install', 'update'], true))
				{
					$this->runSqlFile('install.mysql.utf8.sql');
				}

				return true;
			}

			/**
			 * Executes the statements of a packaged SQL file.
			 *
			 * @param   string  $file  File name inside the library sql folder.
			 *
			 * @return  void
			 */
			private function runSqlFile(string $file): void
			{
				$paths = [
					JPATH_LIBRARIES . '/dharma_universal_filter/sql/' . $file,
					__DIR__ . '/sql/' . $file,
				];

				$sql = '';
				foreach ($paths as $path)
				{
					if (is_file($path))
					{
						$sql = (string) file_get_contents($path);
						break;
					}
				}

				if ($sql === '')
				{
					return;
				}

				$db = Factory::getContainer()->get(DatabaseInterface::class);
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
