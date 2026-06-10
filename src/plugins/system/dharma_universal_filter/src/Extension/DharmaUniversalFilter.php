<?php
/*
 * @package     Dharma Universal Filter System Plugin
 * @subpackage  plg_system_dharma_universal_filter
 * @version     0.2.0
 * @author      Dharma Design
 * @copyright   Copyright (c) 2026 Dharma Design. All rights reserved.
 * @license     GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 * @link
 */

namespace Joomla\Plugin\System\DharmaUniversalFilter\Extension;

\defined('_JEXEC') or die;

use Dharma\UniversalFilter\Indexer;
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

	private const COMMAND_BATCH_SIZE = 10;

	private ?Indexer $indexer = null;

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
			$this->getIndexer()->reindexProduct($id);
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
				$this->getIndexer()->truncateIndexes();
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
			$pks  = $this->getNextIndexedProductIds($last, $productIds, self::COMMAND_BATCH_SIZE);
			if (count($pks) === 0)
			{
				$data['action'] = 'next';

				return $data;
			}

			$data['index_last'] = end($pks);
			$data['index_progress'] = min(
				(int) ($data['index_total'] ?? 0),
				(int) ($data['index_progress'] ?? 0) + count($pks)
			);

			$this->getIndexer()->reindexProducts($pks, count($productIds) > 0);

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

	private function getIndexer(): Indexer
	{
		return $this->indexer ??= new Indexer($this->getDatabase());
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
		$this->getIndexer()->ensureTables();

		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__radicalmart_products'))
			->where($db->quoteName('state') . ' = 1');

		if (count($productIds) > 0)
		{
			$query->whereIn($db->quoteName('id'), $productIds, ParameterType::INTEGER);
		}

		return (int) $db->setQuery($query)->loadResult();
	}

	private function getNextIndexedProductIds(int $last, array $productIds = [], int $limit = self::COMMAND_BATCH_SIZE): array
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__radicalmart_products'))
			->where($db->quoteName('state') . ' = 1')
			->where($db->quoteName('id') . ' > :last')
			->order($db->quoteName('id'))
			->bind(':last', $last, ParameterType::INTEGER);

		if (count($productIds) > 0)
		{
			$query->whereIn($db->quoteName('id'), $productIds, ParameterType::INTEGER);
		}

		return array_map('intval', $db->setQuery($query, 0, max(1, $limit))->loadColumn());
	}
}
