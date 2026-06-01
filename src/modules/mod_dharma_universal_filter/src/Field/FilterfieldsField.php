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

namespace Joomla\Module\DharmaUniversalFilter\Site\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

class FilterfieldsField extends ListField
{
	/**
	 * The form field type.
	 *
	 * @var  string
	 *
	 * @since  0.1.0
	 */
	protected $type = 'filterfields';

	/**
	 * Method to get field options.
	 *
	 * @return  array
	 *
	 * @since  0.1.0
	 */
	protected function getOptions(): array
	{
		$options = parent::getOptions();

		$options[] = (object) [
			'value' => 'price',
			'text'  => Text::_('MOD_DHARMA_UNIVERSAL_FILTER_FIELD_PRICE'),
		];
		$options[] = (object) [
			'value' => 'categories',
			'text'  => Text::_('MOD_DHARMA_UNIVERSAL_FILTER_FIELD_CATEGORIES'),
		];
		$options[] = (object) [
			'value' => 'manufacturers',
			'text'  => Text::_('MOD_DHARMA_UNIVERSAL_FILTER_FIELD_MANUFACTURERS'),
		];
		$options[] = (object) [
			'value' => 'badges',
			'text'  => Text::_('MOD_DHARMA_UNIVERSAL_FILTER_FIELD_BADGES'),
		];
		$options[] = (object) [
			'value' => 'in_stock',
			'text'  => Text::_('MOD_DHARMA_UNIVERSAL_FILTER_FIELD_IN_STOCK'),
		];

		try
		{
			$db = Factory::getContainer()->get(DatabaseInterface::class);
			$query = $db->getQuery(true)
				->select($db->quoteName(['alias', 'title']))
				->from($db->quoteName('#__radicalmart_fields'))
				->where($db->quoteName('area') . ' = ' . $db->quote('products'))
				->where($db->quoteName('state') . ' = 1')
				->order($db->quoteName('ordering') . ' ASC');

			foreach ($db->setQuery($query)->loadObjectList() as $field)
			{
				$options[] = (object) [
					'value' => (string) $field->alias,
					'text'  => (string) $field->title . ' [' . (string) $field->alias . ']',
				];
			}
		}
		catch (\Throwable)
		{
			return $options;
		}

		return $options;
	}
}
