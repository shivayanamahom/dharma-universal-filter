<?php
/*
 * @package     Dharma Universal Filter Task Plugin
 * @subpackage  plg_task_dharma_universal_filter
 * @version     0.2.0
 * @author      Dharma Design
 * @copyright   Copyright (c) 2026 Dharma Design. All rights reserved.
 * @license     GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 * @link
 */

namespace Joomla\Plugin\Task\DharmaUniversalFilter\Extension;

\defined('_JEXEC') or die;

use Dharma\UniversalFilter\Indexer;
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
			'onTaskOptionsList'    => 'advertiseRoutines',
			'onExecuteTask'        => 'standardRoutineHandler',
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
			[$valueRows, $priceRows] = (new Indexer($this->getDatabase()))->rebuildAll();

			$this->safeLogTask(
				sprintf('Dharma Universal Filter index rebuilt: %d value rows, %d price rows.', $valueRows, $priceRows),
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
}
