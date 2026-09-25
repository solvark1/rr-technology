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
 * \file        htdocs/custom/verleih/schoolclass_card.php
 * \ingroup     verleih
 * \brief       Card page (create/edit/view) for VerleihSchoolClass
 */

// Load Dolibarr environment
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
$backtopage = GETPOST('backtopage', 'alpha');

$object = new VerleihSchoolClass($db);
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

/*
 * Actions
 */
if ($action == 'add' && $permissiontoadd) {
	$object->label = GETPOST('label', 'alphanohtml');
	$object->schoolyear = GETPOST('schoolyear', 'alphanohtml');
	$object->note = GETPOST('note', 'alphanohtml');
	$object->status = GETPOSTINT('status');
	$object->array_options = $extrafields->getOptionalsFromPost($object->table_element);

	if (empty($object->label) || empty($object->schoolyear)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->trans("VerleihClassLabel")), null, 'errors');
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
	$object->label = GETPOST('label', 'alphanohtml');
	$object->schoolyear = GETPOST('schoolyear', 'alphanohtml');
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
		header("Location: ".dol_buildpath('/verleih/schoolclass_list.php', 1));
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

/*
 * View
 */
$form = new Form($db);
$title = $langs->trans("VerleihSchoolClass");

llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-verleih page-card');

if ($action == 'create') {
	print load_fiche_titre($langs->trans("New").' - '.$langs->trans("VerleihSchoolClass"), '', 'fa-users');

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	print dol_get_fiche_head();
	print '<table class="border centpercent">';

	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans("VerleihClassLabel").'</td><td><input type="text" name="label" class="minwidth200" value="'.dol_escape_htmltag(GETPOST('label', 'alphanohtml')).'" autofocus></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans("VerleihSchoolYear").'</td><td><input type="text" name="schoolyear" class="minwidth100" placeholder="2026/2027" value="'.dol_escape_htmltag(GETPOST('schoolyear', 'alphanohtml')).'"></td></tr>';
	print '<tr><td>'.$langs->trans("Note").'</td><td><input type="text" name="note" class="minwidth300" value="'.dol_escape_htmltag(GETPOST('note', 'alphanohtml')).'"></td></tr>';
	print '<tr><td>'.$langs->trans("Status").'</td><td>'.$form->selectarray('status', array(0 => $langs->trans('Disabled'), 1 => $langs->trans('Enabled')), 1).'</td></tr>';

	print $object->showOptionals($extrafields, 'create');

	print '</table>';
	print dol_get_fiche_end();

	print $form->buttonsSaveCancel("Create");
	print '</form>';
} elseif ($action == 'edit' && $object->id > 0) {
	print load_fiche_titre($langs->trans("VerleihSchoolClass"), '', 'fa-users');

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="'.$object->id.'">';

	print dol_get_fiche_head();
	print '<table class="border centpercent">';

	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans("VerleihClassLabel").'</td><td><input type="text" name="label" class="minwidth200" value="'.dol_escape_htmltag($object->label).'"></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans("VerleihSchoolYear").'</td><td><input type="text" name="schoolyear" class="minwidth100" value="'.dol_escape_htmltag($object->schoolyear).'"></td></tr>';
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

	print dol_get_fiche_head(array(), '', $langs->trans("VerleihSchoolClass"), -1, 'fa-users');

	$linkback = '<a href="'.dol_buildpath('/verleih/schoolclass_list.php', 1).'">'.$langs->trans("BackToList").'</a>';

	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">';

	print '<tr><td class="titlefield">'.$langs->trans("VerleihClassLabel").'</td><td>'.dol_escape_htmltag($object->label).'</td></tr>';
	print '<tr><td>'.$langs->trans("VerleihSchoolYear").'</td><td>'.dol_escape_htmltag($object->schoolyear).'</td></tr>';
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

	// Students of this class
	print load_fiche_titre($langs->trans("VerleihStudents"), '', 'fa-user-graduate');

	require_once __DIR__.'/class/verleihstudent.class.php';
	$studentobj = new VerleihStudent($db);
	$classstudents = $studentobj->fetchAll('ASC', 'lastname', 0, 0, '(fk_schoolclass:=:'.((int) $object->id).')');
	if (!is_array($classstudents)) {
		$classstudents = array();
	}

	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("Name").'</td>';
	print '<td>'.$langs->trans("VerleihStudentNumber").'</td>';
	print '<td class="center">'.$langs->trans("Status").'</td>';
	print '</tr>';

	if (empty($classstudents)) {
		print '<tr><td colspan="3"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
	}

	foreach ($classstudents as $st) {
		print '<tr class="oddeven">';
		print '<td>'.$st->getNomUrl(1).'</td>';
		print '<td>'.dol_escape_htmltag($st->studentnumber).'</td>';
		print '<td class="center">'.$st->getLibStatut(5).'</td>';
		print '</tr>';
	}

	print '</table>';
	print '</div>';
} else {
	print load_fiche_titre($title);
	print '<div class="opacitymedium">'.$langs->trans("NoRecordFound").'</div>';
}

llxFooter();
$db->close();
