<?php
// RR_RENTING_RETIRED_UI: preserved original source below; school routes are retired.
header('Location: renting.php');
exit;

/* Copyright (C) 2026 Kim Wittkowski <kim@wittkowski-it.de>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file        htdocs/custom/verleih/report_index.php
 * \ingroup     verleih
 * \brief       Landing page for the "Listen" (reports) section
 */

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array("verleih@verleih", "other"));

if (!isModEnabled("verleih")) {
	accessforbidden("Module verleih not enabled");
}
if (!$user->hasRight('verleih', 'lire')) {
	accessforbidden();
}

$arrayofcss = array('/verleih/css/verleih.css');
llxHeader('', $langs->trans("VerleihReportsIndexTitle"), '', '', 0, 0, '', $arrayofcss);

print load_fiche_titre($langs->trans("VerleihReportsIndexTitle"), '', 'fa-list-alt');
print '<div class="opacitymedium marginbottomonly">'.$langs->trans("VerleihReportsIndexDesc").'</div>';

$reports = array(
	array('title' => 'VerleihReportByStudent', 'desc' => 'VerleihReportByStudentDesc', 'picto' => 'fa-user-graduate', 'url' => 'report_student.php'),
	array('title' => 'VerleihReportByItemType', 'desc' => 'VerleihReportByItemTypeDesc', 'picto' => 'fa-tablet-alt', 'url' => 'report_itemtype.php'),
);

print '<div class="vl-grid">';
foreach ($reports as $rep) {
	print '<div class="vl-card">';
	print '<h3><a href="'.dol_buildpath('/verleih/'.$rep['url'], 1).'">'.img_picto('', $rep['picto'], 'class="paddingright"').$langs->trans($rep['title']).'</a></h3>';
	print '<div class="vl-sub">'.$langs->trans($rep['desc']).'</div>';
	print '</div>';
}
print '</div>';

llxFooter();
$db->close();
