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

use Joomla\CMS\Extension\Service\Provider\HelperFactory;
use Joomla\CMS\Extension\Service\Provider\Module;
use Joomla\CMS\Extension\Service\Provider\ModuleDispatcherFactory;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface {

	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @since   1.1.0
	 */
	public function register(Container $container)
	{
		// Register services
		$container->registerServiceProvider(new ModuleDispatcherFactory('\\Joomla\\Module\\DharmaUniversalFilter'));
		$container->registerServiceProvider(new HelperFactory('\\Joomla\\Module\\DharmaUniversalFilter\\Site\\Helper'));
		$container->registerServiceProvider(new Module());
	}
};