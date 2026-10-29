<?php
/**
 * @package     Dharma Universal Filter Module
 * @subpackage  mod_dharma_universal_filter
 *
 * Vertical filter checkboxes. The native RadicalMart markup is preserved so
 * its names, selected values, disabled options and AJAX attributes continue
 * to work. The module's limit setting only hides extra rows and never turns
 * the field into a dropdown.
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;

/** @var array $displayData */
/** @var Form  $form */

$form  = $displayData['form'];
$name  = (string) $displayData['name'];
$group = (string) $displayData['group'];
$params = $displayData['params'] ?? null;
$limited = $params && (string) $params->get('checkbox_display_mode', 'all') === 'limit';
$visibleCount = $limited ? max(1, (int) $params->get('checkbox_visible_count', 6)) : 0;
$inputHtml = $form->getInput($name, $group);

// RadicalMart appends its own red "clear" link to every checkbox group; the module has a single reset button instead.
$inputHtml = preg_replace('~<div class="text-end">\s*<a\b[^>]*text-danger[^>]*>.*?</a>\s*</div>~is', '', $inputHtml);
$optionCount = 0;
$classAttributes = [];
preg_match_all('~class\s*=\s*["\']([^"\']*)["\']~i', $inputHtml, $classAttributes);

foreach ($classAttributes[1] ?? [] as $classList)
{
	if (preg_match('/(?:^|\s)(?:form-check|rm-color-swatches__option)(?:\s|$)/', $classList))
	{
		$optionCount++;
	}
}

$hasMore = $limited && $optionCount > $visibleCount;

// The module's JS applies `hidden` after DOM ready. Add the same state to the
// server-rendered markup so a refresh never paints all options first and then
// collapses the list (or briefly shows a useless button for short lists).
if ($hasMore)
{
	$optionIndex = 0;
	$inputHtml = preg_replace_callback(
		'~<([a-z][a-z0-9]*)\b([^>]*)>~i',
		static function (array $match) use (&$optionIndex, $visibleCount): string {
			$classAttributes = [];
			preg_match('~class\s*=\s*["\']([^"\']*)["\']~i', $match[2], $classAttributes);

			if (!preg_match('/(?:^|\s)(?:form-check|rm-color-swatches__option)(?:\s|$)/', $classAttributes[1] ?? ''))
			{
				return $match[0];
			}

			$isVisible = $optionIndex++ < $visibleCount;

			return $isVisible
				? $match[0]
				: '<' . $match[1] . $match[2] . ' hidden>';
		},
		$inputHtml
	);
}
?>
<div class="duf-vchecklist" data-duf-vchecklist="1" data-visible-count="<?php echo $visibleCount; ?>"
	 data-show-all="<?php echo htmlspecialchars(Text::_('MOD_DHARMA_UNIVERSAL_FILTER_SHOW_ALL'), ENT_QUOTES, 'UTF-8'); ?>"
	 data-hide-extra="<?php echo htmlspecialchars(Text::_('MOD_DHARMA_UNIVERSAL_FILTER_HIDE_EXTRA'), ENT_QUOTES, 'UTF-8'); ?>">
	<?php echo $inputHtml; ?>
	<?php if ($hasMore): ?>
		<button type="button" class="duf-vchecklist__toggle" data-duf-vchecklist-toggle>
			<span data-duf-vchecklist-label><?php echo Text::_('MOD_DHARMA_UNIVERSAL_FILTER_SHOW_ALL'); ?></span>
			<span class="duf-vchecklist__chevron" aria-hidden="true"></span>
		</button>
	<?php endif; ?>
</div>
