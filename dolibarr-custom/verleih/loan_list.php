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
 * \file        htdocs/custom/verleih/loan_list.php
 * \ingroup     verleih
 * \brief       List page for VerleihLoan (classic table + Kachel/kanban mode)
 *
 * Follows the structure of htdocs/modulebuilder/template/myobject_list.php - see
 * css_changes.md section "Alles angleichen" for the rationale. Deletion (single or mass)
 * is refused by VerleihLoan::delete() itself unless the loan is still a draft.
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

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array("verleih@verleih", "other"));

$action = GETPOST('action', 'aZ09') ? GETPOST('action', 'aZ09') : 'list';
$massaction = GETPOST('massaction', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$toselect = GETPOST('toselect', 'array:int');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'verleihloanlist';
$backtopage = GETPOST('backtopage', 'alpha');
$optioncss = GETPOST('optioncss', 'aZ');

// View mode: classic table (default) or modern tile view ("kanban"), see css_changes.md.
$mode = GETPOST('mode', 'aZ');
if ($mode === '') {
	$mode = $_SESSION['VERLEIH_VIEWMODE'] ?? '';
} else {
	$_SESSION['VERLEIH_VIEWMODE'] = $mode;
}

$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$page = 0;
}
$offset = $limit * $page;

$object = new VerleihLoan($db);
$hookmanager->initHooks(array($contextpage));

if (!$sortfield) {
	$sortfield = "t.ref";
}
if (!$sortorder) {
	$sortorder = "DESC";
}

$search_all = trim(GETPOST('search_all', 'alphanohtml'));
$search = array();
foreach ($object->fields as $key => $val) {
	if (GETPOST('search_'.$key, 'alpha') !== '') {
		$search[$key] = GETPOST('search_'.$key, 'alpha');
	}
}

$fieldstosearchall = array();
foreach ($object->fields as $key => $val) {
	if (!empty($val['searchall'])) {
		$fieldstosearchall['t.'.$key] = $val['label'];
	}
}

$arrayfields = array();
foreach ($object->fields as $key => $val) {
	if (!empty($val['visible'])) {
		$visible = (int) dol_eval((string) $val['visible'], 1);
		$arrayfields['t.'.$key] = array(
			'label' => $val['label'],
			'checked' => (($visible < 0) ? '0' : '1'),
			'enabled' => (string) (int) (abs($visible) != 3 && (bool) dol_eval((string) $val['enabled'], 1)),
			'position' => $val['position'],
			'help' => isset($val['help']) ? $val['help'] : ''
		);
	}
}
$object->fields = dol_sort_array($object->fields, 'position');
$arrayfields = dol_sort_array($arrayfields, 'position');

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
if (GETPOST('cancel', 'alpha')) {
	$action = 'list';
	$massaction = '';
}
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') {
	$massaction = '';
}

$parameters = array('arrayfields' => &$arrayfields);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
		foreach ($object->fields as $key => $val) {
			$search[$key] = '';
		}
		$search_all = '';
		$toselect = array();
	}
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
		|| GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha')) {
		$massaction = '';
	}

	// Mass/single delete goes through VerleihLoan::delete(), which itself refuses
	// non-draft loans (see class file) - no extra status check needed here.
	$objectclass = 'VerleihLoan';
	$objectlabel = 'VerleihLoan';
	$uploaddir = $conf->verleih->dir_output ?? DOL_DATA_ROOT.'/verleih';
	global $error;
	include DOL_DOCUMENT_ROOT.'/core/actions_massactions.inc.php';
}

/*
 * View
 */
$form = new Form($db);
$title = $langs->trans("VerleihLoans");
$arrayofcss = array('/verleih/css/verleih.css');

llxHeader('', $title, '', '', 0, 0, '', $arrayofcss, '', 'mod-verleih page-list bodyforlist');

$sql = "SELECT ";
$sql .= $object->getFieldList('t');
$sqlfields = $sql;
$sql .= " FROM ".$db->prefix().$object->table_element." as t";
$sql .= " WHERE t.entity IN (".getEntity($object->element).")";

foreach ($search as $key => $val) {
	if (array_key_exists($key, $object->fields) && $search[$key] !== '') {
		$field_spec = $object->fields[$key];
		// "-1" is the placeholder value for "no selection / show all" on select-based
		// filters (arrayofkeyval and FK dropdowns) - a fully untouched filter form still
		// submits it, so it must be skipped rather than turned into an impossible
		// "field IN (-1)" condition that always excludes every row.
		if (((strpos($field_spec['type'], 'integer:') === 0) || !empty($field_spec['arrayofkeyval'])) && $search[$key] == '-1') {
			$search[$key] = '';
			continue;
		}
		$mode_search = (($object->isInt($field_spec) || $object->isFloat($field_spec)) ? 1 : 0);
		if ((strpos($field_spec['type'], 'integer:') === 0) || !empty($field_spec['arrayofkeyval'])) {
			$mode_search = 2;
		}
		$sql .= natural_search("t.".$db->escape($key), $search[$key], (($key == 'status') ? 2 : $mode_search));
	}
}
if ($search_all) {
	$sql .= natural_search(array_keys($fieldstosearchall), $search_all);
}

$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListWhere', $parameters, $object, $action);
$sql .= $hookmanager->resPrint;

$nbtotalofrecords = '';
if (!getDolGlobalInt('MAIN_DISABLE_FULL_SCANLIST')) {
	$sqlforcount = preg_replace('/^'.preg_quote($sqlfields, '/').'/', 'SELECT COUNT(*) as nbtotalofrecords', $sql);
	$resqlforcount = $db->query($sqlforcount);
	if ($resqlforcount) {
		$objforcount = $db->fetch_object($resqlforcount);
		$nbtotalofrecords = $objforcount->nbtotalofrecords;
	}
	if (($page * $limit) > $nbtotalofrecords) {
		$page = 0;
		$offset = 0;
	}
	$db->free($resqlforcount);
}

$sql .= $db->order($sortfield, $sortorder);
if ($limit) {
	$sql .= $db->plimit($limit + 1, $offset);
}

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}
$num = $db->num_rows($resql);

$arrayofselected = is_array($toselect) ? $toselect : array();

$param = '';
if (!empty($mode)) {
	$param .= '&mode='.urlencode($mode);
}
if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
	$param .= '&contextpage='.urlencode($contextpage);
}
if ($limit > 0 && $limit != $conf->liste_limit) {
	$param .= '&limit='.((int) $limit);
}
foreach ($search as $key => $val) {
	if ($search[$key] != '') {
		$param .= '&search_'.$key.'='.urlencode($search[$key]);
	}
}

$arrayofmassactions = array();
if ($permissiontodelete) {
	$arrayofmassactions['predelete'] = img_picto('', 'delete', 'class="pictofixedwidth"').$langs->trans("Delete");
}
if (GETPOSTINT('nomassaction') || in_array($massaction, array('presend', 'predelete'))) {
	$arrayofmassactions = array();
}
$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">'."\n";
if ($optioncss != '') {
	print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
}
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="mode" value="'.dol_escape_htmltag($mode).'">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';
print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';

$modeparam = preg_replace('/&mode=[^&]*/', '', $param);
$newcardbutton = '';
$newcardbutton .= dolGetButtonTitle($langs->trans('ViewList'), '', 'fa fa-bars imgforviewmode', $_SERVER["PHP_SELF"].'?mode=list'.$modeparam, '', ($mode != 'kanban' ? 2 : 1), array('morecss' => 'reposition'));
$newcardbutton .= dolGetButtonTitle($langs->trans('ViewKanban'), '', 'fa fa-th-large imgforviewmode', $_SERVER["PHP_SELF"].'?mode=kanban'.$modeparam, '', ($mode == 'kanban' ? 2 : 1), array('morecss' => 'reposition'));
$newcardbutton .= dolGetButtonTitleSeparator();
$newcardbutton .= dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', dol_buildpath('/verleih/loan_card.php', 1).'?action=create&backtopage='.urlencode($_SERVER['PHP_SELF'].($param ? '?'.$param : '')), '', $permissiontoadd);

print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, $object->picto, 0, $newcardbutton, '', $limit, 0, 0, 1);

// Confirmation dialog for the mass-delete action ($massaction=='predelete' -> confirm -> 'delete').
// Without this include, choosing "Delete" in the mass-action dropdown only ever sends
// massaction=predelete, which actions_massactions.inc.php does not act on by itself.
include DOL_DOCUMENT_ROOT.'/core/tpl/massactions_pre.tpl.php';

if ($search_all) {
	print '<div class="divsearchfieldfilter">'.$langs->trans("FilterOnInto", $search_all).implode(', ', $fieldstosearchall).'</div>'."\n";
}

$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
$htmlofselectarray = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage, $conf->main_checkbox_left_column);
$selectedfields = (($mode != 'kanban') ? $htmlofselectarray : '');
$selectedfields .= (count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal noborder liste">'."\n";

print '<tr class="liste_titre_filter">';
if ($conf->main_checkbox_left_column) {
	print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons('left').'</td>';
}
foreach ($object->fields as $key => $val) {
	if (empty($arrayfields['t.'.$key]['checked'])) {
		continue;
	}
	$cssforfield = (empty($val['csslist']) ? (empty($val['css']) ? '' : $val['css']) : $val['csslist']);
	if ($key == 'status') {
		$cssforfield .= ($cssforfield ? ' ' : '').'center';
	} elseif (in_array($val['type'], array('date', 'datetime', 'timestamp'))) {
		$cssforfield .= ($cssforfield ? ' ' : '').'center';
	}
	print '<td class="liste_titre'.($cssforfield ? ' '.$cssforfield : '').'">';
	if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
		print $form->selectarray('search_'.$key, $val['arrayofkeyval'], (isset($search[$key]) ? $search[$key] : ''), 1, 0, 0, '', 1, 0, 0, '', 'maxwidth100'.($key == 'status' ? ' search_status width100 onrightofpage' : ''), 1);
	} elseif (strpos($val['type'], 'integer:') === 0) {
		print $object->showInputField($val, $key, (isset($search[$key]) ? $search[$key] : ''), '', '', 'search_', $cssforfield.' maxwidth250', 1);
	} else {
		print $object->showInputField($val, $key, (isset($search[$key]) ? $search[$key] : ''), '', '', 'search_', $cssforfield.' maxwidth150', 1);
	}
	print '</td>';
}
if (!$conf->main_checkbox_left_column) {
	print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons().'</td>';
}
print '</tr>'."\n";

print '<tr class="liste_titre">';
if ($conf->main_checkbox_left_column) {
	print getTitleFieldOfList($selectedfields, 0, $_SERVER["PHP_SELF"], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
}
foreach ($object->fields as $key => $val) {
	if (empty($arrayfields['t.'.$key]['checked'])) {
		continue;
	}
	$cssforfield = (empty($val['csslist']) ? (empty($val['css']) ? '' : $val['css']) : $val['csslist']);
	if ($key == 'status') {
		$cssforfield .= ($cssforfield ? ' ' : '').'center';
	} elseif (in_array($val['type'], array('date', 'datetime', 'timestamp'))) {
		$cssforfield .= ($cssforfield ? ' ' : '').'center';
	}
	$cssforfield = preg_replace('/small\s*/', '', $cssforfield);
	print getTitleFieldOfList($arrayfields['t.'.$key]['label'], 0, $_SERVER['PHP_SELF'], 't.'.$key, '', $param, ($cssforfield ? 'class="'.$cssforfield.'"' : ''), $sortfield, $sortorder, ($cssforfield ? $cssforfield.' ' : ''));
}
if (!$conf->main_checkbox_left_column) {
	print getTitleFieldOfList($selectedfields, 0, $_SERVER["PHP_SELF"], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
}
print '</tr>'."\n";

$i = 0;
$imaxinloop = ($limit ? min($num, $limit) : $num);
$savnbfield = count(array_filter($arrayfields, fn ($f) => !empty($f['checked']))) + 1;
while ($i < $imaxinloop) {
	$obj = $db->fetch_object($resql);
	if (empty($obj)) {
		break;
	}
	$object->setVarsFromFetchObj($obj);

	if ($mode == 'kanban') {
		if ($i == 0) {
			print '<tr class="trkanban"><td colspan="'.$savnbfield.'">';
			print '<div class="vl-grid">';
		}
		$selected = -1;
		if ($massactionbutton || $massaction) {
			$selected = 0;
			if (in_array($object->id, $arrayofselected)) {
				$selected = 1;
			}
		}
		print $object->getKanbanView('', array('selected' => $selected));
		if ($i == ($imaxinloop - 1)) {
			print '</div>';
			print '</td></tr>';
		}
	} else {
		print '<tr data-rowid="'.$object->id.'" class="oddeven">';
		if ($conf->main_checkbox_left_column) {
			print '<td class="nowrap center">';
			if ($massactionbutton || $massaction) {
				$selected = in_array($object->id, $arrayofselected) ? 1 : 0;
				print '<input id="cb'.$object->id.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$object->id.'"'.($selected ? ' checked="checked"' : '').'>';
			}
			print '</td>';
		}
		foreach ($object->fields as $key => $val) {
			if (empty($arrayfields['t.'.$key]['checked'])) {
				continue;
			}
			$cssforfield = (empty($val['csslist']) ? (empty($val['css']) ? '' : $val['css']) : $val['csslist']);
			if ($key == 'status') {
				$cssforfield .= ($cssforfield ? ' ' : '').'center';
			} elseif (in_array($val['type'], array('date', 'datetime', 'timestamp'))) {
				$cssforfield .= ($cssforfield ? ' ' : '').'center';
			}
			print '<td'.($cssforfield ? ' class="'.$cssforfield.'"' : '').'>';
			if ($key == 'status') {
				print $object->getLibStatut(5);
			} else {
				print $object->showOutputField($val, $key, (string) $object->$key, '');
			}
			print '</td>';
		}
		if (!$conf->main_checkbox_left_column) {
			print '<td class="nowrap center">';
			if ($massactionbutton || $massaction) {
				$selected = in_array($object->id, $arrayofselected) ? 1 : 0;
				print '<input id="cb'.$object->id.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$object->id.'"'.($selected ? ' checked="checked"' : '').'>';
			}
			print '</td>';
		}
		print '</tr>'."\n";
	}
	$i++;
}

if ($num == 0) {
	print '<tr><td colspan="'.$savnbfield.'"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
}

$db->free($resql);

print '</table>'."\n";
print '</div>'."\n";
print '</form>'."\n";

llxFooter();
$db->close();
