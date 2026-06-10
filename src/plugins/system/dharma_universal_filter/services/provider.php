<?php
/*
 * @package     Dharma Universal Filter System Plugin
 * @subpackage  plg_system_dharma_universal_filter
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
use Joomla\Plugin\System\DharmaUniversalFilter\Extension\DharmaUniversalFilter;

require_once \dirname(__DIR__) . '/src/Extension/DharmaUniversalFilter.php';

// Safety net in case the library namespace map has not been refreshed yet.
if (!class_exists(\Dharma\UniversalFilter\Indexer::class) && is_file(JPATH_LIBRARIES . '/dharma_universal_filter/src/Indexer.php'))
{
	require_once JPATH_LIBRARIES . '/dharma_universal_filter/src/Indexer.php';
}

return new class implements ServiceProviderInterface {
	public function register(Container $container): void
	{
		$container->set(
			PluginInterface::class,
			$container->lazy(DharmaUniversalFilter::class, function (Container $container) {
				$plugin = new DharmaUniversalFilter(
					(array) PluginHelper::getPlugin('system', 'dharma_universal_filter')
				);
				$plugin->setApplication(Factory::getApplication());
				$plugin->setDatabase($container->get(DatabaseInterface::class));

				return $plugin;
			})
		);
	}
};
