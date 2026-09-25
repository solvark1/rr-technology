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
 * \file        htdocs/custom/verleih/report_student.php
 * \ingroup     verleih
 * \brief       Report: everything a given student has borrowed (view + CSV export)
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

require_once __DIR__.'/class/verleihstudent.class.php';
require_once __DIR__.'/class/verleihschoolclass.class.php';

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
$fk_student = GETPOSTINT('fk_student');

// View mode: classic table (default) or modern tile view ("kanban"), see css_changes.md.
$mode = GETPOST('mode', 'aZ');
if ($mode === '') {
	$mode = $_SESSION['VERLEIH_VIEWMODE'] ?? '';
} else {
	$_SESSION['VERLEIH_VIEWMODE'] = $mode;
}

$conditionOptions = array(
	1 => $langs->trans('VerleihConditionNew'),
	2 => $langs->trans('VerleihConditionGood'),
	3 => $langs->trans('VerleihConditionUsable'),
	4 => $langs->trans('VerleihConditionReplace'),
	5 => $langs->trans('VerleihConditionBroken'),
);

$classcache = array();
$classobj = new VerleihSchoolClass($db);
foreach ($classobj->fetchAll('ASC', 'label', 0, 0, '') as $c) {
	$classcache[$c->id] = $c->label.' ('.$c->schoolyear.')';
}

$studentcache = array();
$studentobj = new VerleihStudent($db);
foreach ($studentobj->fetchAll('ASC', 'lastname', 0, 0, '') as $s) {
	$studentcache[$s->id] = $s->getFullName().(!empty($s->fk_schoolclass) && isset($classcache[$s->fk_schoolclass]) ? ' - '.$classcache[$s->fk_schoolclass] : '');
}

$rows = array();
$student = null;
if ($fk_student > 0) {
	$student = new VerleihStudent($db);
	$student->fetch($fk_student);

	$sql = "SELECT l.rowid, l.condition_out, l.condition_in, l.date_return,";
	$sql .= " i.inventorynumber, i.serialnumber,";
	$sql .= " it.ref as itemtype_ref, it.label as itemtype_label,";
	$sql .= " loan.rowid as loan_id, loan.ref as loan_ref, loan.dateloan";
	$sql .= " FROM ".$db->prefix()."verleih_loan_line as l";
	$sql .= " LEFT JOIN ".$db->prefix()."verleih_item as i ON i.rowid = l.fk_item";
	$sql .= " LEFT JOIN ".$db->prefix()."verleih_itemtype as it ON it.rowid = i.fk_itemtype";
	$sql .= " LEFT JOIN ".$db->prefix()."verleih_loan as loan ON loan.rowid = l.fk_loan";
	$sql .= " WHERE l.fk_student = ".((int) $fk_student);
	$sql .= " ORDER BY loan.dateloan DESC, l.rowid DESC";

	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$rows[] = $obj;
		}
	}
}

if ($action == 'export' && $fk_student > 0) {
	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="verleih_schueler_'.$fk_student.'.csv"');
	$out = fopen('php://output', 'w');
	fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
	fputcsv($out, array($langs->trans('VerleihLoan'), $langs->trans('VerleihItemType'), $langs->trans('VerleihInventoryNumber'), $langs->trans('VerleihSerialNumber'), $langs->trans('VerleihDateLoan'), $langs->trans('VerleihConditionOut'), $langs->trans('Status'), $langs->trans('VerleihConditionIn'), $langs->trans('VerleihDateReturn')), ';');
	foreach ($rows as $row) {
		fputcsv($out, array(
			$row->loan_ref,
			$row->itemtype_label,
			$row->inventorynumber,
			$row->serialnumber,
			$row->dateloan ? dol_print_date($db->jdate($row->dateloan), 'day') : '',
			$conditionOptions[(int) $row->condition_out] ?? '',
			$row->date_return ? $langs->transnoentitiesnoconv('VerleihLineReturned') : $langs->transnoentitiesnoconv('VerleihLineOpen'),
			$row->date_return ? ($conditionOptions[(int) $row->condition_in] ?? '') : '',
			$row->date_return ? dol_print_date($db->jdate($row->date_return), 'day') : '',
		), ';');
	}
	fclose($out);
	exit;
}

/*
 * View
 */
$form = new Form($db);
$title = $langs->trans("VerleihReportByStudent");

$arrayofcss = array('/verleih/css/verleih.css');
llxHeader('', $title, '', '', 0, 0, '', $arrayofcss);

print load_fiche_titre($title, '<a href="'.dol_buildpath('/verleih/report_index.php', 1).'">'.$langs->trans("VerleihBackToReports").'</a>', 'fa-user-graduate');

print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="mode" value="'.dol_escape_htmltag($mode).'">';
print '<div class="marginbottomonly">';
print $langs->trans("VerleihChooseStudentForReport").': ';
print $form->selectarray('fk_student', $studentcache, $fk_student, 1);
print ' <input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans("Search")).'">';
print '</div>';
print '</form>';

if ($student && $student->id > 0) {
	$toggleparam = '&fk_student='.$student->id;
	print '<div class="vl-toolbar">';
	print '<span class="vl-viewtoggle">';
	print dolGetButtonTitle($langs->trans('ViewList'), '', 'fa fa-bars imgforviewmode', $_SERVER["PHP_SELF"].'?mode=list'.$toggleparam, '', ($mode != 'kanban' ? 2 : 1));
	print dolGetButtonTitle($langs->trans('ViewKanban'), '', 'fa fa-th-large imgforviewmode', $_SERVER["PHP_SELF"].'?mode=kanban'.$toggleparam, '', ($mode == 'kanban' ? 2 : 1));
	print '</span>';
	print '<span class="vl-spacer"></span>';
	if (!empty($rows)) {
		print '<a class="button" href="'.$_SERVER["PHP_SELF"].'?action=export&fk_student='.$student->id.'">'.$langs->trans("VerleihExportCsv").'</a>';
	}
	print '</div>';

	if ($mode == 'kanban') {
		if (empty($rows)) {
			print '<div class="vl-empty">'.$langs->trans("NoRecordFound").'</div>';
		} else {
			print '<div class="vl-grid">';
			foreach ($rows as $row) {
				print '<div class="vl-card">';
				print '<h3>'.dol_escape_htmltag($row->itemtype_label).'</h3>';
				print '<div class="vl-sub">'.dol_escape_htmltag($row->inventorynumber).'</div>';
				print '<div class="vl-body">';
				print '<div class="vl-row"><span class="vl-label">'.$langs->trans("VerleihLoan").'</span><span><a href="'.dol_buildpath('/verleih/loan_card.php', 1).'?id='.((int) $row->loan_id).'">'.dol_escape_htmltag($row->loan_ref).'</a></span></div>';
				print '<div class="vl-row"><span class="vl-label">'.$langs->trans("VerleihDateLoan").'</span><span>'.($row->dateloan ? dol_print_date($db->jdate($row->dateloan), 'day') : '').'</span></div>';
				if (!empty($row->date_return)) {
					print '<div class="vl-row"><span class="vl-label">'.$langs->trans("VerleihDateReturn").'</span><span>'.dol_print_date($db->jdate($row->date_return), 'day').'</span></div>';
				}
				print '</div>';
				print '<div class="vl-chips">';
				print '<span class="vl-chip vl-accent">'.dol_escape_htmltag($conditionOptions[(int) $row->condition_out] ?? '').'</span>';
				if (!empty($row->date_return)) {
					print '<span class="vl-chip vl-ok">'.dol_escape_htmltag($langs->trans("VerleihLineReturned")).'</span>';
					print '<span class="vl-chip vl-muted">'.dol_escape_htmltag($conditionOptions[(int) $row->condition_in] ?? '').'</span>';
				} else {
					print '<span class="vl-chip vl-warn">'.dol_escape_htmltag($langs->trans("VerleihLineOpen")).'</span>';
				}
				print '</div>';
				print '</div>';
			}
			print '</div>';
		}
	} else {
		print '<div class="div-table-responsive-no-min">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans("VerleihLoan").'</td>';
		print '<td>'.$langs->trans("VerleihItemType").'</td>';
		print '<td>'.$langs->trans("VerleihInventoryNumber").'</td>';
		print '<td class="center">'.$langs->trans("VerleihDateLoan").'</td>';
		print '<td>'.$langs->trans("VerleihConditionOut").'</td>';
		print '<td class="center">'.$langs->trans("Status").'</td>';
		print '<td>'.$langs->trans("VerleihConditionIn").'</td>';
		print '<td class="center">'.$langs->trans("VerleihDateReturn").'</td>';
		print '</tr>';

		if (empty($rows)) {
			print '<tr><td colspan="8"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
		}

		foreach ($rows as $row) {
			print '<tr class="oddeven">';
			print '<td><a href="'.dol_buildpath('/verleih/loan_card.php', 1).'?id='.((int) $row->loan_id).'">'.dol_escape_htmltag($row->loan_ref).'</a></td>';
			print '<td>'.dol_escape_htmltag($row->itemtype_label).'</td>';
			print '<td>'.dol_escape_htmltag($row->inventorynumber).'</td>';
			print '<td class="center">'.($row->dateloan ? dol_print_date($db->jdate($row->dateloan), 'day') : '').'</td>';
			print '<td>'.dol_escape_htmltag($conditionOptions[(int) $row->condition_out] ?? '').'</td>';
			if (!empty($row->date_return)) {
				print '<td class="center">'.$langs->trans("VerleihLineReturned").'</td>';
				print '<td>'.dol_escape_htmltag($conditionOptions[(int) $row->condition_in] ?? '').'</td>';
				print '<td class="center">'.dol_print_date($db->jdate($row->date_return), 'day').'</td>';
			} else {
				print '<td class="center">'.$langs->trans("VerleihLineOpen").'</td>';
				print '<td></td>';
				print '<td></td>';
			}
			print '</tr>';
		}

		print '</table>';
		print '</div>';
	}
}

llxFooter();
$db->close();
