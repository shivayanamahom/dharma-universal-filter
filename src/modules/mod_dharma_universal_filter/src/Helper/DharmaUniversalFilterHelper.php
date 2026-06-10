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

namespace Joomla\Module\DharmaUniversalFilter\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Router\Route;
use Joomla\Component\RadicalMart\Administrator\Helper\ParamsHelper;
use Joomla\Component\RadicalMart\Administrator\Helper\PriceHelper;
use Joomla\Component\RadicalMart\Site\Helper\RouteHelper;
use Joomla\Component\RadicalMart\Site\Model\ProductsModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

class DharmaUniversalFilterHelper
{
	/**
	 * Products filter form object.
	 *
	 * @var  array|null
	 *
	 * @since  1.1.0
	 */
	protected ?array $_form = null;

	/**
	 * Products model object.
	 *
	 * @var  ProductsModel|null
	 *
	 * @since  1.1.0
	 */
	protected ?ProductsModel $_model = null;

	/**
	 * Category id.
	 *
	 * @var  array|null
	 *
	 * @since  1.1.0
	 */
	protected ?array $_category = null;

	/**
	 * Available product filter data by category id.
	 *
	 * @var  array|null
	 *
	 * @since  0.1.0
	 */
	protected ?array $_availableFilterData = null;

	/**
	 * Current request filters cache.
	 *
	 * @var  array|null
	 *
	 * @since  0.1.0
	 */
	protected ?array $_currentFilters = null;

	/**
	 * Indexed product ids by category.
	 *
	 * @var  array|null
	 *
	 * @since  0.1.0
	 */
	protected ?array $_indexedCategoryItemIds = null;

	/**
	 * Indexed product ids by field values.
	 *
	 * @var  array|null
	 *
	 * @since  0.1.0
	 */
	protected ?array $_indexedFieldItemIds = null;

	/**
	 * Indexed product ids by price range.
	 *
	 * @var  array|null
	 *
	 * @since  0.1.0
	 */
	protected ?array $_indexedPriceItemIds = null;

	/**
	 * Whether category has task index rows.
	 *
	 * @var  array|null
	 *
	 * @since  0.1.0
	 */
	protected ?array $_hasFilterIndexRows = null;

	/**
	 * Method to get correct category id.
	 *
	 * @param   Registry  $params  Module params.
	 *
	 * @throws  \Exception
	 *
	 * @return  int  Correct category id.
	 *
	 * @since  1.2.0
	 */
	public function getCategoryId(Registry $params): int
	{
		$app = Factory::getApplication();
		$pk  = (int) $params->get('category');
		if ($pk > 0)
		{
			$category = $pk;
		}
		elseif ($app->input->get('option') === 'com_radicalmart'
			&& $app->input->get('view') === 'category'
			&& $app->input->getInt('id'))
		{
			$category = $app->input->getInt('id');
		}
		else
		{
			$category = 1;
		}

		return $category;
	}

	/**
	 * Method to get form action url.
	 *
	 * @param   Registry  $params  Module params.
	 *
	 * @throws  \Exception
	 *
	 * @return  string  The action url.
	 *
	 * @since  1.1.0
	 */
	public function getAction(Registry $params): string
	{
		if ((int) $params->get('menu_item') > 0)
		{
			$link = 'index.php?Itemid=' . (int) $params->get('menu_item');
		}
		else
		{
			$link = RouteHelper::getCategoryViewRoute($this->getCategoryId($params));
		}

		return Route::link('site', $link);
	}

	/**
	 * Method to get filter form.
	 *
	 * @param   int|null  $pk  Category id.
	 *
	 * @throws  \Exception
	 *
	 * @return  Form|false  The Form object or false on error.
	 *
	 * @since  1.1.0
	 */
	public function getForm(?int $pk = null, ?Registry $params = null)
	{
		if (empty($pk))
		{
			$pk = 1;
		}
		$cascadeKey = ($params && (int) $params->get('cascade', 1) === 1) ? md5(json_encode($this->getCurrentFilters())) : '';
		$formKey = $pk . ':' . ($params ? (string) $params->get('empty_options_mode', 'hide') : 'hide') . ':' . $cascadeKey;
		if ($this->_form === null)
		{
			$this->_form = [];
		}
		if (!isset($this->_form[$formKey]))
		{
			$model = $this->getModel();
			$model->setState('category.id', $pk);

			Form::addFormPath(JPATH_ROOT . '/components/com_radicalmart/forms');

			$form             = $model->getFilterForm();
			$this->prepareAvailableFilterForm($form, $pk, $params);
			$this->_form[$formKey] = $this->hasFormFields($form) ? $form : false;

			return $this->_form[$formKey];
		}

		return $this->_form[$formKey];
	}

	/**
	 * Checks whether the filter form still contains visible fields.
	 *
	 * @param   Form  $form  Filter form object.
	 *
	 * @return  bool
	 *
	 * @since  0.1.0
	 */
	protected function hasFormFields(Form $form): bool
	{
		foreach ($form->getFieldsets() as $key => $fieldset)
		{
			if (count($form->getFieldset($key)) > 0)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Removes filter fields and options that have no matching products in the active category.
	 *
	 * @param   Form  $form  Filter form object.
	 * @param   int   $pk    Category id.
	 *
	 * @throws  \Exception
	 *
	 * @return  void
	 *
	 * @since  0.1.0
	 */
	protected function prepareAvailableFilterForm(Form $form, int $pk, ?Registry $params = null): void
	{
		$currentFilters = ($params && (int) $params->get('cascade', 1) === 1) ? $this->getCurrentFilters() : [];
		$available = $this->getAvailableFilterData($pk, $currentFilters);
		$emptyMode = $params ? (string) $params->get('empty_options_mode', 'hide') : 'hide';
		$showOptionCounts = $params ? (int) $params->get('show_option_counts', 0) === 1 : false;
		$hasCurrentFilters = count($currentFilters) > 0;
		$optionsEmptyMode = ($pk <= 1 && !$hasCurrentFilters) ? 'hide' : $emptyMode;

		if ($available['count'] === 0)
		{
			foreach ($form->getFieldsets() as $key => $fieldset)
			{
				foreach ($form->getFieldset($key) as $field)
				{
					$form->removeField($field->fieldname, $field->group);
				}
			}

			return;
		}

		$priceAvailable = $hasCurrentFilters
			? $this->getAvailableFilterData($pk, $this->getFiltersWithoutField($currentFilters, 'price'))
			: $available;

		if (!$priceAvailable['has_price'] && $emptyMode === 'hide')
		{
			$form->removeField('price', 'filter');
		}
		elseif (!$priceAvailable['has_price'] && $emptyMode === 'disable')
		{
			$form->setFieldAttribute('price', 'disabled', 'true', 'filter');
		}
		elseif ($priceAvailable['has_price'])
		{
			$form->setFieldAttribute('price', 'hints', (new Registry([
				'from' => $priceAvailable['price_min'],
				'to'   => $priceAvailable['price_max'],
			]))->toString(), 'filter');
		}

		if ($showOptionCounts)
		{
			foreach (['categories', 'manufacturers', 'badges'] as $fieldName)
			{
				$fieldAvailable = $hasCurrentFilters
					? $this->getAvailableFilterData($pk, $this->getFiltersWithoutField($currentFilters, $fieldName), false)
					: $available;
				$this->applyOptionCounts($form, $fieldName, 'filter', $fieldAvailable['categories']);
			}
		}

		if ($emptyMode !== 'show')
		{
			foreach (['categories', 'manufacturers', 'badges'] as $fieldName)
			{
				$fieldAvailable = $hasCurrentFilters
					? $this->getAvailableFilterData($pk, $this->getFiltersWithoutField($currentFilters, $fieldName), false)
					: $available;
				$this->filterFormOptions($form, $fieldName, 'filter', $fieldAvailable['categories'], $optionsEmptyMode);
			}
		}

		foreach ($form->getFieldsets() as $key => $fieldset)
		{
			foreach ($form->getFieldset($key) as $field)
			{
				if ($field->group !== 'fields' && !str_ends_with($field->group, '.fields'))
				{
					continue;
				}

				$fieldAvailable = $hasCurrentFilters
					? $this->getAvailableFilterData($pk, $this->getFiltersWithoutField($currentFilters, $field->fieldname, true), false)
					: $available;
				$values = $fieldAvailable['fields'][$field->fieldname] ?? [];
				if ($showOptionCounts)
				{
					$this->applyOptionCounts($form, $field->fieldname, $field->group, $values);
				}

				if (count($values) === 0)
				{
					if ($optionsEmptyMode === 'hide')
					{
						$form->removeField($field->fieldname, $field->group);
					}
					elseif ($optionsEmptyMode === 'disable')
					{
						$form->setFieldAttribute($field->fieldname, 'disabled', 'true', $field->group);

						// Disable each option too: the custom checkbox/list layouts honour
						// per-option state, not the field-level "disabled" attribute. Without
						// this, a field whose values are all incompatible with the active
						// filters would still render as fully active.
						$this->filterFormOptions($form, $field->fieldname, $field->group, [], 'disable');
					}

					continue;
				}

				if ($emptyMode !== 'show')
				{
					$this->filterFormOptions($form, $field->fieldname, $field->group, $values, $optionsEmptyMode);
				}
			}
		}

		if ($params)
		{
			$this->applyFieldsConfig($form, $params);
			$this->applyCheckboxOptionsBehavior($form, $params);
		}
	}

	/**
	 * Removes one field from active filters for per-field cascade calculations.
	 *
	 * @param   array   $filters  Active filters.
	 * @param   string  $name     Field name.
	 * @param   bool    $custom   Whether the field is under filter[fields].
	 *
	 * @return  array
	 *
	 * @since  0.1.0
	 */
	protected function getFiltersWithoutField(array $filters, string $name, bool $custom = false): array
	{
		if ($custom)
		{
			unset($filters['fields'][$name]);
			if (empty($filters['fields']))
			{
				unset($filters['fields']);
			}

			return $filters;
		}

		unset($filters[$name]);

		return $filters;
	}

	/**
	 * Applies checkbox rendering behavior to suitable fields.
	 *
	 * @param   Form      $form    Filter form object.
	 * @param   Registry  $params  Module params.
	 *
	 * @return  void
	 *
	 * @since  0.1.0
	 */
	protected function applyCheckboxOptionsBehavior(Form $form, Registry $params): void
	{
		$displayMode = (string) $params->get('checkbox_display_mode', 'all');
		$visibleCount = max(1, (int) $params->get('checkbox_visible_count', 6));
		$showOptionCounts = (int) $params->get('show_option_counts', 0) === 1;

		foreach ($form->getFieldsets() as $key => $fieldset)
		{
			foreach ($form->getFieldset($key) as $field)
			{
				$fieldXml = $this->getFormFieldXml($form, $field->fieldname, $field->group);
				if (!$fieldXml || !isset($fieldXml->option) || count($fieldXml->option) === 0)
				{
					continue;
				}

				$type = strtolower((string) $fieldXml['type']);
				if (!str_contains($type, 'checkbox'))
				{
					continue;
				}

				$fieldXml['dharma_display_mode']  = $displayMode;
				$fieldXml['dharma_visible_count'] = (string) $visibleCount;
				$fieldXml['dharma_hide_clean']    = 'true';
				$fieldXml['dharma_hide_disabled_toggle'] = 'true';
				$fieldXml['dharma_show_option_counts'] = $showOptionCounts ? 'true' : 'false';
			}
		}
	}

	/**
	 * Applies configured labels and display templates to form XML.
	 *
	 * @param   Form      $form    Filter form object.
	 * @param   Registry  $params  Module params.
	 *
	 * @return  void
	 *
	 * @since  0.1.0
	 */
	protected function applyFieldsConfig(Form $form, Registry $params): void
	{
		$config = $this->getFieldsConfig($params);
		if (count($config) === 0)
		{
			return;
		}

		foreach ($form->getFieldsets() as $key => $fieldset)
		{
			foreach ($form->getFieldset($key) as $field)
			{
				$fieldConfig = $config[$field->fieldname] ?? null;
				if (!$fieldConfig)
				{
					continue;
				}

				if (!empty($fieldConfig['title']))
				{
					$form->setFieldAttribute($field->fieldname, 'label', (string) $fieldConfig['title'], $field->group);
				}

				$display = (string) ($fieldConfig['display'] ?? 'auto');
				if ($display === 'auto')
				{
					continue;
				}

				$fieldXml = $this->getFormFieldXml($form, $field->fieldname, $field->group);
				if (!$fieldXml || !isset($fieldXml->option) || count($fieldXml->option) === 0)
				{
					continue;
				}

				if ($display === 'list')
				{
					$fieldXml['type'] = 'list';
					unset($fieldXml['addfieldprefix'], $fieldXml['multiple']);
				}
				elseif ($display === 'checkboxes')
				{
					$fieldXml['type']           = 'RMFieldsStandard_Filter_checkboxes';
					$fieldXml['addfieldprefix'] = 'Joomla\Plugin\RadicalMartFields\Standard\Field';
					$fieldXml['multiple']       = 'true';
				}
				elseif ($display === 'radio')
				{
					$fieldXml['type'] = 'radio';
					$fieldXml['class'] = trim((string) $fieldXml['class'] . ' btn-group duf-radio-native');
					unset($fieldXml['addfieldprefix'], $fieldXml['multiple']);

					$value = $form->getValue($field->fieldname, $field->group);
					if (is_array($value))
					{
						$value = array_values(array_filter($value, static fn($item): bool => (string) $item !== ''));
						$form->setValue($field->fieldname, $field->group, $value[0] ?? '');
					}
				}
			}
		}
	}

	/**
	 * Removes unavailable options from a form field. Fields without remaining options are removed.
	 *
	 * @param   Form    $form       Filter form object.
	 * @param   string  $fieldName  Field name.
	 * @param   string  $group      Field group.
	 * @param   array   $available  Available option values.
	 *
	 * @return  void
	 *
	 * @since  0.1.0
	 */
	protected function filterFormOptions(Form $form, string $fieldName, string $group, array $available, string $emptyMode = 'hide'): void
	{
		$fieldXml = $this->getFormFieldXml($form, $fieldName, $group);
		if (!$fieldXml)
		{
			return;
		}

		if (!isset($fieldXml->option) || count($fieldXml->option) === 0)
		{
			return;
		}

		$hasAvailableOptions = false;
		for ($i = count($fieldXml->option) - 1; $i >= 0; $i--)
		{
			$value = (string) $fieldXml->option[$i]['value'];
			if ($value === '')
			{
				continue;
			}

			if (!isset($available[$value]))
			{
				if ($emptyMode === 'hide')
				{
					unset($fieldXml->option[$i]);
				}
				elseif ($emptyMode === 'disable')
				{
					$fieldXml->option[$i]['disabled'] = 'true';
					$fieldXml->option[$i]['disable'] = 'true';
					$fieldXml->option[$i]['class'] = trim((string) $fieldXml->option[$i]['class'] . ' dharma-filter-option-unavailable');
				}

				continue;
			}

			$fieldXml->option[$i]['dharma_option_count'] = (string) max(0, (int) $available[$value]);
			$hasAvailableOptions = true;
		}

		if (!$hasAvailableOptions && $emptyMode === 'hide')
		{
			$form->removeField($fieldName, $group);

			return;
		}

		$this->sortFieldOptionsByAvailability($fieldXml);
	}

	/**
	 * Adds product counts to field option XML.
	 *
	 * @param   Form    $form       Filter form object.
	 * @param   string  $fieldName  Field name.
	 * @param   string  $group      Field group.
	 * @param   array   $counts     Option value counts.
	 *
	 * @return  void
	 *
	 * @since  0.1.0
	 */
	protected function applyOptionCounts(Form $form, string $fieldName, string $group, array $counts): void
	{
		$fieldXml = $this->getFormFieldXml($form, $fieldName, $group);
		if (!$fieldXml || !isset($fieldXml->option) || count($fieldXml->option) === 0)
		{
			return;
		}

		foreach ($fieldXml->option as $option)
		{
			$value = (string) $option['value'];
			if ($value === '')
			{
				continue;
			}

			$option['dharma_option_count'] = (string) max(0, (int) ($counts[$value] ?? 0));
			if ((string) $option['dharma_count_label'] === '')
			{
				$option['dharma_count_label'] = (string) $option;
			}

			$option[0] = (string) $option['dharma_count_label'] . ' (' . (string) $option['dharma_option_count'] . ')';
		}
	}

	/**
	 * Moves enabled/available options before disabled/unavailable options.
	 *
	 * @param   \SimpleXMLElement  $fieldXml  Field XML element.
	 *
	 * @return  void
	 *
	 * @since  0.1.0
	 */
	protected function sortFieldOptionsByAvailability(\SimpleXMLElement $fieldXml): void
	{
		$options = [];
		foreach ($fieldXml->option as $option)
		{
			$attributes = [];
			foreach ($option->attributes() as $name => $value)
			{
				$attributes[(string) $name] = (string) $value;
			}

			$options[] = [
				'text'       => (string) $option,
				'attributes' => $attributes,
				'disabled'   => !empty($attributes['disabled']) || !empty($attributes['disable']),
				'order'      => count($options),
			];
		}

		usort($options, static function (array $a, array $b): int {
			$disabled = (int) $a['disabled'] <=> (int) $b['disabled'];
			if ($disabled !== 0)
			{
				return $disabled;
			}

			$sort = strnatcasecmp($a['attributes']['value'] ?? $a['text'], $b['attributes']['value'] ?? $b['text']);

			return $sort !== 0 ? $sort : $a['order'] <=> $b['order'];
		});

		for ($i = count($fieldXml->option) - 1; $i >= 0; $i--)
		{
			unset($fieldXml->option[$i]);
		}

		foreach ($options as $option)
		{
			$optionXml = $fieldXml->addChild('option', htmlspecialchars($option['text']));
			foreach ($option['attributes'] as $name => $value)
			{
				$optionXml->addAttribute($name, $value);
			}
		}
	}

	/**
	 * Gets available filter values from published products in the active category.
	 *
	 * @param   int    $pk              Category id.
	 * @param   array  $currentFilters  Active request filters.
	 * @param   bool   $includePrice    Whether to calculate price range data.
	 *
	 * @throws  \Exception
	 *
	 * @return  array
	 *
	 * @since  0.1.0
	 */
	protected function getAvailableFilterData(int $pk, array $currentFilters = [], bool $includePrice = true): array
	{
		if ($this->_availableFilterData === null)
		{
			$this->_availableFilterData = [];
		}

		$filtersHash = md5(json_encode($currentFilters));
		$cacheKey = $pk . ':' . ($includePrice ? 'price' : 'no-price') . ':' . $filtersHash;
		if (isset($this->_availableFilterData[$cacheKey]))
		{
			return $this->_availableFilterData[$cacheKey];
		}

		$priceCacheKey = $pk . ':price:' . $filtersHash;
		if (!$includePrice && isset($this->_availableFilterData[$priceCacheKey]))
		{
			return $this->_availableFilterData[$priceCacheKey];
		}

		// Use persistent cache for unfiltered data — it only changes when the index is rebuilt.
		$noActiveFilters = count($currentFilters) === 0;
		if ($noActiveFilters)
		{
			try
			{
				/** @var \Joomla\CMS\Cache\Controller\CallbackController $persistentCache */
				$persistentCache = Factory::getContainer()
					->get(CacheControllerFactoryInterface::class)
					->createCacheController('callback', ['defaultgroup' => 'mod_dharma_universal_filter', 'caching' => true, 'lifetime' => 15]);

				$langKey      = $this->getLanguageCacheKey();
				$persistKey   = $pk . ':' . ($includePrice ? 'price' : 'no-price') . ':' . $langKey;
				$cachedResult = $persistentCache->get(
					function () use ($pk, $currentFilters, $includePrice): ?array {
						return $this->getIndexedAvailableFilterData($pk, $currentFilters, $includePrice);
					},
					[],
					$persistKey
				);

				if ($cachedResult !== null)
				{
					$this->_availableFilterData[$cacheKey] = $cachedResult;

					return $cachedResult;
				}
			}
			catch (\Throwable)
			{
				// Fall through to normal flow if cache is unavailable.
			}
		}

		$indexed = $this->getIndexedAvailableFilterData($pk, $currentFilters, $includePrice);
		if ($indexed !== null)
		{
			$this->_availableFilterData[$cacheKey] = $indexed;

			return $indexed;
		}

		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$query = $db->getQuery(true)
			->select($db->quoteName(['ci.filter_categories', 'ci.filter_prices', 'ci.filter_fields']))
			->from($db->quoteName('#__radicalmart_categories_items', 'ci'))
			->where($db->quoteName('ci.state') . ' = 1')
			->where($db->quoteName('ci.category_id') . ' = :category_id')
			->bind(':category_id', $pk, ParameterType::INTEGER);

		if (Multilanguage::isEnabled())
		{
			$query->whereIn(
				$db->quoteName('ci.language'),
				[Factory::getApplication()->getLanguage()->getTag(), '*'],
				ParameterType::STRING
			);
		}

		$rows = $db->setQuery($query)->loadObjectList();
		$result = [
			'count'      => count($rows),
			'has_price'  => false,
			'price_min'  => 0,
			'price_max'  => 0,
			'categories' => [],
			'fields'     => [],
		];

		foreach ($rows as $row)
		{
			if (!$this->rowMatchesFilters($row, $currentFilters))
			{
				continue;
			}

			foreach (array_filter(explode(',', (string) $row->filter_categories)) as $categoryId)
			{
				$value = (string) (int) $categoryId;
				$result['categories'][$value] = (int) ($result['categories'][$value] ?? 0) + 1;
			}

			if ($includePrice)
			{
				foreach ($this->decodeJsonObject((string) $row->filter_prices) as $price)
				{
					if (is_array($price) && !empty($price['max']) && (float) $price['max'] > 0)
					{
						$result['has_price'] = true;
						$min = !empty($price['min']) ? (float) $price['min'] : 0;
						$max = (float) $price['max'];
						if ($min > 0 && ($result['price_min'] === 0 || $min < $result['price_min']))
						{
							$result['price_min'] = $min;
						}

						if ($max > $result['price_max'])
						{
							$result['price_max'] = $max;
						}

						break;
					}
				}
			}

			foreach ($this->decodeJsonObject((string) $row->filter_fields) as $productFields)
			{
				if (!is_array($productFields))
				{
					continue;
				}

				foreach ($productFields as $alias => $value)
				{
					if ($alias === 'com_radicalmart_state' || $alias === 'com_radicalmart_in_stock')
					{
						continue;
					}

					$this->collectAvailableFieldValues($result['fields'], (string) $alias, $value);
				}
			}
		}

		$result['count'] = count($rows) > 0 ? $this->countMatchingRows($rows, $currentFilters) : 0;
		$this->_availableFilterData[$cacheKey] = $result;

		return $result;
	}

	/**
	 * Gets available filter data from Dharma Universal Filter task indexes.
	 *
	 * @param   int    $pk              Category id.
	 * @param   array  $currentFilters  Active request filters.
	 * @param   bool   $includePrice    Whether to calculate price range data.
	 *
	 * @return  array|null  Indexed data, or null when indexes are not available.
	 *
	 * @since  0.1.0
	 */
	protected function getIndexedAvailableFilterData(int $pk, array $currentFilters = [], bool $includePrice = true): ?array
	{
		try
		{
			$db      = Factory::getContainer()->get(DatabaseInterface::class);
			$itemIds = $this->getIndexedMatchingItemIds($pk, $currentFilters);
		}
		catch (\Throwable)
		{
			return null;
		}

		if (count($itemIds) === 0)
		{
			if (!$this->hasFilterIndexRows($pk))
			{
				return null;
			}

			return [
				'count'      => 0,
				'has_price'  => false,
				'price_min'  => 0,
				'price_max'  => 0,
				'categories' => [],
				'fields'     => [],
			];
		}

		$result = [
			'count'      => count($itemIds),
			'has_price'  => false,
			'price_min'  => 0,
			'price_max'  => 0,
			'categories' => [],
			'fields'     => [],
		];

		$restrictToItemIds = count($currentFilters) > 0;
		$categoryFields = ['categories', 'manufacturers', 'badges'];
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('field_name'),
				'ANY_VALUE(' . $db->quoteName('field_value') . ') AS ' . $db->quoteName('field_value'),
				'COUNT(DISTINCT ' . $db->quoteName('item_id') . ') AS ' . $db->quoteName('option_count'),
			])
			->from($db->quoteName('#__dharma_universal_filter_index'))
			->where($db->quoteName('category_id') . ' = :category_id')
			->group($db->quoteName(['field_name', 'field_value_hash']))
			->bind(':category_id', $pk, ParameterType::INTEGER);

		if ($restrictToItemIds)
		{
			$query->whereIn($db->quoteName('item_id'), $itemIds, ParameterType::INTEGER);
		}

		$this->addIndexedLanguageFilter($query);

		foreach ($db->setQuery($query)->loadObjectList() as $row)
		{
			$fieldName = (string) $row->field_name;
			$value     = (string) $row->field_value;

			if ($value === '')
			{
				continue;
			}

			if (in_array($fieldName, $categoryFields, true))
			{
				$result['categories'][$value] = (int) $row->option_count;
				continue;
			}

			if ($fieldName === 'in_stock')
			{
				continue;
			}

			$result['fields'][$fieldName][$value] = (int) $row->option_count;
		}

		if ($includePrice)
		{
			$currency = PriceHelper::getCurrency($this->getCurrentFilters()['currency'] ?? null);
			$currencyGroup = (string) $currency['group'];
			$query = $db->getQuery(true)
				->select([
					'MIN(NULLIF(' . $db->quoteName('price_min') . ', 0)) AS ' . $db->quoteName('price_min'),
					'MAX(' . $db->quoteName('price_max') . ') AS ' . $db->quoteName('price_max'),
				])
				->from($db->quoteName('#__dharma_universal_filter_price_index'))
				->where($db->quoteName('category_id') . ' = :category_id')
				->where($db->quoteName('currency') . ' = :currency')
				->bind(':category_id', $pk, ParameterType::INTEGER)
				->bind(':currency', $currencyGroup);

			if ($restrictToItemIds)
			{
				$query->whereIn($db->quoteName('item_id'), $itemIds, ParameterType::INTEGER);
			}

			$this->addIndexedLanguageFilter($query);

			$price = $db->setQuery($query)->loadObject();
			if ($price && (float) $price->price_max > 0)
			{
				$result['has_price'] = true;
				$result['price_min'] = $price->price_min !== null ? (float) $price->price_min : 0;
				$result['price_max'] = (float) $price->price_max;
			}
		}

		return $result;
	}

	/**
	 * Gets item ids matching active filters in task index tables.
	 *
	 * @param   int    $pk              Category id.
	 * @param   array  $currentFilters  Active request filters.
	 *
	 * @return  int[]
	 *
	 * @since  0.1.0
	 */
	protected function getIndexedMatchingItemIds(int $pk, array $currentFilters): array
	{
		$itemIds = $this->getIndexedCategoryItemIds($pk);
		if (count($itemIds) === 0)
		{
			return [];
		}

		foreach (['categories', 'manufacturers', 'badges', 'in_stock'] as $fieldName)
		{
			if (empty($currentFilters[$fieldName]))
			{
				continue;
			}

			$itemIds = array_values(array_intersect(
				$itemIds,
				$this->getIndexedItemIdsByFieldValues($pk, $fieldName, $currentFilters[$fieldName])
			));

			if (count($itemIds) === 0)
			{
				return [];
			}
		}

		foreach ($currentFilters['fields'] ?? [] as $fieldName => $values)
		{
			$itemIds = array_values(array_intersect(
				$itemIds,
				$this->getIndexedItemIdsByFieldValues($pk, (string) $fieldName, $values)
			));

			if (count($itemIds) === 0)
			{
				return [];
			}
		}

		if (!empty($currentFilters['price']))
		{
			$itemIds = array_values(array_intersect(
				$itemIds,
				$this->getIndexedItemIdsByPrice($pk, $currentFilters['price'])
			));
		}

		return array_values(array_unique(array_map('intval', $itemIds)));
	}

	/**
	 * Gets indexed item ids for category.
	 *
	 * @param   int  $pk  Category id.
	 *
	 * @return  int[]
	 *
	 * @since  0.1.0
	 */
	protected function getIndexedCategoryItemIds(int $pk): array
	{
		if ($this->_indexedCategoryItemIds === null)
		{
			$this->_indexedCategoryItemIds = [];
		}

		$cacheKey = $pk . ':' . $this->getLanguageCacheKey();
		if (isset($this->_indexedCategoryItemIds[$cacheKey]))
		{
			return $this->_indexedCategoryItemIds[$cacheKey];
		}

		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$query = $db->getQuery(true)
			->select('DISTINCT ' . $db->quoteName('item_id'))
			->from($db->quoteName('#__dharma_universal_filter_index'))
			->where($db->quoteName('category_id') . ' = :category_id')
			->bind(':category_id', $pk, ParameterType::INTEGER);

		$this->addIndexedLanguageFilter($query);

		$this->_indexedCategoryItemIds[$cacheKey] = array_map('intval', $db->setQuery($query)->loadColumn());

		return $this->_indexedCategoryItemIds[$cacheKey];
	}

	/**
	 * Gets indexed item ids by field values.
	 *
	 * @param   int     $pk         Category id.
	 * @param   string  $fieldName  Field name.
	 * @param   array   $values     Filter values.
	 *
	 * @return  int[]
	 *
	 * @since  0.1.0
	 */
	protected function getIndexedItemIdsByFieldValues(int $pk, string $fieldName, array $values): array
	{
		$hashes = array_map(static fn($value): string => sha1((string) $value), $values);
		if (count($hashes) === 0)
		{
			return [];
		}

		sort($hashes);
		if ($this->_indexedFieldItemIds === null)
		{
			$this->_indexedFieldItemIds = [];
		}

		$cacheKey = $pk . ':' . $this->getLanguageCacheKey() . ':' . $fieldName . ':' . md5(json_encode($hashes));
		if (isset($this->_indexedFieldItemIds[$cacheKey]))
		{
			return $this->_indexedFieldItemIds[$cacheKey];
		}

		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$query = $db->getQuery(true)
			->select('DISTINCT ' . $db->quoteName('item_id'))
			->from($db->quoteName('#__dharma_universal_filter_index'))
			->where($db->quoteName('category_id') . ' = :category_id')
			->where($db->quoteName('field_name') . ' = :field_name')
			->whereIn($db->quoteName('field_value_hash'), $hashes, ParameterType::STRING)
			->bind(':category_id', $pk, ParameterType::INTEGER)
			->bind(':field_name', $fieldName);

		$this->addIndexedLanguageFilter($query);

		$this->_indexedFieldItemIds[$cacheKey] = array_map('intval', $db->setQuery($query)->loadColumn());

		return $this->_indexedFieldItemIds[$cacheKey];
	}

	/**
	 * Gets indexed item ids by active price filter.
	 *
	 * @param   int    $pk           Category id.
	 * @param   array  $priceFilter  Price filter.
	 *
	 * @return  int[]
	 *
	 * @since  0.1.0
	 */
	protected function getIndexedItemIdsByPrice(int $pk, array $priceFilter): array
	{
		$currency      = PriceHelper::getCurrency($this->getCurrentFilters()['currency'] ?? null);
		$currencyGroup = (string) $currency['group'];
		$priceFromKey  = $priceFilter['from'] !== null ? (string) (float) $priceFilter['from'] : '';
		$priceToKey    = $priceFilter['to'] !== null ? (string) (float) $priceFilter['to'] : '';

		if ($this->_indexedPriceItemIds === null)
		{
			$this->_indexedPriceItemIds = [];
		}

		$cacheKey = $pk . ':' . $this->getLanguageCacheKey() . ':' . $currencyGroup . ':' . $priceFromKey . ':' . $priceToKey;
		if (isset($this->_indexedPriceItemIds[$cacheKey]))
		{
			return $this->_indexedPriceItemIds[$cacheKey];
		}

		$db            = Factory::getContainer()->get(DatabaseInterface::class);
		$query         = $db->getQuery(true)
			->select('DISTINCT ' . $db->quoteName('item_id'))
			->from($db->quoteName('#__dharma_universal_filter_price_index'))
			->where($db->quoteName('category_id') . ' = :category_id')
			->where($db->quoteName('currency') . ' = :currency')
			->bind(':category_id', $pk, ParameterType::INTEGER)
			->bind(':currency', $currencyGroup);

		if ($priceFilter['from'] !== null)
		{
			$priceFrom = (float) $priceFilter['from'];
			$query->where($db->quoteName('price_max') . ' >= :price_from')
				->bind(':price_from', $priceFrom);
		}

		if ($priceFilter['to'] !== null)
		{
			$priceTo = (float) $priceFilter['to'];
			$query->where($db->quoteName('price_min') . ' <= :price_to')
				->bind(':price_to', $priceTo);
		}

		$this->addIndexedLanguageFilter($query);

		$this->_indexedPriceItemIds[$cacheKey] = array_map('intval', $db->setQuery($query)->loadColumn());

		return $this->_indexedPriceItemIds[$cacheKey];
	}

	/**
	 * Gets current language cache key for indexed queries.
	 *
	 * @return  string
	 *
	 * @since  0.1.0
	 */
	protected function getLanguageCacheKey(): string
	{
		if (!Multilanguage::isEnabled())
		{
			return '*';
		}

		return Factory::getApplication()->getLanguage()->getTag();
	}

	/**
	 * Adds current language condition to an index query when multilingual mode is enabled.
	 *
	 * @param   \Joomla\Database\DatabaseQuery  $query  Query object.
	 *
	 * @return  void
	 *
	 * @since  0.1.0
	 */
	protected function addIndexedLanguageFilter(\Joomla\Database\DatabaseQuery $query): void
	{
		if (!Multilanguage::isEnabled())
		{
			return;
		}

		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$query->whereIn(
			$db->quoteName('language'),
			[Factory::getApplication()->getLanguage()->getTag(), '*'],
			ParameterType::STRING
		);
	}

	/**
	 * Checks whether the task index contains rows for category.
	 *
	 * @param   int  $pk  Category id.
	 *
	 * @return  bool
	 *
	 * @since  0.1.0
	 */
	protected function hasFilterIndexRows(int $pk): bool
	{
		if ($this->_hasFilterIndexRows === null)
		{
			$this->_hasFilterIndexRows = [];
		}

		$cacheKey = $pk . ':' . $this->getLanguageCacheKey();
		if (array_key_exists($cacheKey, $this->_hasFilterIndexRows))
		{
			return $this->_hasFilterIndexRows[$cacheKey];
		}

		try
		{
			$db = Factory::getContainer()->get(DatabaseInterface::class);
			$query = $db->getQuery(true)
				->select('COUNT(*)')
				->from($db->quoteName('#__dharma_universal_filter_index'))
				->where($db->quoteName('category_id') . ' = :category_id')
				->bind(':category_id', $pk, ParameterType::INTEGER);

			$this->_hasFilterIndexRows[$cacheKey] = (int) $db->setQuery($query)->loadResult() > 0;

			return $this->_hasFilterIndexRows[$cacheKey];
		}
		catch (\Throwable)
		{
			$this->_hasFilterIndexRows[$cacheKey] = false;

			return false;
		}
	}

	/**
	 * Gets active request filters for cascade calculations.
	 *
	 * @return  array
	 *
	 * @since  0.1.0
	 */
	protected function getCurrentFilters(): array
	{
		if ($this->_currentFilters !== null)
		{
			return $this->_currentFilters;
		}

		$filter = Factory::getApplication()->getInput()->get('filter', [], 'array');
		if (!is_array($filter))
		{
			$this->_currentFilters = [];

			return $this->_currentFilters;
		}

		$result = [];
		foreach (['categories', 'manufacturers', 'badges', 'in_stock'] as $name)
		{
			if (!empty($filter[$name]))
			{
				$result[$name] = $this->normalizeFilterValues($filter[$name]);
			}
		}

		if (!empty($filter['fields']) && is_array($filter['fields']))
		{
			foreach ($filter['fields'] as $alias => $value)
			{
				$values = $this->normalizeFilterValues($value);
				if (count($values) > 0)
				{
					$result['fields'][(string) $alias] = $values;
				}
			}
		}

		if (!empty($filter['price']) && is_array($filter['price']))
		{
			$from = $this->normalizePriceValue($filter['price']['from'] ?? null);
			$to   = $this->normalizePriceValue($filter['price']['to'] ?? null);
			if ($from !== null || $to !== null)
			{
				$result['price'] = [
					'from' => $from,
					'to'   => $to,
				];
			}
		}

		$this->_currentFilters = $result;

		return $this->_currentFilters;
	}

	/**
	 * Normalizes filter value into string array.
	 *
	 * @param   mixed  $value  Filter value.
	 *
	 * @return  array
	 *
	 * @since  0.1.0
	 */
	protected function normalizeFilterValues(mixed $value): array
	{
		if (!is_array($value))
		{
			$value = [$value];
		}

		$result = [];
		foreach ($value as $item)
		{
			$item = trim((string) $item);
			if ($item !== '')
			{
				$result[] = $item;
			}
		}

		return array_values(array_unique($result));
	}

	/**
	 * Normalizes price value.
	 *
	 * @param   mixed  $value  Raw price value.
	 *
	 * @return  float|null
	 *
	 * @since  0.1.0
	 */
	protected function normalizePriceValue(mixed $value): ?float
	{
		$value = trim(str_replace([' ', ','], ['', '.'], (string) $value));
		if ($value === '' || !is_numeric($value))
		{
			return null;
		}

		return (float) $value;
	}

	/**
	 * Counts category index rows matching active filters.
	 *
	 * @param   array  $rows            Category index rows.
	 * @param   array  $currentFilters  Current filters.
	 *
	 * @return  int
	 *
	 * @since  0.1.0
	 */
	protected function countMatchingRows(array $rows, array $currentFilters): int
	{
		$count = 0;
		foreach ($rows as $row)
		{
			if ($this->rowMatchesFilters($row, $currentFilters))
			{
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Checks whether an indexed product row matches active filters.
	 *
	 * @param   object  $row             Category item index row.
	 * @param   array   $currentFilters  Current filters.
	 *
	 * @return  bool
	 *
	 * @since  0.1.0
	 */
	protected function rowMatchesFilters(object $row, array $currentFilters): bool
	{
		if (count($currentFilters) === 0)
		{
			return true;
		}

		$rowCategories = array_filter(array_map('trim', explode(',', (string) $row->filter_categories)));
		foreach (['categories', 'manufacturers', 'badges'] as $name)
		{
			if (!empty($currentFilters[$name]) && count(array_intersect($currentFilters[$name], $rowCategories)) === 0)
			{
				return false;
			}
		}

		if (!empty($currentFilters['price']) && !$this->rowMatchesPriceFilter($row, $currentFilters['price']))
		{
			return false;
		}

		if (empty($currentFilters['fields']))
		{
			return true;
		}

		foreach ($this->decodeJsonObject((string) $row->filter_fields) as $productFields)
		{
			if (!is_array($productFields))
			{
				continue;
			}

			if ($this->productFieldsMatchFilters($productFields, $currentFilters['fields']))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks whether indexed price data matches active price filter.
	 *
	 * @param   object  $row          Category item index row.
	 * @param   array   $priceFilter  Price filter.
	 *
	 * @return  bool
	 *
	 * @since  0.1.0
	 */
	protected function rowMatchesPriceFilter(object $row, array $priceFilter): bool
	{
		foreach ($this->decodeJsonObject((string) $row->filter_prices) as $price)
		{
			if (!is_array($price))
			{
				continue;
			}

			$min = !empty($price['min']) ? (float) $price['min'] : 0;
			$max = !empty($price['max']) ? (float) $price['max'] : 0;
			if ($max <= 0)
			{
				continue;
			}

			if ($priceFilter['from'] !== null && $max < (float) $priceFilter['from'])
			{
				continue;
			}

			if ($priceFilter['to'] !== null && $min > (float) $priceFilter['to'])
			{
				continue;
			}

			return true;
		}

		return false;
	}

	/**
	 * Checks whether product field values match active field filters.
	 *
	 * @param   array  $productFields  Indexed product fields.
	 * @param   array  $fieldFilters   Active field filters.
	 *
	 * @return  bool
	 *
	 * @since  0.1.0
	 */
	protected function productFieldsMatchFilters(array $productFields, array $fieldFilters): bool
	{
		foreach ($fieldFilters as $alias => $values)
		{
			if (!array_key_exists($alias, $productFields))
			{
				return false;
			}

			$productValues = [];
			$this->collectAvailableFieldValues($productValues, (string) $alias, $productFields[$alias]);
			$productValues = array_keys($productValues[$alias] ?? []);
			if (count(array_intersect($values, $productValues)) === 0)
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Gets field XML by Joomla Form group path.
	 *
	 * @param   Form    $form       Filter form object.
	 * @param   string  $fieldName  Field name.
	 * @param   string  $group      Dot-separated field group.
	 *
	 * @return  \SimpleXMLElement|null
	 *
	 * @since  0.1.0
	 */
	protected function getFormFieldXml(Form $form, string $fieldName, string $group): ?\SimpleXMLElement
	{
		$xml = $form->getXml();
		$groupPath = '';
		foreach (explode('.', $group) as $groupPart)
		{
			$groupPath .= '//fields[@name="' . $groupPart . '"]';
		}

		$matches = $xml->xpath($groupPath . '//field[@name="' . $fieldName . '"]');

		return !empty($matches[0]) ? $matches[0] : null;
	}

	/**
	 * Normalizes module field configuration into an alias-indexed array.
	 *
	 * @param   Registry  $params  Module params.
	 *
	 * @return  array
	 *
	 * @since  0.1.0
	 */
	protected function getFieldsConfig(Registry $params): array
	{
		$value = $params->get('fields_config', []);
		if ($value instanceof Registry)
		{
			$value = $value->toArray();
		}
		elseif (is_object($value))
		{
			$value = (array) $value;
		}
		elseif (is_string($value) && $value !== '')
		{
			$decoded = json_decode($value, true);
			$value   = is_array($decoded) ? $decoded : [];
		}

		if (!is_array($value))
		{
			return [];
		}

		$result = [];
		foreach ($value as $row)
		{
			if ($row instanceof Registry)
			{
				$row = $row->toArray();
			}
			elseif (is_object($row))
			{
				$row = (array) $row;
			}

			if (!is_array($row) || empty($row['field']))
			{
				continue;
			}

			$result[(string) $row['field']] = $row;
		}

		return $result;
	}

	/**
	 * Collects scalar product field values recursively.
	 *
	 * @param   array   $fields  Available fields accumulator.
	 * @param   string  $alias   Field alias.
	 * @param   mixed   $value   Product field value.
	 *
	 * @return  void
	 *
	 * @since  0.1.0
	 */
	protected function collectAvailableFieldValues(array &$fields, string $alias, mixed $value): void
	{
		if (!isset($fields[$alias]))
		{
			$fields[$alias] = [];
		}

		if (is_array($value))
		{
			foreach ($value as $item)
			{
				$this->collectAvailableFieldValues($fields, $alias, $item);
			}

			return;
		}

		$value = trim((string) $value);
		if ($value !== '')
		{
			$fields[$alias][$value] = (int) ($fields[$alias][$value] ?? 0) + 1;
		}
	}

	/**
	 * Decodes a JSON object into an array.
	 *
	 * @param   string  $json  JSON string.
	 *
	 * @return  array
	 *
	 * @since  0.1.0
	 */
	protected function decodeJsonObject(string $json): array
	{
		if ($json === '')
		{
			return [];
		}

		$value = json_decode($json, true);

		return is_array($value) ? $value : [];
	}

	/**
	 * Method to get category object.
	 *
	 * @param   int|null  $pk  Category id.
	 *
	 * @throws  \Exception
	 *
	 * @return  object|false  Category object.
	 *
	 * @since  1.2.0
	 */
	public function getCategory(?int $pk = null)
	{
		if (empty($pk))
		{
			$pk = 1;
		}

		if ($this->_category === null)
		{
			$this->_category = [];
		}

		if (!isset($this->_category[$pk]))
		{
			$model      = $this->getModel();
			$categories = $model->getCategories([$pk]);

			$this->_category[$pk] = (!empty($categories[$pk])) ? $categories[$pk] : false;
		}

		return $this->_category[$pk];
	}

	/**
	 * Method to get products model.
	 *
	 * @throws  \Exception
	 *
	 * @return  ProductsModel  Products mode.
	 *
	 * @since  1.1.0
	 */
	protected function getModel(): ProductsModel
	{
		if ($this->_model === null)
		{
			$app = Factory::getApplication();

			// Load language
			$app->getLanguage()->load('com_radicalmart');
			if (isset(Factory::$language))
			{
				Factory::getLanguage()->load('com_radicalmart');
			}

			// Get model
			$this->_model = $app->bootComponent('com_radicalmart')->getMVCFactory()
				->createModel('Products', 'Site', ['ignore_request' => true]);
			$this->_model->setContext('com_radicalmart.category');
			$this->_model->setState('params', ParamsHelper::getComponentParams());
			$this->_model->setState('filter.published', 1);
			$this->_model->setState('filter.language', Multilanguage::isEnabled());
		}

		return $this->_model;
	}
}
