<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

if (!$form) return;

$normalizeConfig = static function (mixed $value): array {
  if ($value instanceof Registry) $value = $value->toArray();
  elseif (is_object($value))      $value = (array) $value;
  elseif (is_string($value) && $value !== '') {
    $decoded = json_decode($value, true);
    $value   = is_array($decoded) ? $decoded : [];
  }
  if (!is_array($value)) return [];
  $result = [];
  foreach ($value as $row) {
    if ($row instanceof Registry) $row = $row->toArray();
    elseif (is_object($row))      $row = (array) $row;
    if (!is_array($row) || empty($row['field'])) continue;
    $result[] = $row;
  }
  return $result;
};

$fieldsConfig = $normalizeConfig($params->get('fields_config', []));

$assets = Factory::getApplication()->getDocument()->getWebAssetManager();
$assets->useScript('bootstrap.collapse');
$assets->useScript('bootstrap.offcanvas');
if ($assets->assetExists('style', 'mod_dharma_universal_filter.filter')) {
  $assets->useStyle('mod_dharma_universal_filter.filter');
} else {
  $assets->registerAndUseStyle(
    'mod_dharma_universal_filter.filter',
    'media/mod_dharma_universal_filter/css/filter.css',
    ['version' => 'auto']
  );
}

$onSubmit = '';
if ($params->get('ajax', 0)) {
  static $ajaxScriptLoaded = false;
  if (!$ajaxScriptLoaded) {
    $assets->registerAndUseScript(
      'mod_dharma_universal_filter.ajax',
      'media/mod_dharma_universal_filter/js/ajax.min.js',
      ['version' => 'auto'],
      ['defer' => true, 'type' => 'module'],
      ['core']
    );
    $ajaxScriptLoaded = true;
  }
  $onSubmit = 'window.DharmaUniversalFilter?.ajaxSubmit(event);';
}

static $filterStyleLoaded = false;
$filterStyleVersion = is_file(JPATH_ROOT . '/media/mod_dharma_universal_filter/css/filter.css')
  ? (string) filemtime(JPATH_ROOT . '/media/mod_dharma_universal_filter/css/filter.css')
  : '1';
if (!$filterStyleLoaded):
  $filterStyleLoaded = true;
?>
  <link rel="stylesheet" href="<?php echo Uri::root(true); ?>/media/mod_dharma_universal_filter/css/filter.css?v=<?php echo $filterStyleVersion; ?>">
<?php endif; ?>
<?php
static $ajaxInlineScriptLoaded = false;
$ajaxScriptVersion = is_file(JPATH_ROOT . '/media/mod_dharma_universal_filter/js/ajax.min.js')
  ? (string) filemtime(JPATH_ROOT . '/media/mod_dharma_universal_filter/js/ajax.min.js')
  : '1';
$stickyEnabled = (int) $params->get('sticky_filter', 0) === 1;
$stickyOffset = max(0, (int) $params->get('sticky_offset', 0));
$stickyClass = $stickyEnabled ? ' duf-filter-sticky' : '';
$stickyAttrs = $stickyEnabled ? ' style="top: ' . $stickyOffset . 'px;" data-duf-sticky-top="' . $stickyOffset . '"' : '';
?>
<?php if ($params->get('ajax', 0) && !$ajaxInlineScriptLoaded): ?>
  <script src="<?php echo Uri::root(true); ?>/media/mod_dharma_universal_filter/js/ajax.min.js?v=<?php echo $ajaxScriptVersion; ?>"></script>
  <?php $ajaxInlineScriptLoaded = true; ?>
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
<?php

// ── Собираем поля ──────────────────────────────────────────
$availableFields = [];
foreach ($form->getFieldsets() as $key => $fieldset) {
  foreach ($form->getFieldset($key) as $field) {
    $availableFields[$field->fieldname] = $field;
  }
}

$renderFields = [];
if (count($fieldsConfig) > 0) {
  foreach ($fieldsConfig as $row) {
    $fieldName = (string) $row['field'];
    if (($row['show'] ?? '1') !== '1' || empty($availableFields[$fieldName])) continue;
    $renderFields[] = ['field' => $availableFields[$fieldName], 'config' => $row];
  }
} else {
  foreach ($availableFields as $field) {
    $renderFields[] = ['field' => $field, 'config' => []];
  }
}

// ── Счётчик активных фильтров ──────────────────────────────
$activeCount = 0;
foreach ($availableFields as $f) {
  $val = $form->getValue($f->fieldname, $f->group);
  if (is_array($val)) {
    $activeCount += count(array_filter($val, static fn($item): bool => (string) $item !== ''));
  } elseif (!empty($val) && $val !== '') {
    $activeCount++;
  }
}

$formId     = 'mod_dharma_universal_filter_' . $module->id;
$offcanvasId = $formId . '_offcanvas';
$offcanvasMode = (string) $params->get('offcanvas_mode', 'mobile');
$offcanvasMode = in_array($offcanvasMode, ['never', 'mobile', 'always'], true) ? $offcanvasMode : 'mobile';
$showOffcanvas = $offcanvasMode !== 'never';
$showInline = $offcanvasMode !== 'always';
$offcanvasButtonClass = $offcanvasMode === 'mobile' ? 'd-lg-none mb-2' : 'mb-2';
$offcanvasClass = $offcanvasMode === 'mobile'
  ? 'offcanvas offcanvas-start duf-offcanvas d-lg-none'
  : 'offcanvas offcanvas-start duf-offcanvas';
$inlineClass = $offcanvasMode === 'mobile' ? 'd-none d-lg-block' : 'd-block';
?>

<?php /* ════ КНОПКА (мобайл + планшет) ════ */ ?>
<?php if ($showOffcanvas): ?>
<div class="<?php echo $offcanvasButtonClass . $stickyClass; ?>"<?php echo $stickyAttrs; ?>>
  <button class="duf-filter-toggle"
          type="button"
          data-bs-toggle="offcanvas"
          data-bs-target="#<?php echo $offcanvasId; ?>"
          aria-controls="<?php echo $offcanvasId; ?>">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
      <line x1="4" y1="6"  x2="20" y2="6"/>
      <line x1="8" y1="12" x2="20" y2="12"/>
      <line x1="12" y1="18" x2="20" y2="18"/>
    </svg>
    <?php echo Text::_('MOD_DHARMA_UNIVERSAL_FILTER_BUTTON'); ?>
    <?php if ($activeCount > 0): ?>
      <span class="duf-filter-toggle__badge"><?php echo $activeCount; ?></span>
    <?php endif; ?>
  </button>
</div>

<?php /* ════ ОФФКАНВАС (мобайл + планшет) ════ */ ?>
<div class="<?php echo $offcanvasClass; ?>"
     tabindex="-1"
     id="<?php echo $offcanvasId; ?>"
     aria-labelledby="<?php echo $offcanvasId; ?>_label">

  <div class="offcanvas-header">
    <div class="duf-offcanvas__title" id="<?php echo $offcanvasId; ?>_label">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
           stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="4" y1="6"  x2="20" y2="6"/>
        <line x1="8" y1="12" x2="20" y2="12"/>
        <line x1="12" y1="18" x2="20" y2="18"/>
      </svg>
      <?php echo Text::_('MOD_DHARMA_UNIVERSAL_FILTER_BUTTON'); ?>
      <?php if ($activeCount > 0): ?>
        <span class="duf-filter-toggle__badge"><?php echo $activeCount; ?></span>
      <?php endif; ?>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?php echo Text::_('MOD_DHARMA_UNIVERSAL_FILTER_CLOSE'); ?>"></button>
  </div>

  <div class="offcanvas-body">
    <form action="<?php echo $action; ?>" method="get"
          onsubmit="<?php echo $onSubmit; ?>"
          data-dharma-filter-ajax="<?php echo $formId; ?>_mobile">

      <div class="accordion" id="<?php echo $formId; ?>_mobile_accordion">
        <?php foreach ($renderFields as $i => $renderField):
          $field = $renderField['field'];
          $name  = $field->fieldname;
          $group = $field->group;
          $open  = (($renderField['config']['expanded'] ?? '1') === '1' || $form->getValue($name, $group)) ? 'show' : '';
          $id    = $formId . '_m_' . $field->id;
          $form->setFieldAttribute($name, 'id', $id, $group);
          if (!empty($renderField['config']['title']))
            $form->setFieldAttribute($name, 'label', (string) $renderField['config']['title'], $group);
          $type = strtolower((string) $form->getFieldAttribute($name, 'type', '', $group));
          $isPrice = ($name === 'price' && $group === 'filter');
          $layout = (string) ($renderField['config']['layout'] ?? '');
          if ($layout === '') {
            $layout = match(true) {
              $isPrice && $params->get('price_display','inputs') === 'slider' => 'modules.dharma_universal_filter.field.price_slider',
              $isPrice => 'modules.dharma_universal_filter.field.price_inputs',
              str_contains($type,'checkbox') => 'modules.dharma_universal_filter.field.checkboxes',
              $type === 'radio'  => 'modules.dharma_universal_filter.field.radio',
              $type === 'list'   => 'modules.dharma_universal_filter.field.select',
              default            => 'modules.dharma_universal_filter.field.default',
            };
          }
          $labelId   = $id . '_label';
          $contentId = $id . '_content';
        ?>
        <div class="accordion-item border-0 border-bottom rounded-0">
          <div class="accordion-header" id="<?php echo $labelId; ?>">
            <button class="accordion-button <?php echo $open ? '' : 'collapsed'; ?> px-0"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#<?php echo $contentId; ?>"
                    aria-expanded="<?php echo $open ? 'true' : 'false'; ?>"
                    aria-controls="<?php echo $contentId; ?>">
              <?php echo Text::_($form->getFieldAttribute($name, 'label', $name, $group)); ?>
            </button>
          </div>
          <div id="<?php echo $contentId; ?>"
               class="accordion-collapse collapse <?php echo $open; ?>"
               aria-labelledby="<?php echo $labelId; ?>">
            <div class="accordion-body px-0">
              <?php echo LayoutHelper::render($layout, [
                'field' => $field, 'form' => $form, 'group' => $group,
                'name'  => $name, 'module' => $module, 'params' => $params,
                'onSubmit' => $onSubmit, 'config' => $renderField['config'],
              ]); ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if ((int)$params->get('show_search_button',1) || (int)$params->get('show_reset_button',1)): ?>
        <div class="d-grid gap-2 mt-3">
          <?php if ((int)$params->get('show_search_button',1)): ?>
            <button type="submit" class="duf-hfilter__btn duf-hfilter__btn--primary justify-content-center">
              <?php echo Text::_('JGLOBAL_FILTER_BUTTON'); ?>
            </button>
          <?php endif; ?>
          <?php if ((int)$params->get('show_reset_button',1)): ?>
            <a href="<?php echo $action; ?>" class="duf-hfilter__btn duf-hfilter__btn--reset justify-content-center">
              <?php echo Text::_('MOD_DHARMA_UNIVERSAL_FILTER_RESET_BUTTON'); ?>
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    </form>
  </div>
</div>
<?php endif; ?>

<?php /* ════ ГОРИЗОНТАЛЬНЫЙ ФИЛЬТР (десктоп) ════ */ ?>
<?php if ($showInline): ?>
<div class="<?php echo $inlineClass . $stickyClass; ?>"<?php echo $stickyAttrs; ?>>
  <form action="<?php echo $action; ?>" method="get"
        onsubmit="<?php echo $onSubmit; ?>"
        data-dharma-filter-ajax="<?php echo $formId; ?>_desktop"
        class="duf-hfilter">

    <?php foreach ($renderFields as $renderField):
      $field   = $renderField['field'];
      $name    = $field->fieldname;
      $group   = $field->group;
      $isPrice = ($name === 'price' && $group === 'filter');
      $id      = $formId . '_d_' . $field->id;
      $form->setFieldAttribute($name, 'id', $id, $group);
      if (!empty($renderField['config']['title']))
        $form->setFieldAttribute($name, 'label', (string) $renderField['config']['title'], $group);
      $type = strtolower((string) $form->getFieldAttribute($name, 'type', '', $group));
      $layout = (string) ($renderField['config']['layout'] ?? '');
      if ($layout === '') {
        $layout = match(true) {
          $isPrice && $params->get('price_display','inputs') === 'slider' => 'modules.dharma_universal_filter.field.price_slider',
          $isPrice => 'modules.dharma_universal_filter.field.price_inputs',
          str_contains($type,'checkbox') => 'modules.dharma_universal_filter.field.checkboxes_dropdown',
          $type === 'radio'  => 'modules.dharma_universal_filter.field.radio',
          $type === 'list'   => 'modules.dharma_universal_filter.field.select',
          default            => 'modules.dharma_universal_filter.field.default',
        };
      }
      if ($isPrice): ?>
        <div class="duf-hfilter__divider"></div>
      <?php endif; ?>

      <div class="duf-hfilter__group <?php echo $isPrice ? 'duf-hfilter__group--price' : 'duf-hfilter__group--grow'; ?>">
        <span class="duf-hfilter__label">
          <?php echo Text::_($form->getFieldAttribute($name, 'label', $name, $group)); ?>
        </span>
        <?php echo LayoutHelper::render($layout, [
          'field' => $field, 'form' => $form, 'group' => $group,
          'name'  => $name, 'module' => $module, 'params' => $params,
          'onSubmit' => $onSubmit, 'config' => $renderField['config'],
        ]); ?>
      </div>

    <?php endforeach; ?>

    <?php if ((int)$params->get('show_search_button',1) || (int)$params->get('show_reset_button',1)): ?>
      <div class="duf-hfilter__divider"></div>
      <div class="duf-hfilter__actions">
        <?php if ((int)$params->get('show_search_button',1)): ?>
          <button type="submit" class="duf-hfilter__btn duf-hfilter__btn--primary">
            <?php echo Text::_('JGLOBAL_FILTER_BUTTON'); ?>
          </button>
        <?php endif; ?>
        <?php if ((int)$params->get('show_reset_button',1)): ?>
          <a href="<?php echo $action; ?>" class="duf-hfilter__btn duf-hfilter__btn--reset">
            <?php echo Text::_('MOD_DHARMA_UNIVERSAL_FILTER_RESET_BUTTON'); ?>
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </form>
</div>
<?php endif; ?>
