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
use Joomla\CMS\Form\Field\SubformField;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

class FilterfieldsconfigField extends SubformField
{
	/**
	 * The form field type.
	 *
	 * @var  string
	 *
	 * @since  0.1.0
	 */
	protected $type = 'filterfieldsconfig';

	/**
	 * Method to attach a Form object to the field.
	 *
	 * @param   \SimpleXMLElement  $element  The field XML element.
	 * @param   mixed              $value    The field value.
	 * @param   string|null        $group    The field group.
	 *
	 * @return  bool
	 *
	 * @since  0.1.0
	 */
	public function setup(\SimpleXMLElement $element, $value, $group = null): bool
	{
		if ($this->shouldUseDefaultRows($value))
		{
			$value = $this->getDefaultRows();
		}

		return parent::setup($element, $value, $group);
	}

	/**
	 * Checks whether defaults should be injected.
	 *
	 * @param   mixed  $value  Current field value.
	 *
	 * @return  bool
	 *
	 * @since  0.1.0
	 */
	protected function shouldUseDefaultRows(mixed $value): bool
	{
		if (!empty($value))
		{
			return false;
		}

		$moduleId = Factory::getApplication()->getInput()->getInt('id');
		if ($moduleId <= 0)
		{
			return true;
		}

		try
		{
			$db = Factory::getContainer()->get(DatabaseInterface::class);
			$query = $db->getQuery(true)
				->select($db->quoteName('params'))
				->from($db->quoteName('#__modules'))
				->where($db->quoteName('id') . ' = :id')
				->bind(':id', $moduleId);

			$params = json_decode((string) $db->setQuery($query)->loadResult(), true);

			return !is_array($params) || empty($params['fields_config_initialized']);
		}
		catch (\Throwable)
		{
			return true;
		}
	}

	/**
	 * Builds default field config rows from available filter fields.
	 *
	 * @return  array
	 *
	 * @since  0.1.0
	 */
	protected function getDefaultRows(): array
	{
		$rows = [];
		foreach ($this->getAvailableFields() as $field)
		{
			$rows[] = [
				'field'    => $field['value'],
				'show'     => '1',
				'title'    => '',
				'display'  => 'auto',
				'expanded' => '1',
			];
		}

		return $rows;
	}

	/**
	 * Gets available system and RadicalMart fields.
	 *
	 * @return  array
	 *
	 * @since  0.1.0
	 */
	protected function getAvailableFields(): array
	{
		$fields = [
			['value' => 'price', 'text' => Text::_('MOD_DHARMA_UNIVERSAL_FILTER_FIELD_PRICE')],
			['value' => 'categories', 'text' => Text::_('MOD_DHARMA_UNIVERSAL_FILTER_FIELD_CATEGORIES')],
			['value' => 'manufacturers', 'text' => Text::_('MOD_DHARMA_UNIVERSAL_FILTER_FIELD_MANUFACTURERS')],
			['value' => 'badges', 'text' => Text::_('MOD_DHARMA_UNIVERSAL_FILTER_FIELD_BADGES')],
			['value' => 'in_stock', 'text' => Text::_('MOD_DHARMA_UNIVERSAL_FILTER_FIELD_IN_STOCK')],
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
				$fields[] = [
					'value' => (string) $field->alias,
					'text'  => (string) $field->title,
				];
			}
		}
		catch (\Throwable)
		{
			return $fields;
		}

		return $fields;
	}
}
