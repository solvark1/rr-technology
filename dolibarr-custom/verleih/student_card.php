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
 * \file        htdocs/custom/verleih/student_card.php
 * \ingroup     verleih
 * \brief       Card page (create/edit/view) for VerleihStudent
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
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array("verleih@verleih", "other"));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

$object = new VerleihStudent($db);
if ($id > 0) {
	$object->fetch($id);
}

$extrafields = new ExtraFields($db);
$extrafields->fetch_name_optionals_label($object->table_element);

if (!isModEnabled("verleih")) {
	accessforbidden("Module verleih not enabled");
}
if (!$user->hasRight('verleih', 'lire')) {
	accessforbidden();
}
$permissiontoadd = $user->hasRight('verleih', 'creer');
$permissiontodelete = $user->hasRight('verleih', 'supprimer');

// Build class dropdown
$classcache = array();
$classobj = new VerleihSchoolClass($db);
foreach ($classobj->fetchAll('ASC', 'label', 0, 0, '') as $c) {
	$classcache[$c->id] = $c->label.' ('.$c->schoolyear.')';
}

/*
 * Actions
 */
if ($action == 'add' && $permissiontoadd) {
	$object->lastname = GETPOST('lastname', 'alphanohtml');
	$object->firstname = GETPOST('firstname', 'alphanohtml');
	$object->fk_schoolclass = GETPOSTINT('fk_schoolclass') ?: null;
	$object->studentnumber = GETPOST('studentnumber', 'alphanohtml');
	$object->note = GETPOST('note', 'alphanohtml');
	$object->status = GETPOSTINT('status');
	$object->array_options = $extrafields->getOptionalsFromPost($object->table_element);

	if (empty($object->lastname) || empty($object->firstname)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->trans("Lastname")), null, 'errors');
		$action = 'create';
	} else {
		$result = $object->create($user);
		if ($result > 0) {
			header("Location: ".$_SERVER["PHP_SELF"]."?id=".$result);
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
			$action = 'create';
		}
	}
}

if ($action == 'update' && $permissiontoadd && $id > 0) {
	$object->lastname = GETPOST('lastname', 'alphanohtml');
	$object->firstname = GETPOST('firstname', 'alphanohtml');
	$object->fk_schoolclass = GETPOSTINT('fk_schoolclass') ?: null;
	$object->studentnumber = GETPOST('studentnumber', 'alphanohtml');
	$object->note = GETPOST('note', 'alphanohtml');
	$object->status = GETPOSTINT('status');
	$object->array_options = $extrafields->getOptionalsFromPost($object->table_element);

	$result = $object->update($user);
	if ($result > 0) {
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id);
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
		$action = 'edit';
	}
}

if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' && $permissiontodelete && $id > 0) {
	$result = $object->delete($user);
	if ($result > 0) {
		header("Location: ".dol_buildpath('/verleih/student_list.php', 1));
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

/*
 * View
 */
$form = new Form($db);
$title = $langs->trans("VerleihStudent");

llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-verleih page-card');

if ($action == 'create') {
	print load_fiche_titre($langs->trans("New").' - '.$langs->trans("VerleihStudent"), '', 'fa-user-graduate');

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	print dol_get_fiche_head();
	print '<table class="border centpercent">';

	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans("Lastname").'</td><td><input type="text" name="lastname" class="minwidth200" value="'.dol_escape_htmltag(GETPOST('lastname', 'alphanohtml')).'" autofocus></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans("Firstname").'</td><td><input type="text" name="firstname" class="minwidth200" value="'.dol_escape_htmltag(GETPOST('firstname', 'alphanohtml')).'"></td></tr>';
	print '<tr><td>'.$langs->trans("VerleihSchoolClass").'</td><td>'.$form->selectarray('fk_schoolclass', $classcache, GETPOSTINT('fk_schoolclass'), 1).'</td></tr>';
	print '<tr><td>'.$langs->trans("VerleihStudentNumber").'</td><td><input type="text" name="studentnumber" class="minwidth100" value="'.dol_escape_htmltag(GETPOST('studentnumber', 'alphanohtml')).'"></td></tr>';
	print '<tr><td>'.$langs->trans("Note").'</td><td><input type="text" name="note" class="minwidth300" value="'.dol_escape_htmltag(GETPOST('note', 'alphanohtml')).'"></td></tr>';
	print '<tr><td>'.$langs->trans("Status").'</td><td>'.$form->selectarray('status', array(0 => $langs->trans('Disabled'), 1 => $langs->trans('Enabled')), 1).'</td></tr>';

	print $object->showOptionals($extrafields, 'create');

	print '</table>';
	print dol_get_fiche_end();

	print $form->buttonsSaveCancel("Create");
	print '</form>';
} elseif ($action == 'edit' && $object->id > 0) {
	print load_fiche_titre($langs->trans("VerleihStudent"), '', 'fa-user-graduate');

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="'.$object->id.'">';

	print dol_get_fiche_head();
	print '<table class="border centpercent">';

	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans("Lastname").'</td><td><input type="text" name="lastname" class="minwidth200" value="'.dol_escape_htmltag($object->lastname).'"></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans("Firstname").'</td><td><input type="text" name="firstname" class="minwidth200" value="'.dol_escape_htmltag($object->firstname).'"></td></tr>';
	print '<tr><td>'.$langs->trans("VerleihSchoolClass").'</td><td>'.$form->selectarray('fk_schoolclass', $classcache, $object->fk_schoolclass, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans("VerleihStudentNumber").'</td><td><input type="text" name="studentnumber" class="minwidth100" value="'.dol_escape_htmltag($object->studentnumber).'"></td></tr>';
	print '<tr><td>'.$langs->trans("Note").'</td><td><input type="text" name="note" class="minwidth300" value="'.dol_escape_htmltag($object->note).'"></td></tr>';
	print '<tr><td>'.$langs->trans("Status").'</td><td>'.$form->selectarray('status', array(0 => $langs->trans('Disabled'), 1 => $langs->trans('Enabled')), $object->status).'</td></tr>';

	print $object->showOptionals($extrafields, 'edit');

	print '</table>';
	print dol_get_fiche_end();

	print $form->buttonsSaveCancel();
	print '</form>';
} elseif ($object->id > 0) {
	if ($action == 'delete') {
		print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('Delete'), $langs->trans('ConfirmDeleteObject'), 'confirm_delete', '', 0, 1);
	}

	print dol_get_fiche_head(array(), '', $langs->trans("VerleihStudent"), -1, 'fa-user-graduate');

	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">';

	print '<tr><td class="titlefield">'.$langs->trans("Lastname").'</td><td>'.dol_escape_htmltag($object->lastname).'</td></tr>';
	print '<tr><td>'.$langs->trans("Firstname").'</td><td>'.dol_escape_htmltag($object->firstname).'</td></tr>';
	print '<tr><td>'.$langs->trans("VerleihSchoolClass").'</td><td>'.dol_escape_htmltag($object->fk_schoolclass ? ($classcache[$object->fk_schoolclass] ?? '') : '').'</td></tr>';
	print '<tr><td>'.$langs->trans("VerleihStudentNumber").'</td><td>'.dol_escape_htmltag($object->studentnumber).'</td></tr>';
	print '<tr><td>'.$langs->trans("Note").'</td><td>'.dol_escape_htmltag($object->note).'</td></tr>';
	print '<tr><td>'.$langs->trans("Status").'</td><td>'.$object->getLibStatut(4).'</td></tr>';

	print $object->showOptionals($extrafields, 'view');

	print '</table>';
	print '</div>';

	print dol_get_fiche_end();

	print '<div class="tabsAction">';
	if ($permissiontoadd) {
		print dolGetButtonAction('', $langs->trans('Modify'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit&token='.newToken());
	}
	if ($permissiontodelete) {
		print dolGetButtonAction('', $langs->trans('Delete'), 'delete', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete&token='.newToken());
	}
	print '</div>';

	// Items borrowed by this student (all loan lines, current and past)
	print load_fiche_titre($langs->trans("VerleihBorrowedItems"), '', 'fa-box');

	$conditionOptions = array(
		1 => $langs->trans('VerleihConditionNew'),
		2 => $langs->trans('VerleihConditionGood'),
		3 => $langs->trans('VerleihConditionUsable'),
		4 => $langs->trans('VerleihConditionReplace'),
		5 => $langs->trans('VerleihConditionBroken'),
	);

	$sql = "SELECT l.rowid, l.fk_item, l.condition_out, l.condition_in, l.date_return,";
	$sql .= " i.inventorynumber,";
	$sql .= " loan.rowid as loan_id, loan.ref as loan_ref, loan.dateloan";
	$sql .= " FROM ".$db->prefix()."verleih_loan_line as l";
	$sql .= " LEFT JOIN ".$db->prefix()."verleih_item as i ON i.rowid = l.fk_item";
	$sql .= " LEFT JOIN ".$db->prefix()."verleih_loan as loan ON loan.rowid = l.fk_loan";
	$sql .= " WHERE l.fk_student = ".((int) $object->id);
	$sql .= " ORDER BY loan.dateloan DESC, l.rowid DESC";

	$resql = $db->query($sql);

	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("VerleihLoan").'</td>';
	print '<td>'.$langs->trans("VerleihItem").'</td>';
	print '<td class="center">'.$langs->trans("VerleihDateLoan").'</td>';
	print '<td>'.$langs->trans("VerleihConditionOut").'</td>';
	print '<td class="center">'.$langs->trans("Status").'</td>';
	print '<td>'.$langs->trans("VerleihConditionIn").'</td>';
	print '<td class="center">'.$langs->trans("VerleihDateReturn").'</td>';
	print '</tr>';

	$nblines = $resql ? $db->num_rows($resql) : 0;
	if (!$nblines) {
		print '<tr><td colspan="7"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
	}

	if ($resql) {
		while ($lineobj = $db->fetch_object($resql)) {
			print '<tr class="oddeven">';
			print '<td><a href="'.dol_buildpath('/verleih/loan_card.php', 1).'?id='.((int) $lineobj->loan_id).'">'.dol_escape_htmltag($lineobj->loan_ref).'</a></td>';
			print '<td><a href="'.dol_buildpath('/verleih/item_card.php', 1).'?id='.((int) $lineobj->fk_item).'">'.dol_escape_htmltag($lineobj->inventorynumber).'</a></td>';
			print '<td class="center">'.($lineobj->dateloan ? dol_print_date($db->jdate($lineobj->dateloan), 'day') : '').'</td>';
			print '<td>'.dol_escape_htmltag($conditionOptions[(int) $lineobj->condition_out] ?? '').'</td>';
			if (!empty($lineobj->date_return)) {
				print '<td class="center">'.$langs->trans("VerleihLineReturned").'</td>';
				print '<td>'.dol_escape_htmltag($conditionOptions[(int) $lineobj->condition_in] ?? '').'</td>';
				print '<td class="center">'.dol_print_date($db->jdate($lineobj->date_return), 'day').'</td>';
			} else {
				print '<td class="center">'.$langs->trans("VerleihLineOpen").'</td>';
				print '<td></td>';
				print '<td></td>';
			}
			print '</tr>';
		}
	}

	print '</table>';
	print '</div>';
} else {
	print load_fiche_titre($title);
	print '<div class="opacitymedium">'.$langs->trans("NoRecordFound").'</div>';
}

llxFooter();
$db->close();
