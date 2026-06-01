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

namespace Joomla\Module\DharmaUniversalFilter\Site\Dispatcher;

\defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\Component\RadicalMart\Administrator\Helper\PriceHelper;
use Joomla\Module\DharmaUniversalFilter\Site\Helper\DharmaUniversalFilterHelper;

class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
	use HelperFactoryAwareTrait;

	/**
	 * Returns the layout data.
	 *
	 * @throws \Exception
	 *
	 * @return  array Module layout data.
	 *
	 * @since  1.1.0
	 */
	protected function getLayoutData(): array
	{
		$data = parent::getLayoutData();

		/** @var DharmaUniversalFilterHelper $helper */
		$helper = $this->getHelperFactory()->getHelper('DharmaUniversalFilterHelper');

		$data['category_id'] = $helper->getCategoryId($data['params']);
		$data['category']    = $helper->getCategory($data['category_id']);
		$data['currency']    = PriceHelper::getCurrentCurrency();
		$data['action']      = $helper->getAction($data['params']);
		$data['form']        = $helper->getForm($data['category_id'], $data['params']);

		$app = Factory::getApplication();

		// Load language
		$app->getLanguage()->load('com_radicalmart');
		if (isset(Factory::$language))
		{
			Factory::getLanguage()->load('com_radicalmart');
		}

		// Load assets
		$assets = $app->getDocument()->getWebAssetManager();
		$assets->getRegistry()
			->addExtensionRegistryFile('mod_dharma_universal_filter')
			->addExtensionRegistryFile('com_radicalmart');

		if ($assets->assetExists('style', 'mod_dharma_universal_filter.filter'))
		{
			$assets->useStyle('mod_dharma_universal_filter.filter');
		}
		else
		{
			$assets->registerAndUseStyle(
				'mod_dharma_universal_filter.filter',
				'media/mod_dharma_universal_filter/css/filter.css',
				['version' => 'auto']
			);
		}

		if ((int) $data['params']->get('ajax', 0) === 1)
		{
			if ($assets->assetExists('script', 'mod_dharma_universal_filter.ajax'))
			{
				$assets->useScript('mod_dharma_universal_filter.ajax');
			}
			else
			{
				$assets->registerAndUseScript(
					'mod_dharma_universal_filter.ajax',
					'media/mod_dharma_universal_filter/js/ajax.min.js',
					['version' => 'auto'],
					['defer' => true, 'type' => 'module'],
					['core']
				);
			}
		}

		if (!$data['form'])
		{
			return $data;
		}

		// Set on change attribute
		if ((int) $data['params']->get('ajax', 0) === 1 && (int) $data['params']->get('ajax_auto_submit', 0))
		{
			foreach ($data['form']->getFieldsets() as $key => $fieldset)
			{
				foreach ($data['form']->getFieldset($key) as $field)
				{
					$data['form']->setFieldAttribute($field->fieldname, 'onchange',
						'window.DharmaUniversalFilter?.ajaxSubmit(event);', $field->group);
				}
			}
		}

		return $data;
	}
}
