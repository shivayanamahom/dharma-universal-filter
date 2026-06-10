<?php
/*
 * @package     Dharma Universal Filter Library
 * @subpackage  lib_dharma_universal_filter
 * @version     0.2.0
 * @author      Dharma Design
 * @copyright   Copyright (c) 2026 Dharma Design. All rights reserved.
 * @license     GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 * @link
 */

namespace Dharma\UniversalFilter;

\defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Shared products filter indexer.
 *
 * Single source of truth for building the Dharma Universal Filter index tables.
 * Used by both the system plugin (live, per-product reindex) and the task plugin
 * (scheduled full rebuild), so the indexing logic can never drift between them.
 *
 * @since  0.2.0
 */
class Indexer
{
	/**
	 * Field value index table.
	 *
	 * @since  0.2.0
	 */
	public const INDEX_TABLE = '#__dharma_universal_filter_index';

	/**
	 * Price index table.
	 *
	 * @since  0.2.0
	 */
	public const PRICE_TABLE = '#__dharma_universal_filter_price_index';

	/**
	 * Cache group used by the module read path. Must be cleared whenever the index changes.
	 *
	 * @since  0.2.0
	 */
	public const CACHE_GROUP = 'mod_dharma_universal_filter';

	/**
	 * Number of products loaded from the database per batch.
	 *
	 * @since  0.2.0
	 */
	private const PRODUCT_FETCH_CHUNK = 200;

	/**
	 * Number of value rows inserted per query.
	 *
	 * @since  0.2.0
	 */
	private const INSERT_CHUNK = 500;

	/**
	 * Database driver.
	 *
	 * @var  DatabaseInterface
	 *
	 * @since  0.2.0
	 */
	private DatabaseInterface $db;

	/**
	 * Cached filterable field aliases.
	 *
	 * @var  array|null
	 *
	 * @since  0.2.0
	 */
	private ?array $filterableFieldAliases = null;

	/**
	 * Constructor.
	 *
	 * @param   DatabaseInterface|null  $db  Database driver. Resolved from the container when omitted.
	 *
	 * @since  0.2.0
	 */
	public function __construct(?DatabaseInterface $db = null)
	{
		$this->db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);
	}

	/**
	 * Creates the index tables when missing.
	 *
	 * @return  void
	 *
	 * @since  0.2.0
	 */
	public function ensureTables(): void
	{
		$file = \dirname(__DIR__) . '/sql/install.mysql.utf8.sql';
		$sql  = is_file($file) ? (string) file_get_contents($file) : '';
		if ($sql === '')
		{
			return;
		}

		foreach ($this->db->splitSql($sql) as $statement)
		{
			$statement = trim($statement);
			if ($statement !== '')
			{
				$this->db->setQuery($statement)->execute();
			}
		}
	}

	/**
	 * Reindexes a single product.
	 *
	 * @param   int   $productId       Product id.
	 * @param   bool  $deleteExisting  Whether to remove existing index rows first.
	 *
	 * @return  void
	 *
	 * @since  0.2.0
	 */
	public function reindexProduct(int $productId, bool $deleteExisting = true): void
	{
		$this->reindexProducts([$productId], $deleteExisting);
	}

	/**
	 * Reindexes a set of products inside a single transaction.
	 *
	 * @param   array  $productIds      Product ids.
	 * @param   bool   $deleteExisting  Whether to remove existing index rows first.
	 *
	 * @return  void
	 *
	 * @since  0.2.0
	 */
	public function reindexProducts(array $productIds, bool $deleteExisting = true): void
	{
		$this->ensureTables();

		$productIds = $this->normalizeIds($productIds);
		if (count($productIds) === 0)
		{
			return;
		}

		$valueRows = [];
		$priceRows = [];
		foreach (array_chunk($productIds, self::PRODUCT_FETCH_CHUNK) as $chunk)
		{
			foreach ($this->loadProducts($chunk) as $product)
			{
				$this->appendProductIndexRows($product, $valueRows, $priceRows);
			}
		}

		$this->db->transactionStart();

		try
		{
			if ($deleteExisting)
			{
				$this->deleteProductsIndex($productIds);
			}

			$this->insertRows(self::INDEX_TABLE, $this->valueColumns(), $valueRows);
			$this->insertRows(self::PRICE_TABLE, $this->priceColumns(), $priceRows);

			$this->db->transactionCommit();
		}
		catch (\Throwable $exception)
		{
			$this->db->transactionRollback();

			throw $exception;
		}

		$this->cleanCache();
	}

	/**
	 * Rebuilds the whole index from scratch.
	 *
	 * @return  array  [valueRowCount, priceRowCount]
	 *
	 * @since  0.2.0
	 */
	public function rebuildAll(): array
	{
		$this->ensureTables();
		$this->truncateIndexes();

		$valueTotal = 0;
		$priceTotal = 0;

		foreach ($this->getAllProductIdBatches(self::PRODUCT_FETCH_CHUNK) as $chunk)
		{
			$valueRows = [];
			$priceRows = [];
			foreach ($this->loadProducts($chunk) as $product)
			{
				$this->appendProductIndexRows($product, $valueRows, $priceRows);
			}

			$this->db->transactionStart();

			try
			{
				$this->insertRows(self::INDEX_TABLE, $this->valueColumns(), $valueRows);
				$this->insertRows(self::PRICE_TABLE, $this->priceColumns(), $priceRows);

				$this->db->transactionCommit();
			}
			catch (\Throwable $exception)
			{
				$this->db->transactionRollback();

				throw $exception;
			}

			$valueTotal += count($valueRows);
			$priceTotal += count($priceRows);
		}

		$this->cleanCache();

		return [$valueTotal, $priceTotal];
	}

	/**
	 * Removes index rows for the given products.
	 *
	 * @param   array  $productIds  Product ids.
	 *
	 * @return  void
	 *
	 * @since  0.2.0
	 */
	public function deleteProductsIndex(array $productIds): void
	{
		$productIds = $this->normalizeIds($productIds);
		if (count($productIds) === 0)
		{
			return;
		}

		foreach ([self::INDEX_TABLE, self::PRICE_TABLE] as $table)
		{
			$query = $this->db->getQuery(true)
				->delete($this->db->quoteName($table))
				->whereIn($this->db->quoteName('item_id'), $productIds, ParameterType::INTEGER);

			$this->db->setQuery($query)->execute();
		}
	}

	/**
	 * Empties both index tables.
	 *
	 * @return  void
	 *
	 * @since  0.2.0
	 */
	public function truncateIndexes(): void
	{
		$this->ensureTables();
		$this->db->truncateTable(self::INDEX_TABLE);
		$this->db->truncateTable(self::PRICE_TABLE);
	}

	/**
	 * Clears the module read cache so freshly indexed data becomes visible immediately.
	 *
	 * @return  void
	 *
	 * @since  0.2.0
	 */
	public function cleanCache(): void
	{
		try
		{
			$cache = Factory::getContainer()
				->get(CacheControllerFactoryInterface::class)
				->createCacheController('callback', ['defaultgroup' => self::CACHE_GROUP]);

			$cache->clean(self::CACHE_GROUP);
		}
		catch (\Throwable)
		{
			// Cache cleaning is best-effort; stale cache must never break an index write.
		}
	}

	/**
	 * Column list for the value index table.
	 *
	 * @return  array
	 *
	 * @since  0.2.0
	 */
	private function valueColumns(): array
	{
		return ['category_id', 'item_id', 'field_name', 'field_value', 'field_value_hash', 'language', 'in_stock'];
	}

	/**
	 * Column list for the price index table.
	 *
	 * @return  array
	 *
	 * @since  0.2.0
	 */
	private function priceColumns(): array
	{
		return ['category_id', 'item_id', 'currency', 'price_min', 'price_max', 'language', 'in_stock'];
	}

	/**
	 * Yields published product id batches.
	 *
	 * @param   int  $size  Batch size.
	 *
	 * @return  \Generator
	 *
	 * @since  0.2.0
	 */
	private function getAllProductIdBatches(int $size): \Generator
	{
		$last = 0;

		do
		{
			$query = $this->db->getQuery(true)
				->select($this->db->quoteName('id'))
				->from($this->db->quoteName('#__radicalmart_products'))
				->where($this->db->quoteName('state') . ' = 1')
				->where($this->db->quoteName('id') . ' > :last')
				->order($this->db->quoteName('id') . ' ASC')
				->bind(':last', $last, ParameterType::INTEGER);

			$ids = array_map('intval', $this->db->setQuery($query, 0, $size)->loadColumn());
			if (count($ids) === 0)
			{
				break;
			}

			$last = (int) end($ids);

			yield $ids;
		}
		while (count($ids) === $size);
	}

	/**
	 * Loads product rows for the given ids in a single query.
	 *
	 * @param   array  $productIds  Product ids.
	 *
	 * @return  object[]
	 *
	 * @since  0.2.0
	 */
	private function loadProducts(array $productIds): array
	{
		$productIds = $this->normalizeIds($productIds);
		if (count($productIds) === 0)
		{
			return [];
		}

		$query = $this->db->getQuery(true)
			->select([
				'p.' . $this->db->quoteName('id'),
				'p.' . $this->db->quoteName('categories_all'),
				'p.' . $this->db->quoteName('prices'),
				'p.' . $this->db->quoteName('fields'),
				'p.' . $this->db->quoteName('in_stock'),
				'p.' . $this->db->quoteName('language'),
			])
			->from($this->db->quoteName('#__radicalmart_products', 'p'))
			->join('INNER', $this->db->quoteName('#__radicalmart_categories', 'c') . ' ON c.id = p.category')
			->where($this->db->quoteName('p.state') . ' = 1')
			->where($this->db->quoteName('c.state') . ' = 1')
			->whereIn($this->db->quoteName('p.id'), $productIds, ParameterType::INTEGER);

		return $this->db->setQuery($query)->loadObjectList() ?: [];
	}

	/**
	 * Builds index rows for one product.
	 *
	 * @param   object  $product    Product row.
	 * @param   array   $valueRows  Value rows accumulator.
	 * @param   array   $priceRows  Price rows accumulator.
	 *
	 * @return  void
	 *
	 * @since  0.2.0
	 */
	private function appendProductIndexRows(object $product, array &$valueRows, array &$priceRows): void
	{
		$categories = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $product->categories_all)))));
		if (count($categories) === 0)
		{
			return;
		}
		$indexCategories = array_values(array_unique(array_merge([0], $categories)));

		$fields = (new Registry((string) $product->fields))->toArray();
		$fields['com_radicalmart_state']    = 1;
		$fields['com_radicalmart_in_stock'] = (int) $product->in_stock;

		$filterPrices = [];
		foreach ((new Registry((string) $product->prices))->toArray() as $currency => $price)
		{
			if (!is_array($price))
			{
				continue;
			}

			$final = (float) ($price['final'] ?? 0);
			$base  = (float) ($price['base'] ?? $final);
			$filterPrices[(string) $currency] = ['min' => $final, 'max' => $base];
		}

		foreach ($indexCategories as $categoryId)
		{
			$row = (object) [
				'category_id'       => $categoryId,
				'item_id'           => (int) $product->id,
				'in_stock'          => (int) $product->in_stock,
				'language'          => (string) $product->language,
				'filter_categories' => implode(',', $categories),
				'filter_prices'     => (new Registry($filterPrices))->toString(),
				'filter_fields'     => (new Registry(['p' . (int) $product->id => $fields]))->toString(),
			];

			$this->appendCategoryValues($valueRows, $row);
			$this->appendFieldValues($valueRows, $row);
			$this->appendPriceValues($priceRows, $row);
		}
	}

	/**
	 * Appends category/manufacturer/badge and stock values.
	 *
	 * @param   array   $rows  Accumulator.
	 * @param   object  $row   Category item row.
	 *
	 * @return  void
	 *
	 * @since  0.2.0
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
	 * Appends product custom field values.
	 *
	 * @param   array   $rows  Accumulator.
	 * @param   object  $row   Category item row.
	 *
	 * @return  void
	 *
	 * @since  0.2.0
	 */
	private function appendFieldValues(array &$rows, object $row): void
	{
		$filterableAliases = $this->getFilterableFieldAliases();

		foreach ($this->decodeJsonObject((string) $row->filter_fields) as $productFields)
		{
			if (!is_array($productFields))
			{
				continue;
			}

			foreach ($productFields as $alias => $value)
			{
				if (!isset($filterableAliases[(string) $alias]))
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
	 * Returns the aliases of RadicalMart fields that are exposed in the filter.
	 *
	 * @return  array
	 *
	 * @since  0.2.0
	 */
	private function getFilterableFieldAliases(): array
	{
		if ($this->filterableFieldAliases !== null)
		{
			return $this->filterableFieldAliases;
		}

		$query = $this->db->getQuery(true)
			->select($this->db->quoteName(['alias', 'plugin', 'params']))
			->from($this->db->quoteName('#__radicalmart_fields'))
			->where($this->db->quoteName('state') . ' = 1')
			->where($this->db->quoteName('area') . ' = ' . $this->db->quote('products'));

		$this->filterableFieldAliases = [];
		foreach ($this->db->setQuery($query)->loadObjectList() as $field)
		{
			$params = new Registry((string) $field->params);
			if ((string) $field->plugin !== 'standard'
				|| (int) $params->get('display_filter', 0) !== 1
				|| !in_array((string) $params->get('type'), ['list', 'checkboxes'], true))
			{
				continue;
			}

			$this->filterableFieldAliases[(string) $field->alias] = true;
		}

		return $this->filterableFieldAliases;
	}

	/**
	 * Appends price index values.
	 *
	 * @param   array   $rows  Accumulator.
	 * @param   object  $row   Category item row.
	 *
	 * @return  void
	 *
	 * @since  0.2.0
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
	 * Builds a value index row.
	 *
	 * @param   object  $row        Category item row.
	 * @param   string  $fieldName  Field name.
	 * @param   string  $value      Field value.
	 *
	 * @return  array
	 *
	 * @since  0.2.0
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
	 * @since  0.2.0
	 */
	private function insertRows(string $table, array $columns, array $rows): void
	{
		if (count($rows) === 0)
		{
			return;
		}

		foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk)
		{
			$query = $this->db->getQuery(true)
				->insert($this->db->quoteName($table))
				->columns($this->db->quoteName($columns));

			foreach ($chunk as $row)
			{
				$quoted = [];
				foreach ($row as $value)
				{
					$quoted[] = is_int($value) || is_float($value) ? (string) $value : $this->db->quote((string) $value);
				}

				$query->values(implode(',', $quoted));
			}

			$this->db->setQuery($query)->execute();
		}
	}

	/**
	 * Normalizes a value into a flat string list.
	 *
	 * @param   mixed  $value  Raw value.
	 *
	 * @return  array
	 *
	 * @since  0.2.0
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
	 * Decodes a JSON object into an array.
	 *
	 * @param   string  $json  JSON string.
	 *
	 * @return  array
	 *
	 * @since  0.2.0
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

	/**
	 * Normalizes a list of ids into a unique positive integer list.
	 *
	 * @param   array  $ids  Raw ids.
	 *
	 * @return  int[]
	 *
	 * @since  0.2.0
	 */
	private function normalizeIds(array $ids): array
	{
		return array_values(array_unique(array_filter(array_map('intval', $ids))));
	}
}
