<?php
// RR_RENTING_RETIRED_UI: preserved original source below; school routes are retired.
header('Location: ../renting.php?view=settings');
exit;

/* Copyright (C) 2026 Kim Wittkowski <kim@wittkowski-it.de>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file        htdocs/custom/verleih/admin/setup.php
 * \ingroup     verleih
 * \brief       Setup page for the Verleih module
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
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array("verleih@verleih", "admin", "other"));

if (!$user->admin && !$user->hasRight('verleih', 'configurer')) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

if ($action == 'update') {
	$cols = GETPOSTINT('VERLEIH_LABEL_COLS');
	$rows = GETPOSTINT('VERLEIH_LABEL_ROWS');
	dolibarr_set_const($db, 'VERLEIH_LABEL_COLS', $cols > 0 ? $cols : 3, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'VERLEIH_LABEL_ROWS', $rows > 0 ? $rows : 8, 'chaine', 0, '', $conf->entity);
	setEventMessages($langs->trans("SetupSaved"), null);
}

/*
 * View
 */
$title = $langs->trans("VerleihSetupPage");
llxHeader('', $title);

$linkback = '<a href="'.($backtopage ?? DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($title, $linkback, 'fa-graduation-cap');

print '<span class="opacitymedium">'.$langs->trans("VerleihSetupDesc").'</span>';
print '<br><br>';

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans("VerleihLabelSettings").'</td><td></td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans("VerleihLabelColumns").'</td><td><input type="number" min="1" max="10" name="VERLEIH_LABEL_COLS" value="'.getDolGlobalInt('VERLEIH_LABEL_COLS', 3).'"></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans("VerleihLabelRows").'</td><td><input type="number" min="1" max="20" name="VERLEIH_LABEL_ROWS" value="'.getDolGlobalInt('VERLEIH_LABEL_ROWS', 8).'"></td></tr>';
print '</table>';

print '<br><div class="center"><input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans("Save")).'"></div>';

print '</form>';

print '<br><br>';
print load_fiche_titre($langs->trans("VerleihExtrafieldsTitle"), '', 'fa-list-alt');
print '<span class="opacitymedium">'.$langs->trans("VerleihExtrafieldsDesc").'</span>';
print '<br><br>';

$extrafieldpages = array(
	array('label' => 'VerleihSchoolClass', 'url' => 'schoolclass_extrafields.php', 'picto' => 'fa-users'),
	array('label' => 'VerleihStudent', 'url' => 'student_extrafields.php', 'picto' => 'fa-user-graduate'),
	array('label' => 'VerleihItemType', 'url' => 'itemtype_extrafields.php', 'picto' => 'fa-tablet-alt'),
	array('label' => 'VerleihItem', 'url' => 'item_extrafields.php', 'picto' => 'fa-box'),
);

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
foreach ($extrafieldpages as $efp) {
	print '<tr class="oddeven"><td>'.img_picto('', $efp['picto'], 'class="paddingright"').$langs->trans($efp['label']).'</td>';
	print '<td class="right"><a class="button smallpaddingimp" href="'.dol_buildpath('/verleih/admin/'.$efp['url'], 1).'">'.$langs->trans("VerleihExtrafieldsTitle").'</a></td></tr>';
}
print '</table>';
print '</div>';

llxFooter();
$db->close();
