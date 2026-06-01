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

namespace Joomla\Plugin\Task\DharmaUniversalFilter\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;

final class DharmaUniversalFilter extends CMSPlugin implements SubscriberInterface
{
	use DatabaseAwareTrait;
	use TaskPluginTrait;

	/**
	 * Supported scheduled routines.
	 *
	 * @var  array
	 *
	 * @since  0.1.0
	 */
	private const TASKS_MAP = [
		'dharma.universal_filter.rebuild_index' => [
			'langConstPrefix' => 'PLG_TASK_DHARMA_UNIVERSAL_FILTER_REBUILD_INDEX',
			'method'          => 'rebuildIndex',
		],
	];

	/**
	 * Autoload language files.
	 *
	 * @var  bool
	 *
	 * @since  0.1.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Subscribed events.
	 *
	 * @return  array
	 *
	 * @since  0.1.0
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onTaskOptionsList' => 'advertiseRoutines',
			'onExecuteTask'     => 'standardRoutineHandler',
			'onContentPrepareForm' => 'normalizeSchedulerTaskFormData',
		];
	}

	/**
	 * Normalizes scheduler form data before core task plugins inspect it.
	 *
	 * @param   object  $event  Form prepare event.
	 *
	 * @return  void
	 *
	 * @since  0.1.0
	 */
	public function normalizeSchedulerTaskFormData(object $event): void
	{
		if (!method_exists($event, 'getForm') || !method_exists($event, 'getData'))
		{
			return;
		}

		$form = $event->getForm();

		if (!is_object($form) || !method_exists($form, 'getName') || $form->getName() !== 'com_scheduler.task')
		{
			return;
		}

		$data = $event->getData();

		if (!is_object($data) || !empty($data->type))
		{
			return;
		}

		$data->type = $data->taskOption->id ?? '';
	}

	/**
	 * Rebuilds Dharma Universal Filter index tables.
	 *
	 * @param   ExecuteTaskEvent  $event  Scheduled task event.
	 *
	 * @return  int
	 *
	 * @since  0.1.0
	 */
	private function rebuildIndex(ExecuteTaskEvent $event): int
	{
		try
		{
			$this->ensureTables();

			$db = $this->getDatabase();
			$db->truncateTable('#__dharma_universal_filter_index');
			$db->truncateTable('#__dharma_universal_filter_price_index');

			$query = $db->getQuery(true)
				->select($db->quoteName([
					'item_id',
					'category_id',
					'type',
					'in_stock',
					'state',
					'language',
					'filter_categories',
					'filter_prices',
					'filter_fields',
				]))
				->from($db->quoteName('#__radicalmart_categories_items'))
				->where($db->quoteName('state') . ' = 1');

			$rows = $db->setQuery($query)->loadObjectList();
			$valueRows = [];
			$priceRows = [];

			foreach ($rows as $row)
			{
				$this->appendCategoryValues($valueRows, $row);
				$this->appendFieldValues($valueRows, $row);
				$this->appendPriceValues($priceRows, $row);
			}

			$this->insertRows('#__dharma_universal_filter_index', [
				'category_id',
				'item_id',
				'field_name',
				'field_value',
				'field_value_hash',
				'language',
				'in_stock',
			], $valueRows);
			$this->insertRows('#__dharma_universal_filter_price_index', [
				'category_id',
				'item_id',
				'currency',
				'price_min',
				'price_max',
				'language',
				'in_stock',
			], $priceRows);

			$this->safeLogTask(
				sprintf('Dharma Universal Filter index rebuilt: %d value rows, %d price rows.', count($valueRows), count($priceRows)),
				'info'
			);

			return Status::OK;
		}
		catch (\Throwable $exception)
		{
			$this->safeLogTask('Dharma Universal Filter index rebuild failed: ' . $exception->getMessage(), 'error');

			return Status::KNOCKOUT;
		}
	}

	/**
	 * Logs task messages without letting logging failures break index rebuild.
	 *
	 * @param   string  $message   Log message.
	 * @param   string  $priority  Log priority.
	 *
	 * @return  void
	 *
	 * @since  0.1.0
	 */
	private function safeLogTask(string $message, string $priority = 'info'): void
	{
		try
		{
			$this->logTask($message, $priority);
		}
		catch (\Throwable)
		{
			// Logging is auxiliary; the index rebuild result must remain authoritative.
		}
	}

	/**
	 * Creates index tables when missing.
	 *
	 * @return  void
	 *
	 * @since  0.1.0
	 */
	private function ensureTables(): void
	{
		$db = $this->getDatabase();
		$db->setQuery(
			'CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__dharma_universal_filter_index') . ' (
				' . $db->quoteName('id') . ' BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				' . $db->quoteName('category_id') . ' INT UNSIGNED NOT NULL DEFAULT 0,
				' . $db->quoteName('item_id') . ' INT UNSIGNED NOT NULL DEFAULT 0,
				' . $db->quoteName('field_name') . ' VARCHAR(191) NOT NULL DEFAULT \'\',
				' . $db->quoteName('field_value') . ' VARCHAR(255) NOT NULL DEFAULT \'\',
				' . $db->quoteName('field_value_hash') . ' CHAR(40) NOT NULL DEFAULT \'\',
				' . $db->quoteName('language') . ' VARCHAR(7) NOT NULL DEFAULT \'*\',
				' . $db->quoteName('in_stock') . ' TINYINT NOT NULL DEFAULT 0,
				PRIMARY KEY (' . $db->quoteName('id') . '),
				KEY ' . $db->quoteName('idx_duf_category_field_value') . ' (' . $db->quoteName('category_id') . ', ' . $db->quoteName('field_name') . ', ' . $db->quoteName('field_value_hash') . '),
				KEY ' . $db->quoteName('idx_duf_category_item') . ' (' . $db->quoteName('category_id') . ', ' . $db->quoteName('item_id') . '),
				KEY ' . $db->quoteName('idx_duf_field_item') . ' (' . $db->quoteName('field_name') . ', ' . $db->quoteName('item_id') . ')
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci'
		)->execute();

		$db->setQuery(
			'CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__dharma_universal_filter_price_index') . ' (
				' . $db->quoteName('id') . ' BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				' . $db->quoteName('category_id') . ' INT UNSIGNED NOT NULL DEFAULT 0,
				' . $db->quoteName('item_id') . ' INT UNSIGNED NOT NULL DEFAULT 0,
				' . $db->quoteName('currency') . ' VARCHAR(32) NOT NULL DEFAULT \'\',
				' . $db->quoteName('price_min') . ' DECIMAL(20,6) NOT NULL DEFAULT 0,
				' . $db->quoteName('price_max') . ' DECIMAL(20,6) NOT NULL DEFAULT 0,
				' . $db->quoteName('language') . ' VARCHAR(7) NOT NULL DEFAULT \'*\',
				' . $db->quoteName('in_stock') . ' TINYINT NOT NULL DEFAULT 0,
				PRIMARY KEY (' . $db->quoteName('id') . '),
				KEY ' . $db->quoteName('idx_duf_price_category_currency') . ' (' . $db->quoteName('category_id') . ', ' . $db->quoteName('currency') . '),
				KEY ' . $db->quoteName('idx_duf_price_category_item') . ' (' . $db->quoteName('category_id') . ', ' . $db->quoteName('item_id') . ')
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci'
		)->execute();
	}

	/**
	 * Appends category-derived index values.
	 *
	 * @param   array  $rows  Accumulator.
	 * @param   object $row   Category item row.
	 *
	 * @return  void
	 *
	 * @since  0.1.0
	 */
	private function appendCategoryValues(array &$rows, object $row): void
	{
		foreach (array_filter(explode(',', (string) $row->filter_categories)) as $categoryId)
		{
			$value = (string) (int) $categoryId;
			foreach (['categories', 'manufacturers', 'badges'] as $fieldName)
			{
				$rows[] = $this->buildValueRow($row, $fieldName, $value);
			}
		}

		$rows[] = $this->buildValueRow($row, 'in_stock', (string) (int) $row->in_stock);
	}

	/**
	 * Appends product field index values.
	 *
	 * @param   array   $rows  Accumulator.
	 * @param   object  $row   Category item row.
	 *
	 * @return  void
	 *
	 * @since  0.1.0
	 */
	private function appendFieldValues(array &$rows, object $row): void
	{
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

				foreach ($this->normalizeValues($value) as $normalizedValue)
				{
					$rows[] = $this->buildValueRow($row, (string) $alias, $normalizedValue);
				}
			}
		}
	}

	/**
	 * Appends price index values.
	 *
	 * @param   array   $rows  Accumulator.
	 * @param   object  $row   Category item row.
	 *
	 * @return  void
	 *
	 * @since  0.1.0
	 */
	private function appendPriceValues(array &$rows, object $row): void
	{
		foreach ($this->decodeJsonObject((string) $row->filter_prices) as $currency => $price)
		{
			if (!is_array($price) || empty($price['max']) || (float) $price['max'] <= 0)
			{
				continue;
			}

			$rows[] = [
				(int) $row->category_id,
				(int) $row->item_id,
				(string) $currency,
				!empty($price['min']) ? (float) $price['min'] : 0,
				(float) $price['max'],
				(string) $row->language,
				(int) $row->in_stock,
			];
		}
	}

	/**
	 * Builds an index value row.
	 *
	 * @param   object  $row        Category item row.
	 * @param   string  $fieldName  Field name.
	 * @param   string  $value      Field value.
	 *
	 * @return  array
	 *
	 * @since  0.1.0
	 */
	private function buildValueRow(object $row, string $fieldName, string $value): array
	{
		return [
			(int) $row->category_id,
			(int) $row->item_id,
			$fieldName,
			$value,
			sha1($value),
			(string) $row->language,
			(int) $row->in_stock,
		];
	}

	/**
	 * Inserts rows in chunks.
	 *
	 * @param   string  $table    Table name.
	 * @param   array   $columns  Column names.
	 * @param   array   $rows     Rows to insert.
	 *
	 * @return  void
	 *
	 * @since  0.1.0
	 */
	private function insertRows(string $table, array $columns, array $rows): void
	{
		if (count($rows) === 0)
		{
			return;
		}

		$db = $this->getDatabase();
		foreach (array_chunk($rows, 500) as $chunk)
		{
			$query = $db->getQuery(true)
				->insert($db->quoteName($table))
				->columns($db->quoteName($columns));

			foreach ($chunk as $row)
			{
				$quoted = [];
				foreach ($row as $value)
				{
					$quoted[] = is_int($value) || is_float($value) ? (string) $value : $db->quote((string) $value);
				}

				$query->values(implode(',', $quoted));
			}

			$db->setQuery($query)->execute();
		}
	}

	/**
	 * Normalizes scalar and array values into string list.
	 *
	 * @param   mixed  $value  Raw value.
	 *
	 * @return  array
	 *
	 * @since  0.1.0
	 */
	private function normalizeValues(mixed $value): array
	{
		if (is_array($value))
		{
			$result = [];
			foreach ($value as $item)
			{
				$result = array_merge($result, $this->normalizeValues($item));
			}

			return array_values(array_unique($result));
		}

		$value = trim((string) $value);

		return $value !== '' ? [$value] : [];
	}

	/**
	 * Decodes JSON object.
	 *
	 * @param   string  $json  JSON string.
	 *
	 * @return  array
	 *
	 * @since  0.1.0
	 */
	private function decodeJsonObject(string $json): array
	{
		if ($json === '')
		{
			return [];
		}

		$value = json_decode($json, true);

		return is_array($value) ? $value : [];
	}
}
