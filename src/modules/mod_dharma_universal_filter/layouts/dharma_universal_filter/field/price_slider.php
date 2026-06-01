<?php
/**
 * @package     Dharma Universal Filter Module
 * @subpackage  mod_dharma_universal_filter
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;

/**
 * @var  array  $displayData
 * @var  Form   $form
 */

$form = $displayData['form'];
$hints = json_decode((string) $form->getFieldAttribute('price', 'hints', '{}', 'filter'), true);
$min = max(0, (float) ($hints['from'] ?? 0));
$max = max($min, (float) ($hints['to'] ?? 0));
$value = $form->getValue('price', 'filter', []);
$value = is_array($value) ? $value : [];
$rawFrom = trim((string) ($value['from'] ?? ''));
$rawTo = trim((string) ($value['to'] ?? ''));
$hasFrom = $rawFrom !== '';
$hasTo = $rawTo !== '';
$from = $hasFrom ? max($min, min($max, (float) $rawFrom)) : '';
$to = $hasTo ? max($min, min($max, (float) $rawTo)) : '';
$rangeFrom = $hasFrom ? $from : $min;
$rangeTo = $hasTo ? $to : $max;
$event = !empty($displayData['onSubmit']) ? ' onchange="' . $displayData['onSubmit'] . '"' : '';

if ($max <= 0)
{
	return;
}
?>
<div class="dharma-filter-price-slider" data-dharma-price-slider>
	<div class="row g-2 mb-2">
		<div class="col">
			<input type="number" class="form-control" name="filter[price][from]"
				   min="<?php echo $min; ?>" max="<?php echo $max; ?>"
				   placeholder="<?php echo htmlspecialchars((string) $min, ENT_QUOTES, 'UTF-8'); ?>"
				   value="<?php echo htmlspecialchars((string) $from, ENT_QUOTES, 'UTF-8'); ?>"
				   oninput="this.dataset.dharmaTouched='1';"<?php echo $event; ?>>
		</div>
		<div class="col">
			<input type="number" class="form-control" name="filter[price][to]"
				   min="<?php echo $min; ?>" max="<?php echo $max; ?>"
				   placeholder="<?php echo htmlspecialchars((string) $max, ENT_QUOTES, 'UTF-8'); ?>"
				   value="<?php echo htmlspecialchars((string) $to, ENT_QUOTES, 'UTF-8'); ?>"
				   oninput="this.dataset.dharmaTouched='1';"<?php echo $event; ?>>
		</div>
	</div>
	<div class="position-relative dharma-filter-price-slider__ranges" style="height: 2rem;">
		<input type="range" class="form-range dharma-filter-price-slider__range position-absolute top-0 start-0 w-100" style="z-index: 3;" min="<?php echo $min; ?>" max="<?php echo $max; ?>"
			   value="<?php echo htmlspecialchars((string) $rangeFrom, ENT_QUOTES, 'UTF-8'); ?>"
			   oninput="const input=this.closest('[data-dharma-price-slider]').querySelector('input[name=&quot;filter[price][from]&quot;]'); input.value=this.value; input.dataset.dharmaTouched='1';"<?php echo $event; ?>>
		<input type="range" class="form-range dharma-filter-price-slider__range position-absolute top-0 start-0 w-100" style="z-index: 4;" min="<?php echo $min; ?>" max="<?php echo $max; ?>"
			   value="<?php echo htmlspecialchars((string) $rangeTo, ENT_QUOTES, 'UTF-8'); ?>"
			   oninput="const input=this.closest('[data-dharma-price-slider]').querySelector('input[name=&quot;filter[price][to]&quot;]'); input.value=this.value; input.dataset.dharmaTouched='1';"<?php echo $event; ?>>
	</div>
</div>
