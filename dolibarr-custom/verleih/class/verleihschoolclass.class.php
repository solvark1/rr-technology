<?php
/* Copyright (C) 2026 Kim Wittkowski <kim@wittkowski-it.de>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file        htdocs/custom/verleih/class/verleihschoolclass.class.php
 * \ingroup     verleih
 * \brief       CRUD class for VerleihSchoolClass (Klasse/Jahrgang)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class for VerleihSchoolClass
 */
class VerleihSchoolClass extends CommonObject
{
	/**
	 * @var string ID of module.
	 */
	public $module = 'verleih';

	/**
	 * @var string ID to identify managed object.
	 */
	public $element = 'verleihschoolclass';

	/**
	 * @var string Name of table without prefix where object is stored.
	 */
	public $table_element = 'verleih_schoolclass';

	/**
	 * @var string String with name of icon for this object.
	 */
	public $picto = 'fa-users';

	/**
	 * @var int<0,1> Does object support extrafields ? 0=No, 1=Yes
	 */
	public $isextrafieldmanaged = 1;

	/**
	 * @var int<0,1>|string|null Does this object support multicompany module ?
	 */
	public $ismultientitymanaged = 1;

	const STATUS_INACTIVE = 0;
	const STATUS_ACTIVE = 1;

	/**
	 * @var array<string,array<string,mixed>> Array with all fields and their property.
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1, 'css' => 'left', 'comment' => 'Id'),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => 0, 'default' => '1', 'index' => 1),
		'label' => array('type' => 'varchar(64)', 'label' => 'VerleihClassLabel', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'minwidth200'),
		'schoolyear' => array('type' => 'varchar(16)', 'label' => 'VerleihSchoolYear', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1, 'searchall' => 1, 'css' => 'minwidth100', 'showoncombobox' => 2),
		'note' => array('type' => 'varchar(255)', 'label' => 'Note', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 3, 'css' => 'minwidth300'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'position' => 100, 'notnull' => 1, 'default' => '1', 'visible' => 1, 'index' => 1, 'arrayofkeyval' => array(0 => 'Disabled', 1 => 'Enabled')),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2, 'foreignkey' => '0'),
	);

	public $rowid;
	public $entity;
	public $label;
	public $schoolyear;
	public $note;
	public $status;
	public $date_creation;
	public $tms;
	public $fk_user_creat;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/**
	 * Create object into database
	 *
	 * @param User $user User that creates
	 * @param int<0,1> $notrigger 0=launch triggers after, 1=disable triggers
	 * @return int<-1,max> Return integer <0 if KO, Id of created object if OK
	 */
	public function create(User $user, $notrigger = 0)
	{
		return $this->createCommon($user, $notrigger);
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param int $id Id object
	 * @param string $ref Ref
	 * @return int<-1,1> Return integer <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id, $ref = null)
	{
		return $this->fetchCommon($id, $ref);
	}

	/**
	 * Load list of objects in memory from the database.
	 *
	 * @param string $sortorder Sort Order
	 * @param string $sortfield Sort field
	 * @param int<0,max> $limit Limit
	 * @param int<0,max> $offset Offset
	 * @param string $filter Universal search filter
	 * @return array<int,self>|int<-1,-1> <0 if KO, array of records if OK
	 */
	public function fetchAll($sortorder = '', $sortfield = '', $limit = 1000, $offset = 0, string $filter = '')
	{
		$records = array();

		$sql = "SELECT ".$this->getFieldList('t');
		$sql .= " FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE t.entity IN (".getEntity($this->element).")";

		$errormessage = '';
		$sql .= forgeSQLFromUniversalSearchCriteria($filter, $errormessage);
		if ($errormessage) {
			$this->errors[] = $errormessage;
			return -1;
		}

		if (!empty($sortfield)) {
			$sql .= $this->db->order($sortfield, $sortorder);
		}
		if (!empty($limit)) {
			$sql .= $this->db->plimit($limit, $offset);
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < $num) {
				$obj = $this->db->fetch_object($resql);
				$record = new self($this->db);
				$record->setVarsFromFetchObj($obj);
				$records[$record->id] = $record;
				$i++;
			}
			$this->db->free($resql);
			return $records;
		} else {
			$this->errors[] = 'Error '.$this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Update object into database
	 *
	 * @param User $user User that modifies
	 * @param int<0,1> $notrigger 0=launch triggers after, 1=disable triggers
	 * @return int<-1,1> Return integer <0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = 0)
	{
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Delete object in database
	 *
	 * @param User $user User that deletes
	 * @param int<0,1> $notrigger 0=launch triggers, 1=disable triggers
	 * @return int<-1,1> Return integer <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		return $this->deleteCommon($user, $notrigger);
	}

	/**
	 * Return the label of a given status
	 *
	 * @param int $mode 0=long label, 1=short label, 2=Picto + short label, 3=Picto, 5=Short label + Picto
	 * @return string Label of status
	 */
	public function getLibStatut($mode = 0)
	{
		global $langs;
		$labelStatus = $langs->transnoentitiesnoconv('Enabled');
		$labelStatusShort = $langs->transnoentitiesnoconv('Enabled');
		if ($this->status == self::STATUS_INACTIVE) {
			$labelStatus = $langs->transnoentitiesnoconv('Disabled');
			$labelStatusShort = $langs->transnoentitiesnoconv('Disabled');
		}
		return dolGetStatus($labelStatus, $labelStatusShort, '', ($this->status ? 'status4' : 'status5'), $mode);
	}

	/**
	 * Return a link to the object card
	 *
	 * @param int $withpicto Include picto in link
	 * @param string $option On what the link point to
	 * @return string String with URL
	 */
	public function getNomUrl($withpicto = 0, $option = '')
	{
		$url = dol_buildpath('/verleih/schoolclass_card.php', 1).'?id='.$this->id;
		$result = '<a href="'.$url.'">';
		if ($withpicto) {
			$result .= img_object('', $this->picto, 'class="paddingright"');
		}
		$result .= $this->label;
		$result .= '</a>';
		return $result;
	}

	/**
	 * Return a "kachel" (kanban) view of this record for the list page's tile mode.
	 * Markup follows the Verleih design system (css/verleih.css), same principle as
	 * Dolibarr's native CommonObject::getKanbanView().
	 *
	 * @param string $option Unused, kept for signature compatibility with CommonObject
	 * @param ?array<string,mixed> $arraydata Extra precomputed data: 'selected' (mass-action
	 *        checkbox state), 'studentcount' (int, avoids a per-card query when supplied)
	 * @return string HTML
	 */
	public function getKanbanView($option = '', $arraydata = null)
	{
		global $langs;

		$selected = (empty($arraydata['selected']) ? 0 : $arraydata['selected']);

		$statuschip = $this->status ? 'vl-ok' : 'vl-muted';
		$statuslabel = $this->status ? $langs->trans('Enabled') : $langs->trans('Disabled');

		$return = '<div class="vl-card">';
		$return .= '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">';
		$return .= '<h3>'.$this->getNomUrl(1).'</h3>';
		if ($selected >= 0) {
			$return .= '<input id="cb'.$this->id.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$this->id.'"'.($selected ? ' checked="checked"' : '').'>';
		}
		$return .= '</div>';
		$return .= '<div class="vl-sub">'.dol_escape_htmltag($this->schoolyear).'</div>';
		if (isset($arraydata['studentcount'])) {
			$return .= '<div class="vl-body"><div class="vl-row"><span class="vl-label">'.$langs->trans('VerleihStudents').'</span><span>'.((int) $arraydata['studentcount']).'</span></div></div>';
		}
		$return .= '<div class="vl-chips"><span class="vl-chip '.$statuschip.'">'.dol_escape_htmltag($statuslabel).'</span></div>';
		$return .= '</div>';

		return $return;
	}
}
