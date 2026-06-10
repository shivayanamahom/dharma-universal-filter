<?php
/*
 * @package     Dharma Universal Filter Module
 * @subpackage  mod_dharma_universal_filter
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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Version;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
return new class () implements ServiceProviderInterface {
	public function register(Container $container)
	{
		$container->set(InstallerScriptInterface::class, new class ($container->get(AdministratorApplication::class)) implements InstallerScriptInterface {
			/**
			 * The application object
			 *
			 * @var  AdministratorApplication
			 *
			 * @since  1.1.0
			 */
			protected AdministratorApplication $app;

			/**
			 * The Database object.
			 *
			 * @var   DatabaseDriver
			 *
			 * @since  1.1.0
			 */
			protected DatabaseDriver $db;

			/**
			 * Minimum Joomla version required to install the extension.
			 *
			 * @var  string
			 *
			 * @since  1.1.0
			 */
			protected string $minimumJoomla = '4.2';

			/**
			 * Minimum PHP version required to install the extension.
			 *
			 * @var  string
			 *
			 * @since  1.1.0
			 */
			protected string $minimumPhp = '7.4';

			/**
			 * Update methods.
			 *
			 * @var  array
			 *
			 * @since  1.1.0
			 */
			protected array $updateMethods = [];

			/**
			 * Constructor.
			 *
			 * @param   AdministratorApplication  $app  The applications object.
			 *
			 * @since 1.1.0
			 */
			public function __construct(AdministratorApplication $app)
			{
				$this->app = $app;
				$this->db  = Factory::getContainer()->get('DatabaseDriver');
			}

			/**
			 * Function called after the extension is installed.
			 *
			 * @param   InstallerAdapter  $adapter  The adapter calling this method
			 *
			 * @return  boolean  True on success
			 *
			 * @since   1.1.0
			 */
			public function install(InstallerAdapter $adapter): bool
			{
				$this->migrateLayoutParams();

				return true;
			}

			/**
			 * Function called after the extension is updated.
			 *
			 * @param   InstallerAdapter  $adapter  The adapter calling this method
			 *
			 * @return  boolean  True on success
			 *
			 * @since   1.1.0
			 */
			public function update(InstallerAdapter $adapter): bool
			{
				$this->migrateLayoutParams();

				// Refresh media version
				(new Version())->refreshMediaVersion();

				return true;
			}

			/**
			 * Function called after the extension is uninstalled.
			 *
			 * @param   InstallerAdapter  $adapter  The adapter calling this method
			 *
			 * @return  boolean  True on success
			 *
			 * @since   1.1.0
			 */
			public function uninstall(InstallerAdapter $adapter): bool
			{
				return true;
			}

			/**
			 * Function called before extension installation/update/removal procedure commences.
			 *
			 * @param   string            $type     The type of change (install or discover_install, update, uninstall)
			 * @param   InstallerAdapter  $adapter  The adapter calling this method
			 *
			 * @return  boolean  True on success
			 *
			 * @since   1.1.0
			 */
			public function preflight(string $type, InstallerAdapter $adapter): bool
			{
				// Check compatible
				if (!$this->checkCompatible())
				{
					return false;
				}

				return true;
			}

			/**
			 * Function called after extension installation/update/removal procedure commences.
			 *
			 * @param   string            $type     The type of change (install or discover_install, update, uninstall)
			 * @param   InstallerAdapter  $adapter  The adapter calling this method
			 *
			 * @return  boolean  True on success
			 *
			 * @since   1.1.0
			 */
			public function postflight(string $type, InstallerAdapter $adapter): bool
			{
				if (in_array($type, ['install', 'update'], true))
				{
					$this->migrateLayoutParams();
				}

				// Run updates script
				if ($type === 'update')
				{
					foreach ($this->updateMethods as $method)
					{
						if (method_exists($this, $method))
						{
							$this->$method($adapter);
						}
					}
				}

				return true;
			}

			/**
			 * Migrates old technical layout values to explicit layout names.
			 *
			 * @return  void
			 *
			 * @since  0.1.0
			 */
			protected function migrateLayoutParams(): void
			{
				$moduleName = 'mod_dharma_universal_filter';
				$query = $this->db->getQuery(true)
					->select($this->db->quoteName(['id', 'params']))
					->from($this->db->quoteName('#__modules'))
					->where($this->db->quoteName('module') . ' = :module')
					->bind(':module', $moduleName, ParameterType::STRING);

				$modules = $this->db->setQuery($query)->loadObjectList();
				foreach ($modules as $module)
				{
					$params = json_decode((string) $module->params, true);
					if (!is_array($params))
					{
						continue;
					}

					$layout = (string) ($params['layout'] ?? '');
					$newLayout = match ($layout) {
						'horizont', '_:horizont', '_horizont', 'cassiopeia_dominant:horizont', 'horizontal', '_horizontal', 'cassiopeia_dominant:horizontal' => '_:horizontal',
						'default', '_:default', 'cassiopeia_dominant:default', 'vertical', '_vertical', 'cassiopeia_dominant:vertical', '' => '_:vertical',
						default => $layout,
					};

					if ($newLayout === $layout)
					{
						continue;
					}

					$params['layout'] = $newLayout;
					$id = (int) $module->id;
					$paramsJson = json_encode($params, JSON_UNESCAPED_UNICODE);
					$update = $this->db->getQuery(true)
						->update($this->db->quoteName('#__modules'))
						->set($this->db->quoteName('params') . ' = :params')
						->where($this->db->quoteName('id') . ' = :id')
						->bind(':params', $paramsJson, ParameterType::STRING)
						->bind(':id', $id, ParameterType::INTEGER);

					$this->db->setQuery($update)->execute();
				}
			}

			/**
			 * Method to check compatible.
			 *
			 * @throws  \Exception
			 *
			 * @return  bool True on success, False on failure.
			 *
			 * @since  1.1.0
			 */
			protected function checkCompatible(): bool
			{
				$app = Factory::getApplication();

				// Check joomla version
				if (!(new Version())->isCompatible($this->minimumJoomla))
				{
					$app->enqueueMessage(Text::sprintf('MOD_DHARMA_UNIVERSAL_FILTER_ERROR_COMPATIBLE_JOOMLA', $this->minimumJoomla),
						'error');

					return false;
				}

				// Check PHP
				if (!(version_compare(PHP_VERSION, $this->minimumPhp) >= 0))
				{
					$app->enqueueMessage(Text::sprintf('MOD_DHARMA_UNIVERSAL_FILTER_ERROR_COMPATIBLE_PHP', $this->minimumPhp),
						'error');

					return false;
				}

				return true;
			}
		});
	}
};
