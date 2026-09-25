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
 * \file        htdocs/custom/verleih/loan_card.php
 * \ingroup     verleih
 * \brief       Card page for VerleihLoan: header, lines, checkout (Ausgabe) and return (Rücknahme)
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

require_once __DIR__.'/class/verleihloan.class.php';
require_once __DIR__.'/class/verleihschoolclass.class.php';
require_once __DIR__.'/class/verleihitem.class.php';
require_once __DIR__.'/class/verleihitemtype.class.php';
require_once __DIR__.'/class/verleihstudent.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array("verleih@verleih", "other"));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

$object = new VerleihLoan($db);
if ($id > 0) {
	$object->fetch($id);
}

if (!isModEnabled("verleih")) {
	accessforbidden("Module verleih not enabled");
}
if (!$user->hasRight('verleih', 'lire')) {
	accessforbidden();
}
$permissiontoadd = $user->hasRight('verleih', 'creer');
$permissiontodelete = $user->hasRight('verleih', 'supprimer');
$permissiontocheckout = $user->hasRight('verleih', 'ausgeben');

$classcache = array();
$classobj = new VerleihSchoolClass($db);
foreach ($classobj->fetchAll('ASC', 'label', 0, 0, '(status:=:1)') as $c) {
	$classcache[$c->id] = $c->label.' ('.$c->schoolyear.')';
}

$itemtypecache = array();
$itemtypeobj = new VerleihItemType($db);
foreach ($itemtypeobj->fetchAll('ASC', 'label', 0, 0, '(status:=:1)') as $it) {
	$itemtypecache[$it->id] = $it->ref.' - '.$it->label;
}

// Object type filter for the item picker: chosen either at loan creation or changed later
// directly on the card, kept per-loan in session so it survives the add-line redirect loop.
if ($id > 0 && GETPOSTISSET('filter_itemtype')) {
	$_SESSION['VERLEIH_LOAN_ITEMTYPE_'.$id] = GETPOSTINT('filter_itemtype');
}
$preferred_itemtype = $id > 0 ? (int) ($_SESSION['VERLEIH_LOAN_ITEMTYPE_'.$id] ?? 0) : 0;

// Items already on an open (not yet returned) loan line - anywhere, including this loan's
// own draft lines - must not be offered again, a physical item can only be out once.
$openitemids = array();
$sqlopenitems = "SELECT DISTINCT fk_item FROM ".$db->prefix()."verleih_loan_line WHERE date_return IS NULL";
$resqlopenitems = $db->query($sqlopenitems);
if ($resqlopenitems) {
	while ($objopenitem = $db->fetch_object($resqlopenitems)) {
		$openitemids[(int) $objopenitem->fk_item] = true;
	}
}

$itemcache = array();
$itemobj = new VerleihItem($db);
foreach ($itemobj->fetchAll('ASC', 'inventorynumber', 0, 0, '(status:=:0)') as $it) {
	if (isset($openitemids[$it->id])) {
		continue;
	}
	if (!empty($preferred_itemtype) && (int) $it->fk_itemtype != $preferred_itemtype) {
		continue;
	}
	$itemcache[$it->id] = $it->inventorynumber.($it->serialnumber ? ' ('.$it->serialnumber.')' : '');
}

// If the loan is tied to a class, only offer students of that class - otherwise fall
// back to all active students (e.g. for loans not linked to a specific class).
$studentfilter = '(status:=:1)';
if (!empty($object->fk_schoolclass)) {
	$studentfilter .= ' AND (fk_schoolclass:=:'.((int) $object->fk_schoolclass).')';
}

$studentcache = array();
$studentobj = new VerleihStudent($db);
foreach ($studentobj->fetchAll('ASC', 'lastname', 0, 0, $studentfilter) as $s) {
	$studentcache[$s->id] = $s->getFullName();
}

$conditionOptions = array(
	1 => $langs->trans('VerleihConditionNew'),
	2 => $langs->trans('VerleihConditionGood'),
	3 => $langs->trans('VerleihConditionUsable'),
	4 => $langs->trans('VerleihConditionReplace'),
	5 => $langs->trans('VerleihConditionBroken'),
);

/*
 * Actions
 */
if ($action == 'add' && $permissiontoadd) {
	$object->fk_schoolclass = GETPOSTINT('fk_schoolclass') ?: null;
	$object->schoolyear = GETPOST('schoolyear', 'alphanohtml');
	$object->dateloan = dol_mktime(0, 0, 0, GETPOSTINT('dateloanmonth'), GETPOSTINT('dateloanday'), GETPOSTINT('dateloanyear'));
	$object->datereturnplanned = dol_mktime(0, 0, 0, GETPOSTINT('datereturnplannedmonth'), GETPOSTINT('datereturnplannedday'), GETPOSTINT('datereturnplannedyear'));
	$object->note = GETPOST('note', 'alphanohtml');
	$object->status = VerleihLoan::STATUS_DRAFT;

	if (empty($object->schoolyear) || empty($object->dateloan)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->trans("VerleihSchoolYear")), null, 'errors');
		$action = 'create';
	} else {
		$result = $object->create($user);
		if ($result > 0) {
			$fk_itemtype_create = GETPOSTINT('fk_itemtype');
			if ($fk_itemtype_create > 0) {
				$_SESSION['VERLEIH_LOAN_ITEMTYPE_'.$result] = $fk_itemtype_create;
			}
			header("Location: ".$_SERVER["PHP_SELF"]."?id=".$result);
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
			$action = 'create';
		}
	}
}

if ($action == 'addline' && $permissiontoadd && $object->id > 0) {
	$fk_item = GETPOSTINT('fk_item');
	$fk_student = GETPOSTINT('fk_student');
	$note = GETPOST('linenote', 'alphanohtml');

	if (empty($fk_item) || empty($fk_student)) {
		setEventMessages($langs->trans("VerleihChooseItem").' / '.$langs->trans("VerleihChooseStudent"), null, 'errors');
	} else {
		$result = $object->addLine($user, $fk_item, $fk_student, $note);
		if ($result <= 0) {
			setEventMessages($langs->trans($object->error), null, 'errors');
		}
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

if ($action == 'deleteline' && $permissiontoadd && $object->id > 0) {
	$object->deleteLine($user, GETPOSTINT('lineid'));
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

if ($action == 'confirm_checkout' && GETPOST('confirm') == 'yes' && $permissiontocheckout && $object->id > 0) {
	$result = $object->checkout($user);
	if ($result > 0) {
		setEventMessages($langs->trans("VerleihCheckout")." OK", null);
	} else {
		setEventMessages($langs->trans($object->error), null, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

if ($action == 'checkin' && $permissiontocheckout && $object->id > 0) {
	$selectedlines = GETPOST('checkinline', 'array:int');
	$conditions = array();
	foreach ($selectedlines as $lineid) {
		$conditions[$lineid] = GETPOSTINT('condition_in_'.$lineid);
	}
	if (empty($conditions)) {
		setEventMessages($langs->trans("VerleihNoItemsSelected"), null, 'errors');
	} else {
		$result = $object->processReturn($user, $conditions);
		if ($result <= 0) {
			setEventMessages($langs->trans($object->error), null, 'errors');
		}
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' && $permissiontodelete && $object->id > 0 && $object->status == VerleihLoan::STATUS_DRAFT) {
	$result = $object->delete($user);
	if ($result > 0) {
		header("Location: ".dol_buildpath('/verleih/loan_list.php', 1));
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

/*
 * View
 */
$form = new Form($db);
$title = $langs->trans("VerleihLoan");

llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-verleih page-card');

if ($action == 'create') {
	print load_fiche_titre($langs->trans("New").' - '.$langs->trans("VerleihLoan"), '', 'fa-people-carry');

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	print dol_get_fiche_head();
	print '<table class="border centpercent">';

	print '<tr><td class="titlefieldcreate">'.$langs->trans("VerleihSchoolClass").'</td><td>'.$form->selectarray('fk_schoolclass', $classcache, GETPOSTINT('fk_schoolclass'), 1).'</td></tr>';
	print '<tr><td>'.$langs->trans("VerleihItemType").'</td><td>'.$form->selectarray('fk_itemtype', $itemtypecache, GETPOSTINT('fk_itemtype'), 1).'<span class="opacitymedium"> - '.$langs->trans("VerleihPreselectItemTypeHelp").'</span></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans("VerleihSchoolYear").'</td><td><input type="text" name="schoolyear" class="minwidth100" placeholder="2026/2027" value="'.dol_escape_htmltag(GETPOST('schoolyear', 'alphanohtml')).'"></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans("VerleihDateLoan").'</td><td>'.$form->selectDate('', 'dateloan', 0, 0, 0, '', 1, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans("VerleihDateReturnPlanned").'</td><td>'.$form->selectDate('', 'datereturnplanned', 0, 0, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans("Note").'</td><td><input type="text" name="note" class="minwidth300" value="'.dol_escape_htmltag(GETPOST('note', 'alphanohtml')).'"></td></tr>';

	print '</table>';
	print dol_get_fiche_end();

	print $form->buttonsSaveCancel("Create");
	print '</form>';
} elseif ($object->id > 0) {
	if ($action == 'delete') {
		print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('Delete'), $langs->trans('ConfirmDeleteObject'), 'confirm_delete', '', 0, 1);
	}
	if ($action == 'checkout') {
		print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('VerleihCheckout'), $langs->trans('VerleihConfirmCheckout'), 'confirm_checkout', '', 'yes', 1);
	}

	print dol_get_fiche_head(array(), '', $langs->trans("VerleihLoan"), -1, 'fa-people-carry');

	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">';

	print '<tr><td class="titlefield">'.$langs->trans("Ref").'</td><td>'.dol_escape_htmltag($object->ref).'</td></tr>';
	print '<tr><td>'.$langs->trans("VerleihSchoolClass").'</td><td>'.dol_escape_htmltag($object->fk_schoolclass ? ($classcache[$object->fk_schoolclass] ?? '') : '').'</td></tr>';
	print '<tr><td>'.$langs->trans("VerleihSchoolYear").'</td><td>'.dol_escape_htmltag($object->schoolyear).'</td></tr>';
	print '<tr><td>'.$langs->trans("VerleihDateLoan").'</td><td>'.($object->dateloan ? dol_print_date($object->dateloan, 'day') : '').'</td></tr>';
	print '<tr><td>'.$langs->trans("VerleihDateReturnPlanned").'</td><td>'.($object->datereturnplanned ? dol_print_date($object->datereturnplanned, 'day') : '').'</td></tr>';
	print '<tr><td>'.$langs->trans("Note").'</td><td>'.dol_escape_htmltag($object->note).'</td></tr>';
	print '<tr><td>'.$langs->trans("Status").'</td><td>'.$object->getLibStatut(4).'</td></tr>';

	print '</table>';
	print '</div>';
	print '<div class="clearboth"></div>';
	print dol_get_fiche_end();

	// Actions buttons
	print '<div class="tabsAction">';
	if ($permissiontoadd && $object->status == VerleihLoan::STATUS_DRAFT) {
		print dolGetButtonAction('', $langs->trans('VerleihScanButton'), 'default', dol_buildpath('/verleih/loan_scan.php', 1).'?id='.$object->id);
	}
	if ($permissiontocheckout && $object->status == VerleihLoan::STATUS_DRAFT && count($object->lines) > 0) {
		print dolGetButtonAction('', $langs->trans('VerleihCheckout'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=checkout&token='.newToken());
	}
	if ($permissiontodelete && $object->status == VerleihLoan::STATUS_DRAFT) {
		print dolGetButtonAction('', $langs->trans('Delete'), 'delete', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete&token='.newToken());
	}
	print '</div>';

	// Lines
	print load_fiche_titre($langs->trans("VerleihLoanLines"), '', '');

	$isReturnable = ($object->status == VerleihLoan::STATUS_ACTIVE || $object->status == VerleihLoan::STATUS_PARTIAL);

	// The whole lines table is wrapped in the checkin form when returns can be processed,
	// so <form> stays a proper ancestor of <table> instead of a sibling of its <tr> rows.
	if ($isReturnable) {
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="checkin">';
	}

	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("VerleihItem").'</td>';
	print '<td>'.$langs->trans("VerleihStudent").'</td>';
	print '<td>'.$langs->trans("VerleihConditionOut").'</td>';
	print '<td class="center">'.$langs->trans("Status").'</td>';
	print '<td>'.$langs->trans("VerleihConditionIn").'</td>';
	print '<td class="center">'.$langs->trans("VerleihDateReturn").'</td>';
	print '<td class="right"></td>';
	print '</tr>';

	if (empty($object->lines)) {
		print '<tr><td colspan="7"><span class="opacitymedium">'.$langs->trans("VerleihNoLinesYet").'</span></td></tr>';
	}

	foreach ($object->lines as $line) {
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($line->item_inventorynumber).'</td>';
		print '<td>'.dol_escape_htmltag($line->student_name).'</td>';
		print '<td>'.dol_escape_htmltag($conditionOptions[$line->condition_out] ?? '').'</td>';
		if (!empty($line->date_return)) {
			print '<td class="center">'.$langs->trans("VerleihLineReturned").'</td>';
			print '<td>'.dol_escape_htmltag($conditionOptions[$line->condition_in] ?? '').'</td>';
			print '<td class="center">'.dol_print_date($line->date_return, 'day').'</td>';
			print '<td></td>';
		} elseif ($isReturnable) {
			print '<td class="center"><input type="checkbox" name="checkinline[]" value="'.$line->id.'"></td>';
			print '<td>'.$form->selectarray('condition_in_'.$line->id, $conditionOptions, $line->condition_out).'</td>';
			print '<td class="center"></td>';
			print '<td></td>';
		} else {
			print '<td class="center">'.$langs->trans("VerleihLineOpen").'</td>';
			print '<td></td>';
			print '<td></td>';
			print '<td class="right">';
			if ($permissiontoadd && $object->status == VerleihLoan::STATUS_DRAFT) {
				print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=deleteline&token='.newToken().'&lineid='.$line->id.'" onclick="return confirm(\''.dol_escape_js($langs->trans("ConfirmDeleteLine")).'\');">'.img_delete().'</a>';
			}
			print '</td>';
		}
		print '</tr>';
	}

	print '</table>';
	print '</div>';

	if ($isReturnable) {
		if ($permissiontocheckout) {
			print '<div class="right marginbottomonly"><input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans("VerleihCheckin")).'"></div>';
		}
		print '</form>';
	}

	// Add line form (only while draft) - a separate standalone form below the table
	if ($object->status == VerleihLoan::STATUS_DRAFT && $permissiontoadd) {
		print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'" class="marginbottomonly">';
		print '<input type="hidden" name="id" value="'.$object->id.'">';
		print $langs->trans("VerleihItemType").': '.$form->selectarray('filter_itemtype', array(0 => $langs->trans('VerleihAllItemTypes')) + $itemtypecache, $preferred_itemtype, 0);
		print ' <input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans("Search")).'">';
		print '</form>';

		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="addline">';
		print '<div class="marginbottomonly">';
		print $langs->trans("VerleihChooseItem").': '.$form->selectarray('fk_item', $itemcache, 0, 1).' ';
		print $langs->trans("VerleihChooseStudent").': '.$form->selectarray('fk_student', $studentcache, 0, 1).' ';
		print '<input type="text" name="linenote" class="minwidth150" placeholder="'.dol_escape_htmltag($langs->trans("Note")).'"> ';
		print '<input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans("VerleihAddLine")).'">';
		print '</div>';
		print '</form>';
		print '<div class="opacitymedium">'.$langs->trans("VerleihOnlyAvailableItemsShown");
		if (!empty($preferred_itemtype) && isset($itemtypecache[$preferred_itemtype])) {
			print ' — '.$langs->trans("VerleihFilteredByItemType", $itemtypecache[$preferred_itemtype]);
		}
		print '</div>';
	}
} else {
	print load_fiche_titre($title);
	print '<div class="opacitymedium">'.$langs->trans("NoRecordFound").'</div>';
}

llxFooter();
$db->close();
