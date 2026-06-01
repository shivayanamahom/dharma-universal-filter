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

$form  = $displayData['form'];
$name  = (string) $displayData['name'];
$group = (string) $displayData['group'];

echo $form->getInput($name, $group);
