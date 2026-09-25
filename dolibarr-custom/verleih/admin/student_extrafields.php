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
 * \file        htdocs/custom/verleih/admin/student_extrafields.php
 * \ingroup     verleih
 * \brief       Define extra attributes for VerleihStudent
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

require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('verleih@verleih', 'admin'));

if (!$user->admin && !$user->hasRight('verleih', 'configurer')) {
	accessforbidden();
}

$extrafields = new ExtraFields($db);
$form = new Form($db);

$type2label = ExtraFields::getListOfTypesLabels();

$action = GETPOST('action', 'aZ09');
$attrname = GETPOST('attrname', 'alpha');
$elementtype = 'verleih_student'; // Must match $table_element of VerleihStudent

/*
 * Actions
 */
require DOL_DOCUMENT_ROOT.'/core/actions_extrafields.inc.php';

/*
 * View
 */
$textobject = $langs->transnoentitiesnoconv("VerleihStudent");

llxHeader('', $langs->trans("VerleihSetupPage"), '', '', 0, 0, '', '', '', 'mod-verleih page-admin_extrafields');

$linkback = '<a href="'.dol_buildpath('/verleih/admin/setup.php', 1).'">'.$langs->trans("VerleihBackToSetup").'</a>';
print load_fiche_titre($langs->trans("VerleihStudent"), $linkback, 'fa-user-graduate');

require DOL_DOCUMENT_ROOT.'/core/tpl/admin_extrafields_view.tpl.php';

if ($action == 'create') {
	print '<br><div id="newattrib"></div>';
	print load_fiche_titre($langs->trans('NewAttribute'));
	require DOL_DOCUMENT_ROOT.'/core/tpl/admin_extrafields_add.tpl.php';
}

if ($action == 'edit' && !empty($attrname)) {
	print '<br>';
	print load_fiche_titre($langs->trans('FieldEdition', $attrname));
	require DOL_DOCUMENT_ROOT.'/core/tpl/admin_extrafields_edit.tpl.php';
}

llxFooter();
$db->close();
