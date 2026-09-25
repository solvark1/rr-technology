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
 * \file        htdocs/custom/verleih/itemtype_card.php
 * \ingroup     verleih
 * \brief       Card page (create/edit/view) for VerleihItemType
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

$object = new VerleihItemType($db);
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
	$object->ref = GETPOST('ref', 'alphanohtml');
	$object->label = GETPOST('label', 'alphanohtml');
	$object->itemcategory = GETPOST('itemcategory', 'alphanohtml');
	$object->manufacturer = GETPOST('manufacturer', 'alphanohtml');
	$object->description = GETPOST('description', 'restricthtml');
	$object->status = GETPOSTINT('status');
	$object->array_options = $extrafields->getOptionalsFromPost($object->table_element);

	if (empty($object->ref) || empty($object->label)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->trans("Ref")), null, 'errors');
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
	$object->ref = GETPOST('ref', 'alphanohtml');
	$object->label = GETPOST('label', 'alphanohtml');
	$object->itemcategory = GETPOST('itemcategory', 'alphanohtml');
	$object->manufacturer = GETPOST('manufacturer', 'alphanohtml');
	$object->description = GETPOST('description', 'restricthtml');
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
		header("Location: ".dol_buildpath('/verleih/itemtype_list.php', 1));
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

/*
 * View
 */
$form = new Form($db);
$title = $langs->trans("VerleihItemType");

llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-verleih page-card');

if ($action == 'create') {
	print load_fiche_titre($langs->trans("New").' - '.$langs->trans("VerleihItemType"), '', 'fa-tablet-alt');

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	print dol_get_fiche_head();
	print '<table class="border centpercent">';

	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans("Ref").'</td><td><input type="text" name="ref" class="minwidth150" value="'.dol_escape_htmltag(GETPOST('ref', 'alphanohtml')).'" autofocus></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans("Label").'</td><td><input type="text" name="label" class="minwidth300" value="'.dol_escape_htmltag(GETPOST('label', 'alphanohtml')).'"></td></tr>';
	print '<tr><td>'.$langs->trans("VerleihItemCategory").'</td><td><input type="text" name="itemcategory" class="minwidth150" placeholder="Tablet, Buch, Werkzeug, ..." value="'.dol_escape_htmltag(GETPOST('itemcategory', 'alphanohtml')).'"></td></tr>';
	print '<tr><td>'.$langs->trans("VerleihManufacturer").'</td><td><input type="text" name="manufacturer" class="minwidth150" value="'.dol_escape_htmltag(GETPOST('manufacturer', 'alphanohtml')).'"></td></tr>';
	print '<tr><td>'.$langs->trans("Description").'</td><td><textarea name="description" class="quatrevingtpercent" rows="3">'.dol_escape_htmltag(GETPOST('description', 'restricthtml')).'</textarea></td></tr>';
	print '<tr><td>'.$langs->trans("Status").'</td><td>'.$form->selectarray('status', array(0 => $langs->trans('Disabled'), 1 => $langs->trans('Enabled')), 1).'</td></tr>';

	print $object->showOptionals($extrafields, 'create');

	print '</table>';
	print dol_get_fiche_end();

	print $form->buttonsSaveCancel("Create");
	print '</form>';
} elseif ($action == 'edit' && $object->id > 0) {
	print load_fiche_titre($langs->trans("VerleihItemType"), '', 'fa-tablet-alt');

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="'.$object->id.'">';

	print dol_get_fiche_head();
	print '<table class="border centpercent">';

	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans("Ref").'</td><td><input type="text" name="ref" class="minwidth150" value="'.dol_escape_htmltag($object->ref).'"></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans("Label").'</td><td><input type="text" name="label" class="minwidth300" value="'.dol_escape_htmltag($object->label).'"></td></tr>';
	print '<tr><td>'.$langs->trans("VerleihItemCategory").'</td><td><input type="text" name="itemcategory" class="minwidth150" value="'.dol_escape_htmltag($object->itemcategory).'"></td></tr>';
	print '<tr><td>'.$langs->trans("VerleihManufacturer").'</td><td><input type="text" name="manufacturer" class="minwidth150" value="'.dol_escape_htmltag($object->manufacturer).'"></td></tr>';
	print '<tr><td>'.$langs->trans("Description").'</td><td><textarea name="description" class="quatrevingtpercent" rows="3">'.dol_escape_htmltag($object->description).'</textarea></td></tr>';
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

	print dol_get_fiche_head(array(), '', $langs->trans("VerleihItemType"), -1, 'fa-tablet-alt');

	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">';

	print '<tr><td class="titlefield">'.$langs->trans("Ref").'</td><td>'.dol_escape_htmltag($object->ref).'</td></tr>';
	print '<tr><td>'.$langs->trans("Label").'</td><td>'.dol_escape_htmltag($object->label).'</td></tr>';
	print '<tr><td>'.$langs->trans("VerleihItemCategory").'</td><td>'.dol_escape_htmltag($object->itemcategory).'</td></tr>';
	print '<tr><td>'.$langs->trans("VerleihManufacturer").'</td><td>'.dol_escape_htmltag($object->manufacturer).'</td></tr>';
	print '<tr><td>'.$langs->trans("Description").'</td><td>'.dol_htmlentitiesbr($object->description).'</td></tr>';
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
} else {
	print load_fiche_titre($title);
	print '<div class="opacitymedium">'.$langs->trans("NoRecordFound").'</div>';
}

llxFooter();
$db->close();
