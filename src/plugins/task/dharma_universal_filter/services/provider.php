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

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Plugin\Task\DharmaUniversalFilter\Extension\DharmaUniversalFilter;

require_once \dirname(__DIR__) . '/src/Extension/DharmaUniversalFilter.php';

return new class implements ServiceProviderInterface {
	/**
	 * Registers services.
	 *
	 * @param   Container  $container  DI container.
	 *
	 * @return  void
	 *
	 * @since  0.1.0
	 */
	public function register(Container $container): void
	{
		$container->set(
			PluginInterface::class,
			$container->lazy(DharmaUniversalFilter::class, function (Container $container) {
				$plugin = new DharmaUniversalFilter(
					(array) PluginHelper::getPlugin('task', 'dharma_universal_filter')
				);
				$plugin->setApplication(Factory::getApplication());
				$plugin->setDatabase($container->get(DatabaseInterface::class));

				return $plugin;
			})
		);
	}
};
