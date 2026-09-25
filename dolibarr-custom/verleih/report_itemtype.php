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
 * \file        htdocs/custom/verleih/report_itemtype.php
 * \ingroup     verleih
 * \brief       Report: items filtered by item type, with their current holder (student/class)
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
if (!$user->hasRight('verleih', 'lire')) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$fk_itemtype = GETPOSTINT('fk_itemtype');

// View mode: classic table (default) or modern tile view ("kanban"), see css_changes.md.
$mode = GETPOST('mode', 'aZ');
if ($mode === '') {
	$mode = $_SESSION['VERLEIH_VIEWMODE'] ?? '';
} else {
	$_SESSION['VERLEIH_VIEWMODE'] = $mode;
}

// Chip color classes for condition/status, per css_changes.md section 2.
$conditionChipClass = array(1 => 'vl-ok', 2 => 'vl-ok', 3 => 'vl-accent', 4 => 'vl-warn', 5 => 'vl-danger');
$statusChipClass = array(0 => 'vl-ok', 1 => 'vl-accent', 2 => 'vl-warn', 3 => 'vl-muted');

$conditionOptions = array(
	1 => $langs->trans('VerleihConditionNew'),
	2 => $langs->trans('VerleihConditionGood'),
	3 => $langs->trans('VerleihConditionUsable'),
	4 => $langs->trans('VerleihConditionReplace'),
	5 => $langs->trans('VerleihConditionBroken'),
);
$statusOptions = array(
	0 => $langs->trans('VerleihStatusInStock'),
	1 => $langs->trans('VerleihStatusLoaned'),
	2 => $langs->trans('VerleihStatusRepair'),
	3 => $langs->trans('VerleihStatusRetired'),
);

$typecache = array(0 => $langs->trans('VerleihAllItemTypes'));
$typeobj = new VerleihItemType($db);
foreach ($typeobj->fetchAll('ASC', 'label', 0, 0, '') as $t) {
	$typecache[$t->id] = $t->ref.' - '.$t->label;
}

$sql = "SELECT i.rowid, i.inventorynumber, i.serialnumber, i.itemcondition, i.status,";
$sql .= " it.ref as itemtype_ref, it.label as itemtype_label,";
$sql .= " s.rowid as student_id, s.firstname, s.lastname,";
$sql .= " c.label as class_label, c.schoolyear";
$sql .= " FROM ".$db->prefix()."verleih_item as i";
$sql .= " LEFT JOIN ".$db->prefix()."verleih_itemtype as it ON it.rowid = i.fk_itemtype";
$sql .= " LEFT JOIN ".$db->prefix()."verleih_loan_line as l ON l.fk_item = i.rowid AND l.date_return IS NULL";
$sql .= " LEFT JOIN ".$db->prefix()."verleih_student as s ON s.rowid = l.fk_student";
$sql .= " LEFT JOIN ".$db->prefix()."verleih_schoolclass as c ON c.rowid = s.fk_schoolclass";
$sql .= " WHERE i.entity IN (".getEntity('verleihitem').")";
if ($fk_itemtype > 0) {
	$sql .= " AND i.fk_itemtype = ".$fk_itemtype;
}
$sql .= " ORDER BY it.label ASC, i.inventorynumber ASC";

$resql = $db->query($sql);
$rows = array();
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$rows[] = $obj;
	}
} else {
	setEventMessages($db->lasterror(), null, 'errors');
}

if ($action == 'export') {
	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="verleih_objekttyp_verteilung.csv"');
	$out = fopen('php://output', 'w');
	fputs($out, "\xEF\xBB\xBF");
	fputcsv($out, array($langs->trans('VerleihItemType'), $langs->trans('VerleihInventoryNumber'), $langs->trans('VerleihSerialNumber'), $langs->trans('VerleihItemConditionField'), $langs->trans('Status'), $langs->trans('VerleihStudent'), $langs->trans('VerleihSchoolClass')), ';');
	foreach ($rows as $row) {
		fputcsv($out, array(
			$row->itemtype_label,
			$row->inventorynumber,
			$row->serialnumber,
			$conditionOptions[(int) $row->itemcondition] ?? '',
			$statusOptions[(int) $row->status] ?? '',
			$row->student_id ? trim($row->lastname.', '.$row->firstname) : '',
			$row->class_label ? $row->class_label.' ('.$row->schoolyear.')' : '',
		), ';');
	}
	fclose($out);
	exit;
}

/*
 * View
 */
$form = new Form($db);
$title = $langs->trans("VerleihReportByItemType");

$arrayofcss = array('/verleih/css/verleih.css');
llxHeader('', $title, '', '', 0, 0, '', $arrayofcss);

print load_fiche_titre($title, '<a href="'.dol_buildpath('/verleih/report_index.php', 1).'">'.$langs->trans("VerleihBackToReports").'</a>', 'fa-tablet-alt');

print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="mode" value="'.dol_escape_htmltag($mode).'">';
print '<div class="marginbottomonly">';
print $langs->trans("VerleihItemType").': ';
print $form->selectarray('fk_itemtype', $typecache, $fk_itemtype);
print ' <input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans("Search")).'">';
print '</div>';
print '</form>';

$exporturl = $_SERVER["PHP_SELF"].'?action=export'.($fk_itemtype ? '&fk_itemtype='.$fk_itemtype : '');
$toggleparam = $fk_itemtype ? '&fk_itemtype='.$fk_itemtype : '';
print '<div class="vl-toolbar">';
print '<span class="vl-viewtoggle">';
print dolGetButtonTitle($langs->trans('ViewList'), '', 'fa fa-bars imgforviewmode', $_SERVER["PHP_SELF"].'?mode=list'.$toggleparam, '', ($mode != 'kanban' ? 2 : 1));
print dolGetButtonTitle($langs->trans('ViewKanban'), '', 'fa fa-th-large imgforviewmode', $_SERVER["PHP_SELF"].'?mode=kanban'.$toggleparam, '', ($mode == 'kanban' ? 2 : 1));
print '</span>';
print '<span class="vl-spacer"></span>';
print '<a class="button" href="'.$exporturl.'">'.$langs->trans("VerleihExportCsv").'</a>';
print '</div>';

if ($mode == 'kanban') {
	if (empty($rows)) {
		print '<div class="vl-empty">'.$langs->trans("NoRecordFound").'</div>';
	} else {
		print '<div class="vl-grid">';
		foreach ($rows as $row) {
			print '<div class="vl-card">';
			print '<h3><a href="'.dol_buildpath('/verleih/item_card.php', 1).'?id='.((int) $row->rowid).'">'.dol_escape_htmltag($row->inventorynumber).'</a></h3>';
			print '<div class="vl-sub">'.dol_escape_htmltag($row->itemtype_ref.' - '.$row->itemtype_label).'</div>';
			print '<div class="vl-body">';
			if (!empty($row->serialnumber)) {
				print '<div class="vl-row"><span class="vl-label">'.$langs->trans("VerleihSerialNumber").'</span><span>'.dol_escape_htmltag($row->serialnumber).'</span></div>';
			}
			if ($row->student_id) {
				print '<div class="vl-row"><span class="vl-label">'.$langs->trans("VerleihCurrentHolder").'</span><span><a href="'.dol_buildpath('/verleih/student_card.php', 1).'?id='.((int) $row->student_id).'">'.dol_escape_htmltag(trim($row->lastname.', '.$row->firstname)).'</a></span></div>';
				print '<div class="vl-row"><span class="vl-label">'.$langs->trans("VerleihSchoolClass").'</span><span>'.dol_escape_htmltag($row->class_label ? $row->class_label.' ('.$row->schoolyear.')' : '').'</span></div>';
			} else {
				print '<div class="vl-row"><span class="vl-label">'.$langs->trans("VerleihCurrentHolder").'</span><span class="opacitymedium">'.$langs->trans("VerleihNotLoaned").'</span></div>';
			}
			print '</div>';
			print '<div class="vl-chips">';
			print '<span class="vl-chip '.($conditionChipClass[(int) $row->itemcondition] ?? 'vl-muted').'">'.dol_escape_htmltag($conditionOptions[(int) $row->itemcondition] ?? '').'</span>';
			print '<span class="vl-chip '.($statusChipClass[(int) $row->status] ?? 'vl-muted').'">'.dol_escape_htmltag($statusOptions[(int) $row->status] ?? '').'</span>';
			print '</div>';
			print '</div>';
		}
		print '</div>';
	}
} else {
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("VerleihItemType").'</td>';
	print '<td>'.$langs->trans("VerleihInventoryNumber").'</td>';
	print '<td>'.$langs->trans("VerleihSerialNumber").'</td>';
	print '<td>'.$langs->trans("VerleihItemConditionField").'</td>';
	print '<td class="center">'.$langs->trans("Status").'</td>';
	print '<td>'.$langs->trans("VerleihCurrentHolder").'</td>';
	print '<td>'.$langs->trans("VerleihSchoolClass").'</td>';
	print '</tr>';

	if (empty($rows)) {
		print '<tr><td colspan="7"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
	}

	foreach ($rows as $row) {
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($row->itemtype_ref.' - '.$row->itemtype_label).'</td>';
		print '<td><a href="'.dol_buildpath('/verleih/item_card.php', 1).'?id='.((int) $row->rowid).'">'.dol_escape_htmltag($row->inventorynumber).'</a></td>';
		print '<td>'.dol_escape_htmltag($row->serialnumber).'</td>';
		print '<td>'.dol_escape_htmltag($conditionOptions[(int) $row->itemcondition] ?? '').'</td>';
		print '<td class="center">'.dol_escape_htmltag($statusOptions[(int) $row->status] ?? '').'</td>';
		if ($row->student_id) {
			print '<td><a href="'.dol_buildpath('/verleih/student_card.php', 1).'?id='.((int) $row->student_id).'">'.dol_escape_htmltag(trim($row->lastname.', '.$row->firstname)).'</a></td>';
			print '<td>'.dol_escape_htmltag($row->class_label ? $row->class_label.' ('.$row->schoolyear.')' : '').'</td>';
		} else {
			print '<td><span class="opacitymedium">'.$langs->trans("VerleihNotLoaned").'</span></td>';
			print '<td></td>';
		}
		print '</tr>';
	}

	print '</table>';
	print '</div>';
}

llxFooter();
$db->close();
