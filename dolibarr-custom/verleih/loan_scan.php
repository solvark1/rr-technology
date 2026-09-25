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
 * \file        htdocs/custom/verleih/loan_scan.php
 * \ingroup     verleih
 * \brief       "Ausleihen mit Code": select a student, scan the item's barcode
 *              (its inventory number) to assign it - no JavaScript required, a
 *              barcode scanner behaves like a keyboard and submits the form on Enter.
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
require_once __DIR__.'/class/verleihstudent.class.php';
require_once __DIR__.'/class/verleihitem.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array("verleih@verleih", "other"));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$fk_student = GETPOSTINT('fk_student');

$object = new VerleihLoan($db);
if ($object->fetch($id) <= 0) {
	accessforbidden('Loan not found');
}

if (!isModEnabled("verleih")) {
	accessforbidden("Module verleih not enabled");
}
if (!$user->hasRight('verleih', 'ausgeben') && !$user->hasRight('verleih', 'creer')) {
	accessforbidden();
}
if ($object->status != VerleihLoan::STATUS_DRAFT) {
	accessforbidden($langs->trans("ErrorLoanNotInDraft"));
}

// Students to offer: this loan's class if set, otherwise all active students.
$students = array();
$studentobj = new VerleihStudent($db);
if (!empty($object->fk_schoolclass)) {
	$students = $studentobj->fetchAll('ASC', 'lastname', 0, 0, '(status:=:1) AND (fk_schoolclass:=:'.((int) $object->fk_schoolclass).')');
} else {
	$students = $studentobj->fetchAll('ASC', 'lastname', 0, 0, '(status:=:1)');
}
if (!is_array($students)) {
	$students = array();
}

// Students of this loan that already have an open (not yet returned) line.
$object->fetchLines();
$assignedstudents = array(); // studentid => item_inventorynumber
foreach ($object->lines as $line) {
	if (empty($line->date_return)) {
		$assignedstudents[$line->fk_student] = $line->item_inventorynumber;
	}
}

/*
 * Actions
 */
if ($action == 'scan') {
	$barcode = trim(GETPOST('barcode', 'alphanohtml'));
	$scanstudent = GETPOSTINT('fk_student');

	if (empty($scanstudent)) {
		setEventMessages($langs->trans("VerleihScanNoStudentSelected"), null, 'errors');
	} elseif ($barcode === '') {
		// Empty scan (e.g. stray Enter), just redisplay the same student.
	} else {
		$itemobj = new VerleihItem($db);
		$sqlfind = "SELECT rowid FROM ".$db->prefix()."verleih_item WHERE inventorynumber = '".$db->escape($barcode)."' AND entity IN (".getEntity('verleihitem').")";
		$resqlfind = $db->query($sqlfind);
		if (!$resqlfind || !$db->num_rows($resqlfind)) {
			setEventMessages($langs->trans("VerleihScanItemNotFound", $barcode), null, 'errors');
		} else {
			$objfind = $db->fetch_object($resqlfind);
			$itemobj->fetch($objfind->rowid);

			$studentobj2 = new VerleihStudent($db);
			$studentobj2->fetch($scanstudent);

			$result = $object->addLine($user, $itemobj->id, $scanstudent);
			if ($result > 0) {
				setEventMessages($langs->trans("VerleihScanSuccess", $itemobj->inventorynumber, $studentobj2->getFullName()), null, 'mesgs');

				// Auto-advance to the next student of the list without an open line yet.
				$object->fetchLines();
				$assignedstudents[$scanstudent] = $itemobj->inventorynumber;
				$nextstudent = 0;
				foreach ($students as $s) {
					if (!isset($assignedstudents[$s->id])) {
						$nextstudent = $s->id;
						break;
					}
				}
				header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id.($nextstudent ? '&fk_student='.$nextstudent : ''));
				exit;
			} else {
				setEventMessages($langs->trans($object->error), null, 'errors');
			}
		}
	}

	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id.($fk_student ? '&fk_student='.$fk_student : ''));
	exit;
}

/*
 * View
 */
$title = $langs->trans("VerleihScanTitle").' - '.$object->ref;
$arrayofcss = array('/verleih/css/verleih.css');

llxHeader('', $title, '', '', 0, 0, '', $arrayofcss, '', 'mod-verleih page-scan');

print load_fiche_titre($langs->trans("VerleihScanTitle").' — '.$object->ref, '<a href="'.dol_buildpath('/verleih/loan_card.php', 1).'?id='.$id.'">'.$langs->trans("VerleihScanBackToLoan").'</a>', 'fa-barcode');

print '<div class="opacitymedium marginbottomonly">'.$langs->trans("VerleihScanIntro").'</div>';

if (empty($object->fk_schoolclass)) {
	print '<div class="warning marginbottomonly">'.$langs->trans("VerleihScanNoClass").'</div>';
}

$currentstudent = null;
if ($fk_student > 0) {
	foreach ($students as $s) {
		if ($s->id == $fk_student) {
			$currentstudent = $s;
			break;
		}
	}
}

print '<div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;">';

// Left: student list
print '<div style="flex:1 1 320px;min-width:280px;">';
print '<h3 class="opacitymedium">'.$langs->trans("VerleihScanStudentsInClass").'</h3>';
if (empty($students)) {
	print '<div class="vl-empty">'.$langs->trans("NoRecordFound").'</div>';
} else {
	print '<div class="vl-grid" style="grid-template-columns:1fr;">';
	foreach ($students as $s) {
		$isassigned = isset($assignedstudents[$s->id]);
		$iscurrent = ($currentstudent && $s->id == $currentstudent->id);
		print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&fk_student='.$s->id.'" style="text-decoration:none;">';
		print '<div class="vl-card" style="padding:10px 14px;'.($iscurrent ? 'border-color:var(--vl-accent);box-shadow:0 0 0 2px var(--vl-accent-soft);' : '').'">';
		print '<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">';
		print '<span style="color:var(--vl-text);font-weight:600;">'.dol_escape_htmltag($s->getFullName()).'</span>';
		if ($isassigned) {
			print '<span class="vl-chip vl-ok">'.dol_escape_htmltag($assignedstudents[$s->id]).'</span>';
		}
		print '</div>';
		print '</div>';
		print '</a>';
	}
	print '</div>';
}
print '</div>';

// Right: scan panel
print '<div style="flex:2 1 380px;min-width:300px;">';
if (!$currentstudent) {
	print '<div class="vl-card"><div class="vl-empty">'.$langs->trans("VerleihScanNoStudentSelected").'</div></div>';
} else {
	print '<div class="vl-card">';
	print '<div class="vl-sub">'.$langs->trans("VerleihScanCurrentStudent").'</div>';
	print '<h3>'.dol_escape_htmltag($currentstudent->getFullName()).'</h3>';

	if (isset($assignedstudents[$currentstudent->id])) {
		print '<div class="vl-chips"><span class="vl-chip vl-ok">'.$langs->trans("VerleihScanAssigned").': '.dol_escape_htmltag($assignedstudents[$currentstudent->id]).'</span></div>';
	}

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" id="verleih-scan-form" style="margin-top:16px;">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="scan">';
	print '<input type="hidden" name="id" value="'.$id.'">';
	print '<input type="hidden" name="fk_student" value="'.$currentstudent->id.'">';
	print '<label style="display:block;font-weight:600;margin-bottom:6px;">'.$langs->trans("VerleihScanInputLabel").'</label>';
	print '<input type="text" id="verleih-scan-barcode-input" name="barcode" class="flat centpercent" style="font-size:1.3em;padding:10px;" placeholder="'.dol_escape_htmltag($langs->trans("VerleihScanInputPlaceholder")).'" autofocus autocomplete="off">';
	print '<div class="marginbottomonly right" style="margin-top:10px;"><input type="submit" class="button" value="OK"></div>';
	print '</form>';

	print '<div class="center" style="margin-top:10px;">';
	print '<button type="button" class="button" id="verleih-scan-camera-toggle">'.$langs->trans("VerleihEnableCameraButton").'</button>';
	print '</div>';
	print '<div id="verleih-scan-camera-container" class="vl-camera-container" style="display:none;">';
	print '<video id="verleih-scan-camera-video" playsinline></video>';
	print '<div class="vl-camera-overlay"></div>';
	print '</div>';
	print '<div id="verleih-scan-camera-status" class="center"></div>';

	print '</div>';
}
print '</div>';

print '</div>';

llxFooter();
$db->close();

// Camera scanner JS is loaded at the very end (after DOM is complete), same pattern as
// custom/scanproduct/scan.php: Quagga2 is fetched lazily from CDN only when the camera
// button is clicked, so pages/users that never use the camera pay no extra cost.
?>
<script>
var verleihScanLang = {
	enableCamera: "<?php echo dol_escape_js($langs->trans('VerleihEnableCameraButton')); ?>",
	disableCamera: "<?php echo dol_escape_js($langs->trans('VerleihDisableCameraButton')); ?>",
	cameraNotSupported: "<?php echo dol_escape_js($langs->trans('VerleihCameraNotSupported')); ?>",
	cameraPermissionDenied: "<?php echo dol_escape_js($langs->trans('VerleihCameraPermissionDenied')); ?>",
	cameraLibraryError: "<?php echo dol_escape_js($langs->trans('VerleihCameraLibraryError')); ?>",
	cameraInitializing: "<?php echo dol_escape_js($langs->trans('VerleihCameraInitializing')); ?>",
	cameraActive: "<?php echo dol_escape_js($langs->trans('VerleihCameraActive')); ?>",
	cameraDetected: "<?php echo dol_escape_js($langs->trans('VerleihCameraDetected')); ?>"
};
</script>
<script src="<?php echo dol_buildpath('/verleih/js/verleih-scan.js', 1); ?>"></script>

