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
 * \file        htdocs/custom/verleih/label.php
 * \ingroup     verleih
 * \brief       Select items and generate inventory labels (PDF)
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

require_once __DIR__.'/class/verleihitem.class.php';
require_once __DIR__.'/class/verleihitemtype.class.php';

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
if (!$user->hasRight('verleih', 'etiketten')) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$search_fk_itemtype = GETPOSTINT('search_fk_itemtype');
$search_inventorynumber = GETPOST('search_inventorynumber', 'alpha');

$typecache = array();
$typeobjbyid = array();
$typeobj = new VerleihItemType($db);
foreach ($typeobj->fetchAll('ASC', 'label', 0, 0, '') as $t) {
	$typecache[$t->id] = $t->ref.' - '.$t->label;
	$typeobjbyid[$t->id] = $t;
}

if ($action == 'generate') {
	$selected = GETPOST('toselect', 'array:int');
	if (empty($selected)) {
		setEventMessages($langs->trans("VerleihNoItemsSelected"), null, 'errors');
	} else {
		$items = array();
		foreach ($selected as $itemid) {
			$it = new VerleihItem($db);
			if ($it->fetch($itemid) > 0) {
				$items[] = $it;
			}
		}
		require_once __DIR__.'/class/verleihlabelpdf.class.php';
		$labelpdf = new VerleihLabelPdf($db);
		$labelpdf->stream($items, $typeobjbyid, 'verleih_etiketten_'.dol_print_date(dol_now(), '%Y%m%d').'.pdf');
		exit;
	}
}

/*
 * View
 */
$form = new Form($db);
$title = $langs->trans("VerleihLabels");

llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-verleih page-label');

print load_fiche_titre($title, '', 'fa-tags');

$filter = '(status:!=:'.VerleihItem::STATUS_RETIRED.')';
if ($search_fk_itemtype > 0) {
	$filter .= " AND (fk_itemtype:=:".$search_fk_itemtype.")";
}
if ($search_inventorynumber !== '') {
	$filter .= " AND (inventorynumber:like:'%".$db->escape($search_inventorynumber)."%')";
}

$itemobj = new VerleihItem($db);
$items = $itemobj->fetchAll('ASC', 'inventorynumber', 0, 0, $filter);
if (!is_array($items)) {
	$items = array();
}

print '<div class="opacitymedium marginbottomonly">'.$langs->trans("VerleihSelectItemsForLabels").'</div>';

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="generate">';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td class="center"><input type="checkbox" onclick="jQuery(\'.verleihlabelcb\').prop(\'checked\', this.checked);"></td>';
print '<td>'.$langs->trans("VerleihInventoryNumber").'</td>';
print '<td>'.$langs->trans("VerleihSerialNumber").'</td>';
print '<td>'.$langs->trans("VerleihItemType").'</td>';
print '<td class="center">'.$langs->trans("Status").'</td>';
print '</tr>';

if (empty($items)) {
	print '<tr><td colspan="5"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
}

foreach ($items as $it) {
	print '<tr class="oddeven">';
	print '<td class="center"><input type="checkbox" class="verleihlabelcb" name="toselect[]" value="'.$it->id.'"></td>';
	print '<td>'.dol_escape_htmltag($it->inventorynumber).'</td>';
	print '<td>'.dol_escape_htmltag($it->serialnumber).'</td>';
	print '<td>'.dol_escape_htmltag($it->fk_itemtype ? ($typecache[$it->fk_itemtype] ?? '') : '').'</td>';
	print '<td class="center">'.$it->getLibStatut(5).'</td>';
	print '</tr>';
}

print '</table>';
print '</div>';

print '<div class="marginbottomonly right">';
print '<input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans("VerleihGenerateLabels")).'">';
print '</div>';

print '</form>';

llxFooter();
$db->close();
