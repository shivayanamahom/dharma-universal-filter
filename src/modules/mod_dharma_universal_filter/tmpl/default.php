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

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/**
 * @var  Form      $form
 * @var  string    $action
 * @var  object    $module
 * @var  Registry  $params
 */

if (!$form)
{
	return;
}

$normalizeConfig = static function (mixed $value): array {
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

		if (is_array($row) && !empty($row['field']))
		{
			$result[] = $row;
		}
	}

	return $result;
};

$fieldsConfig  = $normalizeConfig($params->get('fields_config', []));
$offcanvasMode = (string) $params->get('offcanvas_mode', 'mobile');
$offcanvasMode = in_array($offcanvasMode, ['never', 'mobile', 'always'], true) ? $offcanvasMode : 'mobile';
$showOffcanvas = $offcanvasMode !== 'never';
$showInline    = $offcanvasMode !== 'always';

$assets = Factory::getApplication()->getDocument()->getWebAssetManager();
$assets->useScript('bootstrap.collapse');
if ($showOffcanvas)
{
	$assets->useScript('bootstrap.offcanvas');
}

$onSubmit = '';
if ($params->get('ajax', 0))
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

	$onSubmit = 'window.DharmaUniversalFilter?.ajaxSubmit(event);';
}

$availableFields = [];
foreach ($form->getFieldsets() as $key => $fieldset)
{
	foreach ($form->getFieldset($key) as $field)
	{
		$availableFields[$field->fieldname] = $field;
	}
}

$renderFields = [];
if (count($fieldsConfig) > 0)
{
	foreach ($fieldsConfig as $row)
	{
		$fieldName = (string) $row['field'];
		if (($row['show'] ?? '1') !== '1' || empty($availableFields[$fieldName]))
		{
			continue;
		}

		$renderFields[] = [
			'field'    => $availableFields[$fieldName],
			'config'   => $row,
			'expanded' => ($row['expanded'] ?? '1') === '1',
		];
	}
}
else
{
	foreach ($availableFields as $field)
	{
		$renderFields[] = [
			'field'    => $field,
			'config'   => [],
			'expanded' => null,
		];
	}
}

$activeCount = 0;
foreach ($availableFields as $field)
{
	$value = $form->getValue($field->fieldname, $field->group);
	if (is_array($value))
	{
		$activeCount += count(array_filter($value, static fn($item): bool => (string) $item !== ''));
	}
	elseif (!empty($value) && $value !== '')
	{
		$activeCount++;
	}
}

$formId = 'mod_dharma_universal_filter_' . $module->id;
$offcanvasId = $formId . '_offcanvas';
$stickyEnabled = (int) $params->get('sticky_filter', 0) === 1;
$stickyOffset = max(0, (int) $params->get('sticky_offset', 0));
$stickyClass = $stickyEnabled ? ' duf-filter-sticky' : '';
$stickyAttrs = $stickyEnabled ? ' style="top: ' . $stickyOffset . 'px;" data-duf-sticky-top="' . $stickyOffset . '"' : '';

$renderFilterForm = static function (string $suffix, string $formClass = '') use ($action, $form, $formId, $module, $onSubmit, $params, $renderFields): void {
	?>
	<form action="<?php echo $action; ?>" method="get" onsubmit="<?php echo $onSubmit; ?>"
		  data-dharma-filter-ajax="<?php echo $formId . $suffix; ?>"
		  class="duf-vfilter <?php echo $formClass; ?>">
		<div class="accordion duf-vfilter__accordion" id="<?php echo $formId . $suffix; ?>_accordion">
			<?php
			$i = 0;
			foreach ($renderFields as $renderField):
				$field = $renderField['field'];
				$i++;

				$name  = $field->fieldname;
				$group = $field->group;
				$open  = (($renderField['expanded'] ?? ($i < 6 || $form->getValue($name, $group))) ? 'show' : '');
				$id    = $formId . $suffix . '_' . $field->id;

				$form->setFieldAttribute($name, 'id', $id, $group);
				if (!empty($renderField['config']['title']))
				{
					$form->setFieldAttribute($name, 'label', (string) $renderField['config']['title'], $group);
				}

				$layout = (string) ($renderField['config']['layout'] ?? '');
				if ($layout === '')
				{
					$type = strtolower((string) $form->getFieldAttribute($name, 'type', '', $group));
					$layout = match (true) {
						$name === 'price' && $group === 'filter' && $params->get('price_display', 'inputs') === 'slider' => 'modules.dharma_universal_filter.field.price_slider',
						$name === 'price' && $group === 'filter' => 'modules.dharma_universal_filter.field.price_inputs',
						str_contains($type, 'checkbox') => 'modules.dharma_universal_filter.field.checkboxes',
						$type === 'radio' => 'modules.dharma_universal_filter.field.radio',
						$type === 'list' => 'modules.dharma_universal_filter.field.select',
						default => 'modules.dharma_universal_filter.field.default',
					};
				}

				$labelId   = $id . '_label';
				$contentId = $id . '_content';
				?>
				<div class="accordion-item duf-vfilter__item">
					<div class="accordion-header" id="<?php echo $labelId; ?>">
						<button class="accordion-button duf-vfilter__button <?php echo $open ? '' : 'collapsed'; ?>" type="button"
								data-bs-toggle="collapse"
								data-bs-target="#<?php echo $contentId; ?>"
								aria-expanded="<?php echo $open ? 'true' : 'false'; ?>"
								aria-controls="<?php echo $contentId; ?>">
							<?php echo Text::_($form->getFieldAttribute($name, 'label', $name, $group)); ?>
						</button>
					</div>
					<div id="<?php echo $contentId; ?>" class="accordion-collapse collapse <?php echo $open; ?>"
						 aria-labelledby="<?php echo $labelId; ?>">
						<div class="accordion-body duf-vfilter__body">
							<?php echo LayoutHelper::render($layout, [
								'field'    => $field,
								'form'     => $form,
								'group'    => $group,
								'name'     => $name,
								'module'   => $module,
								'params'   => $params,
								'onSubmit' => $onSubmit,
								'config'   => $renderField['config'],
							]); ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php if ((int) $params->get('show_search_button', 1) === 1 || (int) $params->get('show_reset_button', 1) === 1): ?>
			<div class="duf-vfilter__actions">
				<?php if ((int) $params->get('show_search_button', 1) === 1): ?>
					<button type="submit" class="duf-hfilter__btn duf-hfilter__btn--primary duf-vfilter__btn">
						<?php echo Text::_('JGLOBAL_FILTER_BUTTON'); ?>
					</button>
				<?php endif; ?>
				<?php if ((int) $params->get('show_reset_button', 1) === 1): ?>
					<a href="<?php echo $action; ?>" class="duf-hfilter__btn duf-hfilter__btn--reset duf-vfilter__btn">
						<?php echo Text::_('MOD_DHARMA_UNIVERSAL_FILTER_RESET_BUTTON'); ?>
					</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</form>
	<?php
};

$buttonClass = $offcanvasMode === 'mobile' ? 'd-lg-none mb-2' : 'mb-2';
$offcanvasClass = $offcanvasMode === 'mobile'
	? 'offcanvas offcanvas-start duf-offcanvas d-lg-none'
	: 'offcanvas offcanvas-start duf-offcanvas';
$inlineClass = $offcanvasMode === 'mobile' ? 'd-none d-lg-flex' : '';

static $ajaxScriptLoaded = false;
static $filterStyleLoaded = false;
$filterStyleVersion = is_file(JPATH_ROOT . '/media/mod_dharma_universal_filter/css/filter.css')
	? (string) filemtime(JPATH_ROOT . '/media/mod_dharma_universal_filter/css/filter.css')
	: '1';
$ajaxScriptVersion = is_file(JPATH_ROOT . '/media/mod_dharma_universal_filter/js/ajax.min.js')
	? (string) filemtime(JPATH_ROOT . '/media/mod_dharma_universal_filter/js/ajax.min.js')
	: '1';
?>
<?php if (!$filterStyleLoaded): ?>
	<link rel="stylesheet" href="<?php echo Uri::root(true); ?>/media/mod_dharma_universal_filter/css/filter.css?v=<?php echo $filterStyleVersion; ?>">
	<?php $filterStyleLoaded = true; ?>
<?php endif; ?>
<?php if ($params->get('ajax', 0) && !$ajaxScriptLoaded): ?>
	<script src="<?php echo Uri::root(true); ?>/media/mod_dharma_universal_filter/js/ajax.min.js?v=<?php echo $ajaxScriptVersion; ?>"></script>
	<?php $ajaxScriptLoaded = true; ?>
<?php endif; ?>
<?php if ($stickyEnabled): ?>
	<script>
		(function () {
			if (window.DharmaUniversalFilterSticky) return;
			window.DharmaUniversalFilterSticky = true;
			var states = new Map();
			function reset(el, state) {
				el.classList.remove('duf-filter-sticky--fixed');
				el.style.left = '';
				el.style.width = '';
				if (state.placeholder) state.placeholder.hidden = true;
			}
			function ensure(el) {
				if (states.has(el)) return states.get(el);
				var placeholder = document.createElement('div');
				placeholder.hidden = true;
				el.parentNode.insertBefore(placeholder, el);
				var state = {placeholder: placeholder, start: null};
				states.set(el, state);
				return state;
			}
			function update() {
				document.querySelectorAll('.duf-filter-sticky').forEach(function (el) {
					var state = ensure(el);
					if (getComputedStyle(el).display === 'none') {
						reset(el, state);
						state.start = null;
						return;
					}
					var top = parseInt(el.dataset.dufStickyTop || '0', 10) || 0;
					var anchor = state.placeholder.hidden ? el : state.placeholder;
					var rect = anchor.getBoundingClientRect();
					var pageTop = window.scrollY + rect.top;
					if (state.start === null) state.start = pageTop - top;
					if (window.scrollY > state.start) {
						state.placeholder.hidden = false;
						state.placeholder.style.height = el.offsetHeight + 'px';
						state.placeholder.style.width = rect.width + 'px';
						el.classList.add('duf-filter-sticky--fixed');
						el.style.top = top + 'px';
						el.style.left = rect.left + 'px';
						el.style.width = rect.width + 'px';
					} else {
						reset(el, state);
					}
				});
			}
			window.addEventListener('scroll', update, {passive: true});
			window.addEventListener('resize', function () {
				states.forEach(function (state, el) {
					reset(el, state);
					state.start = null;
				});
				update();
			});
			document.addEventListener('DOMContentLoaded', update);
			update();
		})();
	</script>
<?php endif; ?>

<?php if ($showOffcanvas): ?>
	<div class="<?php echo $buttonClass . $stickyClass; ?>"<?php echo $stickyAttrs; ?>>
		<button class="duf-filter-toggle" type="button" data-bs-toggle="offcanvas"
				data-bs-target="#<?php echo $offcanvasId; ?>" aria-controls="<?php echo $offcanvasId; ?>">
			<span><?php echo Text::_('MOD_DHARMA_UNIVERSAL_FILTER_BUTTON'); ?></span>
			<?php if ($activeCount > 0): ?>
				<span class="duf-filter-toggle__badge"><?php echo $activeCount; ?></span>
			<?php endif; ?>
		</button>
	</div>
	<div class="<?php echo $offcanvasClass; ?>" tabindex="-1" id="<?php echo $offcanvasId; ?>"
		 aria-labelledby="<?php echo $offcanvasId; ?>_label">
		<div class="offcanvas-header">
			<div class="duf-offcanvas__title" id="<?php echo $offcanvasId; ?>_label">
				<?php echo Text::_('MOD_DHARMA_UNIVERSAL_FILTER_BUTTON'); ?>
				<?php if ($activeCount > 0): ?>
					<span class="duf-filter-toggle__badge"><?php echo $activeCount; ?></span>
				<?php endif; ?>
			</div>
			<button type="button" class="btn-close" data-bs-dismiss="offcanvas"
					aria-label="<?php echo Text::_('MOD_DHARMA_UNIVERSAL_FILTER_CLOSE'); ?>"></button>
		</div>
		<div class="offcanvas-body">
			<?php $renderFilterForm('_mobile'); ?>
		</div>
	</div>
<?php endif; ?>

<?php if ($showInline): ?>
	<div class="<?php echo trim($inlineClass . $stickyClass); ?>"<?php echo $stickyAttrs; ?>>
		<?php $renderFilterForm(''); ?>
	</div>
<?php endif; ?>
