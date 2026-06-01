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

namespace Joomla\Plugin\System\DharmaUniversalFilter\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Event\Model\AfterSaveEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use Joomla\Input\Input;

final class DharmaUniversalFilter extends CMSPlugin implements SubscriberInterface
{
	use DatabaseAwareTrait;

	protected $autoloadLanguage = true;

	public static function getSubscribedEvents(): array
	{
		return [
			'onContentAfterSave'                    => 'onContentAfterSave',
			'onRadicalMartGetAdministratorCommands' => 'onRadicalMartGetAdministratorCommands',
		];
	}

	public function onContentAfterSave(AfterSaveEvent $event): void
	{
		if ($event->getContext() !== 'com_radicalmart.product')
		{
			return;
		}

		$item = $event->getItem();
		$id   = (int) ($item->id ?? 0);
		if ($id <= 0)
		{
			return;
		}

		try
		{
			$this->reindexProduct($id);
		}
		catch (\Throwable)
		{
			// Product saving must not fail because an auxiliary filter index update failed.
		}
	}

	public function onRadicalMartGetAdministratorCommands(?string $context = null, ?array &$commands = []): void
	{
		if (!in_array($context, ['com_radicalmart.product', 'com_radicalmart.products', 'com_radicalmart.commands'], true))
		{
			return;
		}

		$commands[] = [
			'command' => 'dharma:universal-filter:reindex',
			'text'    => 'PLG_SYSTEM_DHARMA_UNIVERSAL_FILTER_COMMAND_REINDEX',
			'all'     => $context === 'com_radicalmart.products',
			'method'  => [$this, 'commandReindex'],
		];
	}

	public function commandReindex(string $task, Input $input, array $data = []): array|bool
	{
		if ($task === 'load')
		{
			$data['groups'] = [
				'index' => [
					'title' => Text::_('PLG_SYSTEM_DHARMA_UNIVERSAL_FILTER_COMMAND_GROUP'),
					'tasks' => [
						'total'   => [
							'title'  => Text::_('PLG_SYSTEM_DHARMA_UNIVERSAL_FILTER_COMMAND_TOTAL'),
							'render' => $this->getProgressBar(),
						],
						'reindex' => [
							'title'  => Text::_('PLG_SYSTEM_DHARMA_UNIVERSAL_FILTER_COMMAND_REINDEX_ITEMS'),
							'render' => $this->getProgressBar(),
						],
					],
				],
			];

			return $data;
		}

		$productIds = $this->getCommandProductIds($input);

		if ($task === 'total')
		{
			$total = $this->getCommandProductsTotal($productIds);
			if (count($productIds) === 0)
			{
				$this->truncateIndexes();
			}

			$data['index_total']    = $total;
			$data['index_last']     = 0;
			$data['index_progress'] = 0;

			$data['groups']['index']['tasks']['total']['render']   = $this->getProgressBar(1);
			$data['groups']['index']['tasks']['reindex']['render'] = $this->getProgressBar(0, $total);
			$data['action'] = $total > 0 ? 'next' : 'break';

			return $data;
		}

		if ($task === 'reindex')
		{
			$last = (int) ($data['index_last'] ?? 0);
			$pk   = $this->getNextIndexedProductId($last, $productIds);
			if ($pk <= 0 || $pk === $last)
			{
				$data['action'] = 'next';

				return $data;
			}

			$data['index_last'] = $pk;
			$data['index_progress'] = (int) ($data['index_progress'] ?? 0) + 1;

			$this->reindexProduct($pk, count($productIds) > 0);

			$data['groups']['index']['tasks']['reindex']['render'] = $this->getProgressBar(
				$data['index_progress'],
				(int) ($data['index_total'] ?? 1)
			);
			$data['action'] = $data['index_progress'] >= (int) ($data['index_total'] ?? 0) ? 'next' : 'repeat';

			return $data;
		}

		if ($task === 'finish')
		{
			return [
				'task'    => $input->get('context') === 'com_radicalmart.products' ? 'window.reload' : 'close',
				'message' => Text::_('PLG_SYSTEM_DHARMA_UNIVERSAL_FILTER_COMMAND_SUCCESS'),
			];
		}

		return false;
	}

	private function getProgressBar(int $current = 0, int $total = 1): string
	{
		if ($total <= 0)
		{
			$total = 1;
		}

		$percent = min(100, round(($current / $total) * 100));

		return '<div class="progress">'
			. '<div class="progress-bar" role="progressbar" style="width: ' . $percent . '%;" aria-valuenow="' . $percent . '" aria-valuemin="0" aria-valuemax="100">'
			. $current . ' / ' . $total
			. '</div></div>';
	}

	private function getCommandProductIds(Input $input): array
	{
		if ($input->getInt('all', 0) === 1)
		{
			return [];
		}

		$ids = $input->get('cid', [], 'array');
		if (count($ids) === 0 && $input->getInt('item_pk', 0) > 0)
		{
			$ids = [$input->getInt('item_pk')];
		}

		if (count($ids) === 0)
		{
			$form = $input->get('jform', [], 'array');
			if (!empty($form['id']))
			{
				$ids = [(int) $form['id']];
			}
		}

		return array_values(array_unique(array_filter(array_map('intval', $ids))));
	}

	private function getCommandProductsTotal(array $productIds = []): int
	{
		$this->ensureTables();

		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select('COUNT(DISTINCT ' . $db->quoteName('item_id') . ')')
			->from($db->quoteName('#__radicalmart_categories_items'))
			->where($db->quoteName('state') . ' = 1');

		if (count($productIds) > 0)
		{
			$query->whereIn($db->quoteName('item_id'), $productIds, ParameterType::INTEGER);
		}

		return (int) $db->setQuery($query)->loadResult();
	}

	private function getNextIndexedProductId(int $last, array $productIds = []): int
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select('DISTINCT ' . $db->quoteName('item_id'))
			->from($db->quoteName('#__radicalmart_categories_items'))
			->where($db->quoteName('state') . ' = 1')
			->where($db->quoteName('item_id') . ' > :last')
			->order($db->quoteName('item_id'))
			->bind(':last', $last, ParameterType::INTEGER);

		if (count($productIds) > 0)
		{
			$query->whereIn($db->quoteName('item_id'), $productIds, ParameterType::INTEGER);
		}

		return (int) $db->setQuery($query, 0, 1)->loadResult();
	}

	private function reindexProduct(int $productId, bool $deleteExisting = true): void
	{
		$this->ensureTables();

		if ($deleteExisting)
		{
			$this->deleteProductIndex($productId);
		}

		$db = $this->getDatabase();
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
			->where($db->quoteName('state') . ' = 1')
			->where($db->quoteName('item_id') . ' = :item_id')
			->bind(':item_id', $productId, ParameterType::INTEGER);

		$valueRows = [];
		$priceRows = [];
		foreach ($db->setQuery($query)->loadObjectList() as $row)
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
	}

	private function deleteProductIndex(int $productId): void
	{
		$db = $this->getDatabase();
		foreach (['#__dharma_universal_filter_index', '#__dharma_universal_filter_price_index'] as $table)
		{
			$query = $db->getQuery(true)
				->delete($db->quoteName($table))
				->where($db->quoteName('item_id') . ' = :item_id')
				->bind(':item_id', $productId, ParameterType::INTEGER);

			$db->setQuery($query)->execute();
		}
	}

	private function truncateIndexes(): void
	{
		$this->ensureTables();

		$db = $this->getDatabase();
		$db->truncateTable('#__dharma_universal_filter_index');
		$db->truncateTable('#__dharma_universal_filter_price_index');
	}

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
