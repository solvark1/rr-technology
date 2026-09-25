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
 * \file        htdocs/custom/verleih/index.php
 * \ingroup     verleih
 * \brief       Dashboard for the Verleih module
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
require_once __DIR__.'/class/verleihstudent.class.php';
require_once __DIR__.'/class/verleihloan.class.php';

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

/*
 * View
 */
$arrayofcss = array('/verleih/css/verleih.css');
llxHeader('', $langs->trans("VerleihDashboard"), '', '', 0, 0, '', $arrayofcss);

print load_fiche_titre($langs->trans("VerleihDashboardTitle"), '', 'fa-graduation-cap');

$sql = "SELECT status, COUNT(*) as nb FROM ".$db->prefix()."verleih_item WHERE entity IN (".getEntity('verleihitem').") GROUP BY status";
$resql = $db->query($sql);
$itemcounts = array(0 => 0, 1 => 0, 2 => 0, 3 => 0);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$itemcounts[(int) $obj->status] = (int) $obj->nb;
	}
}

$sql = "SELECT COUNT(*) as nb FROM ".$db->prefix()."verleih_loan WHERE entity IN (".getEntity('verleihloan').") AND status IN (".VerleihLoan::STATUS_ACTIVE.", ".VerleihLoan::STATUS_PARTIAL.")";
$resql = $db->query($sql);
$openloans = 0;
if ($resql && ($obj = $db->fetch_object($resql))) {
	$openloans = (int) $obj->nb;
}

$sql = "SELECT COUNT(*) as nb FROM ".$db->prefix()."verleih_student WHERE entity IN (".getEntity('verleihstudent').") AND status = 1";
$resql = $db->query($sql);
$activestudents = 0;
if ($resql && ($obj = $db->fetch_object($resql))) {
	$activestudents = (int) $obj->nb;
}

$tiles = array(
	array('label' => 'VerleihDashboardItemsInStock', 'value' => $itemcounts[0], 'picto' => 'fa-box', 'url' => 'item_list.php'),
	array('label' => 'VerleihDashboardItemsLoaned', 'value' => $itemcounts[1], 'picto' => 'fa-people-carry', 'url' => 'item_list.php'),
	array('label' => 'VerleihDashboardItemsRepair', 'value' => $itemcounts[2] + $itemcounts[3], 'picto' => 'fa-tools', 'url' => 'item_list.php'),
	array('label' => 'VerleihDashboardOpenLoans', 'value' => $openloans, 'picto' => 'fa-people-carry', 'url' => 'loan_list.php'),
	array('label' => 'VerleihDashboardStudents', 'value' => $activestudents, 'picto' => 'fa-user-graduate', 'url' => 'student_list.php'),
);

print '<div class="vl-stat-grid">';
foreach ($tiles as $tile) {
	print '<a class="vl-stat" href="'.dol_buildpath('/verleih/'.$tile['url'], 1).'">';
	print '<span class="vl-stat-value">'.$tile['value'].'</span>';
	print '<span class="vl-stat-label">'.img_picto('', $tile['picto'], 'class="paddingright"').$langs->trans($tile['label']).'</span>';
	print '</a>';
}
print '</div>';

llxFooter();
$db->close();
