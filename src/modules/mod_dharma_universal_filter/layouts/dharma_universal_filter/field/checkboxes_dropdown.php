<?php
/**
 * @package     Dharma Universal Filter Module
 * @subpackage  mod_dharma_universal_filter
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;

/**
 * @var  array  $displayData
 * @var  Form   $form
 */

$form  = $displayData['form'];
$name  = (string) $displayData['name'];
$group = (string) $displayData['group'];
$field = $displayData['field'] ?? null;
$value = $form->getValue($name, $group);

if (!is_array($value))
{
	$value = $value === null || $value === '' ? [] : [$value];
}

$selectedCount = count(array_filter($value, static fn($item): bool => (string) $item !== ''));
$label = $field ? Text::_($form->getFieldAttribute($name, 'label', $name, $group)) : Text::_('JSELECT');
$summary = $selectedCount > 0 ? $label : Text::_('MOD_DHARMA_UNIVERSAL_FILTER_ALL');
$params = $displayData['params'] ?? null;
$submitMode = $params ? (string) $params->get('checkbox_dropdown_submit_mode', 'apply') : 'apply';
$submitMode = in_array($submitMode, ['apply', 'instant'], true) ? $submitMode : 'apply';
$input = preg_replace('/\s+onchange=(["\']).*?\1/i', '', $form->getInput($name, $group));
?>
<details class="duf-checkselect"
		 data-duf-checkselect
		 data-submit-mode="<?php echo $submitMode; ?>"
		 data-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>"
		 data-empty="<?php echo htmlspecialchars(Text::_('MOD_DHARMA_UNIVERSAL_FILTER_ALL'), ENT_QUOTES, 'UTF-8'); ?>"
		 data-reset="<?php echo htmlspecialchars(Text::_('MOD_DHARMA_UNIVERSAL_FILTER_RESET_BUTTON'), ENT_QUOTES, 'UTF-8'); ?>">
	<summary class="duf-checkselect__button">
		<span class="duf-checkselect__text"><?php echo $summary; ?></span>
		<?php if ($selectedCount > 0): ?>
			<span class="duf-checkselect__count"><?php echo $selectedCount; ?></span>
			<span class="duf-checkselect__clear"
				  role="button"
				  tabindex="0"
				  aria-label="<?php echo htmlspecialchars(Text::_('MOD_DHARMA_UNIVERSAL_FILTER_RESET_BUTTON'), ENT_QUOTES, 'UTF-8'); ?>"
				  data-duf-checkselect-clear>&times;</span>
		<?php endif; ?>
	</summary>
	<div class="duf-checkselect__panel">
		<?php echo $input; ?>
		<?php if ($submitMode === 'apply'): ?>
			<button type="button" class="duf-checkselect__apply" data-duf-checkselect-apply>
				<?php echo Text::_('MOD_DHARMA_UNIVERSAL_FILTER_APPLY'); ?>
			</button>
		<?php endif; ?>
	</div>
</details>
