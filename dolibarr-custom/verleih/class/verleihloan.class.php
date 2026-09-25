<?php
/* Copyright (C) 2026 Kim Wittkowski <kim@wittkowski-it.de>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file        htdocs/custom/verleih/class/verleihloan.class.php
 * \ingroup     verleih
 * \brief       CRUD + workflow class for VerleihLoan (Ausleihe: Ausgabe/Rücknahme)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
dol_include_once('/verleih/class/verleihschoolclass.class.php', 'VerleihSchoolClass');
dol_include_once('/verleih/class/verleihitem.class.php', 'VerleihItem');
dol_include_once('/verleih/class/verleihstudent.class.php', 'VerleihStudent');

/**
 * Class for VerleihLoan (Ausleihe header)
 */
class VerleihLoan extends CommonObject
{
	/**
	 * @var string ID of module.
	 */
	public $module = 'verleih';

	/**
	 * @var string ID to identify managed object.
	 */
	public $element = 'verleihloan';

	/**
	 * @var string Name of table without prefix where object is stored.
	 */
	public $table_element = 'verleih_loan';

	/**
	 * @var string String with name of icon for this object.
	 */
	public $picto = 'fa-people-carry';

	/**
	 * @var int<0,1> Does object support extrafields ? 0=No, 1=Yes
	 */
	public $isextrafieldmanaged = 0;

	/**
	 * @var int<0,1>|string|null Does this object support multicompany module ?
	 */
	public $ismultientitymanaged = 1;

	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;
	const STATUS_PARTIAL = 2;
	const STATUS_CLOSED = 3;

	/**
	 * @var array<string,array<string,mixed>> Array with all fields and their property.
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1, 'css' => 'left', 'comment' => 'Id'),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => 0, 'default' => '1', 'index' => 1),
		'ref' => array('type' => 'varchar(32)', 'label' => 'Ref', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'noteditable' => 1, 'css' => 'minwidth100'),
		'fk_schoolclass' => array('type' => 'integer:VerleihSchoolClass:verleih/class/verleihschoolclass.class.php:1', 'label' => 'VerleihSchoolClass', 'picto' => 'fa-users', 'enabled' => 1, 'position' => 20, 'notnull' => -1, 'visible' => 1, 'index' => 1, 'css' => 'maxwidth300'),
		'schoolyear' => array('type' => 'varchar(16)', 'label' => 'VerleihSchoolYear', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1, 'css' => 'minwidth100'),
		'dateloan' => array('type' => 'date', 'label' => 'VerleihDateLoan', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'visible' => 1),
		'datereturnplanned' => array('type' => 'date', 'label' => 'VerleihDateReturnPlanned', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'note' => array('type' => 'varchar(255)', 'label' => 'Note', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 3, 'css' => 'minwidth300'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'position' => 100, 'notnull' => 1, 'default' => '0', 'visible' => 1, 'index' => 1, 'arrayofkeyval' => array(0 => 'VerleihLoanStatusDraft', 1 => 'VerleihLoanStatusActive', 2 => 'VerleihLoanStatusPartial', 3 => 'VerleihLoanStatusClosed')),
		'fk_user_loan' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 110, 'notnull' => 0, 'visible' => -2),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2, 'foreignkey' => '0'),
	);

	public $rowid;
	public $entity;
	public $ref;
	public $fk_schoolclass;
	public $schoolyear;
	public $dateloan;
	public $datereturnplanned;
	public $note;
	public $status;
	public $fk_user_loan;
	public $date_creation;
	public $tms;
	public $fk_user_creat;

	/**
	 * @var array<int,object> Lines of this loan, loaded via fetchLines()
	 */
	public $lines = array();

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
		if (empty($this->ref) || $this->ref == '(PROV)') {
			$this->ref = $this->getNextNumRef();
		}
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
		$result = $this->fetchCommon($id, $ref);
		if ($result > 0) {
			$this->fetchLines();
		}
		return $result;
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
	 * Delete object in database (only allowed while draft; lines are deleted first)
	 *
	 * @param User $user User that deletes
	 * @param int<0,1> $notrigger 0=launch triggers, 1=disable triggers
	 * @return int<-1,1> Return integer <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		if ($this->status != self::STATUS_DRAFT) {
			$this->error = 'ErrorLoanNotInDraft';
			return -1;
		}

		$this->db->begin();

		$sql = "DELETE FROM ".$this->db->prefix()."verleih_loan_line WHERE fk_loan = ".((int) $this->id);
		if (!$this->db->query($sql)) {
			$this->errors[] = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		$result = $this->deleteCommon($user, $notrigger);
		if ($result < 0) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Generate the next reference for a new loan (format AL-YYYY-NNNN)
	 *
	 * @return string
	 */
	public function getNextNumRef()
	{
		$year = date('Y');
		$prefix = 'AL-'.$year.'-';

		$sql = "SELECT ref FROM ".$this->db->prefix()."verleih_loan";
		$sql .= " WHERE ref LIKE '".$this->db->escape($prefix)."%' AND entity IN (".getEntity($this->element).")";
		$sql .= " ORDER BY ref DESC";
		$sql .= $this->db->plimit(1);

		$resql = $this->db->query($sql);
		$next = 1;
		if ($resql && $this->db->num_rows($resql)) {
			$obj = $this->db->fetch_object($resql);
			$lastnum = (int) substr($obj->ref, strlen($prefix));
			$next = $lastnum + 1;
		}

		return $prefix.sprintf('%04d', $next);
	}

	/**
	 * Load lines of this loan (item + student assignments) in memory
	 *
	 * @return int<-1,1> Return integer <0 if KO, >0 if OK
	 */
	public function fetchLines()
	{
		$this->lines = array();

		$sql = "SELECT l.rowid, l.fk_loan, l.fk_item, l.fk_student, l.condition_out, l.condition_in, l.date_return, l.fk_user_return, l.note,";
		$sql .= " i.inventorynumber, i.serialnumber, i.fk_itemtype,";
		$sql .= " s.firstname, s.lastname";
		$sql .= " FROM ".$this->db->prefix()."verleih_loan_line as l";
		$sql .= " LEFT JOIN ".$this->db->prefix()."verleih_item as i ON i.rowid = l.fk_item";
		$sql .= " LEFT JOIN ".$this->db->prefix()."verleih_student as s ON s.rowid = l.fk_student";
		$sql .= " WHERE l.fk_loan = ".((int) $this->id);
		$sql .= " ORDER BY l.rowid ASC";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->errors[] = $this->db->lasterror();
			return -1;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$line = new stdClass();
			$line->id = (int) $obj->rowid;
			$line->fk_loan = (int) $obj->fk_loan;
			$line->fk_item = (int) $obj->fk_item;
			$line->fk_student = (int) $obj->fk_student;
			$line->condition_out = (int) $obj->condition_out;
			$line->condition_in = $obj->condition_in !== null ? (int) $obj->condition_in : null;
			$line->date_return = $obj->date_return ? $this->db->jdate($obj->date_return) : null;
			$line->fk_user_return = $obj->fk_user_return ? (int) $obj->fk_user_return : null;
			$line->note = $obj->note;
			$line->item_inventorynumber = $obj->inventorynumber;
			$line->item_serialnumber = $obj->serialnumber;
			$line->student_name = trim($obj->lastname.', '.$obj->firstname);
			$this->lines[] = $line;
		}
		$this->db->free($resql);

		return 1;
	}

	/**
	 * Add a line (one item assigned to one student) to a draft loan
	 *
	 * @param User $user Acting user
	 * @param int $fk_item Id of VerleihItem
	 * @param int $fk_student Id of VerleihStudent
	 * @param string $note Optional note
	 * @return int<-1,max> Id of created line, <0 if KO
	 */
	public function addLine(User $user, $fk_item, $fk_student, $note = '')
	{
		if ($this->status != self::STATUS_DRAFT) {
			$this->error = 'ErrorLoanNotInDraft';
			return -1;
		}

		$item = new VerleihItem($this->db);
		if ($item->fetch($fk_item) <= 0) {
			$this->error = 'ErrorItemNotFound';
			return -1;
		}
		if ($item->status != VerleihItem::STATUS_IN_STOCK) {
			$this->error = 'ErrorItemNotInStock';
			return -1;
		}

		// The item's own status only changes to LOANED at checkout() time, so a draft
		// line does not yet reserve it there. Guard explicitly against the same item
		// being open (not yet returned) in any loan line - this loan's own draft lines
		// included - to prevent the same physical item being handed out twice.
		$sqlcheck = "SELECT rowid FROM ".$this->db->prefix()."verleih_loan_line WHERE fk_item = ".((int) $fk_item)." AND date_return IS NULL";
		$resqlcheck = $this->db->query($sqlcheck);
		if ($resqlcheck && $this->db->num_rows($resqlcheck)) {
			$this->error = 'ErrorItemAlreadyInOpenLoan';
			return -1;
		}

		$sql = "INSERT INTO ".$this->db->prefix()."verleih_loan_line";
		$sql .= " (fk_loan, fk_item, fk_student, condition_out, note, date_creation)";
		$sql .= " VALUES (".((int) $this->id).", ".((int) $fk_item).", ".((int) $fk_student).", ".((int) $item->itemcondition).", '".$this->db->escape($note)."', '".$this->db->idate(dol_now())."')";

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		return $this->db->last_insert_id($this->db->prefix().'verleih_loan_line');
	}

	/**
	 * Delete a line from a draft loan
	 *
	 * @param User $user Acting user
	 * @param int $lineid Id of the line to remove
	 * @return int<-1,1> >0 if OK, <0 if KO
	 */
	public function deleteLine(User $user, $lineid)
	{
		if ($this->status != self::STATUS_DRAFT) {
			$this->error = 'ErrorLoanNotInDraft';
			return -1;
		}

		$sql = "DELETE FROM ".$this->db->prefix()."verleih_loan_line WHERE rowid = ".((int) $lineid)." AND fk_loan = ".((int) $this->id);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 * Checkout: hand out all lines of this loan to the students. Sets each linked
	 * item's status to LOANED and switches the loan itself to ACTIVE.
	 *
	 * @param User $user Acting user
	 * @return int<-1,1> >0 if OK, <0 if KO
	 */
	public function checkout(User $user)
	{
		if ($this->status != self::STATUS_DRAFT) {
			$this->error = 'ErrorLoanNotInDraft';
			return -1;
		}

		$this->fetchLines();
		if (empty($this->lines)) {
			$this->error = 'ErrorLoanHasNoLines';
			return -1;
		}

		$this->db->begin();

		foreach ($this->lines as $line) {
			$item = new VerleihItem($this->db);
			if ($item->fetch($line->fk_item) <= 0) {
				$this->error = 'ErrorItemNotFound';
				$this->db->rollback();
				return -1;
			}
			if ($item->status != VerleihItem::STATUS_IN_STOCK) {
				$this->error = 'ErrorItemNotInStock';
				$this->db->rollback();
				return -1;
			}
			$item->status = VerleihItem::STATUS_LOANED;
			if ($item->update($user) <= 0) {
				$this->error = 'ErrorUpdatingItem';
				$this->db->rollback();
				return -1;
			}
		}

		$this->status = self::STATUS_ACTIVE;
		$this->fk_user_loan = $user->id;
		if (empty($this->dateloan)) {
			$this->dateloan = dol_now();
		}
		if ($this->update($user) <= 0) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Process return for a set of lines: records the returned condition, releases
	 * the item back to stock (or into repair if the reported condition is broken),
	 * and recomputes the loan header status.
	 *
	 * @param User $user Acting user
	 * @param array<int,int> $conditionsByLineId Map of lineid => condition_in
	 * @return int<-1,1> >0 if OK, <0 if KO
	 */
	public function processReturn(User $user, array $conditionsByLineId)
	{
		if (empty($conditionsByLineId)) {
			$this->error = 'VerleihNoItemsSelected';
			return -1;
		}

		$this->db->begin();

		foreach ($conditionsByLineId as $lineid => $conditionIn) {
			$lineid = (int) $lineid;
			$conditionIn = (int) $conditionIn;

			$sql = "SELECT fk_item, fk_loan, date_return FROM ".$this->db->prefix()."verleih_loan_line WHERE rowid = ".$lineid." AND fk_loan = ".((int) $this->id);
			$resql = $this->db->query($sql);
			if (!$resql || !$this->db->num_rows($resql)) {
				continue;
			}
			$obj = $this->db->fetch_object($resql);
			if (!empty($obj->date_return)) {
				continue; // already returned
			}

			$sqlupd = "UPDATE ".$this->db->prefix()."verleih_loan_line SET";
			$sqlupd .= " condition_in = ".$conditionIn.",";
			$sqlupd .= " date_return = '".$this->db->idate(dol_now())."',";
			$sqlupd .= " fk_user_return = ".((int) $user->id);
			$sqlupd .= " WHERE rowid = ".$lineid;
			if (!$this->db->query($sqlupd)) {
				$this->error = $this->db->lasterror();
				$this->db->rollback();
				return -1;
			}

			$item = new VerleihItem($this->db);
			if ($item->fetch($obj->fk_item) > 0) {
				$item->itemcondition = $conditionIn;
				$item->status = ($conditionIn == VerleihItem::CONDITION_BROKEN) ? VerleihItem::STATUS_REPAIR : VerleihItem::STATUS_IN_STOCK;
				if ($item->update($user) <= 0) {
					$this->error = 'ErrorUpdatingItem';
					$this->db->rollback();
					return -1;
				}
			}
		}

		// Recompute header status
		$this->fetchLines();
		$total = count($this->lines);
		$returned = 0;
		foreach ($this->lines as $line) {
			if (!empty($line->date_return)) {
				$returned++;
			}
		}
		if ($total > 0 && $returned == $total) {
			$this->status = self::STATUS_CLOSED;
		} elseif ($returned > 0) {
			$this->status = self::STATUS_PARTIAL;
		}
		if ($this->update($user) <= 0) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
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
		$statusLabels = array(
			self::STATUS_DRAFT => 'VerleihLoanStatusDraft',
			self::STATUS_ACTIVE => 'VerleihLoanStatusActive',
			self::STATUS_PARTIAL => 'VerleihLoanStatusPartial',
			self::STATUS_CLOSED => 'VerleihLoanStatusClosed',
		);
		$statusType = array(
			self::STATUS_DRAFT => 'status0',
			self::STATUS_ACTIVE => 'status4',
			self::STATUS_PARTIAL => 'status3',
			self::STATUS_CLOSED => 'status6',
		);
		$label = $langs->transnoentitiesnoconv($statusLabels[$this->status] ?? 'Unknown');
		return dolGetStatus($label, $label, '', $statusType[$this->status] ?? 'status0', $mode);
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
		$url = dol_buildpath('/verleih/loan_card.php', 1).'?id='.$this->id;
		$result = '<a href="'.$url.'">';
		if ($withpicto) {
			$result .= img_object('', $this->picto, 'class="paddingright"');
		}
		$result .= $this->ref;
		$result .= '</a>';
		return $result;
	}

	/**
	 * Return a "kachel" (kanban) view of this record for the list page's tile mode.
	 *
	 * @param string $option Unused, kept for signature compatibility with CommonObject
	 * @param ?array<string,mixed> $arraydata Extra precomputed data: 'selected' (mass-action
	 *        checkbox state), 'classlabel' and 'linecount' (avoid per-card queries when supplied)
	 * @return string HTML
	 */
	public function getKanbanView($option = '', $arraydata = null)
	{
		global $langs;

		$selected = (empty($arraydata['selected']) ? 0 : $arraydata['selected']);

		$statusChipClass = array(
			self::STATUS_DRAFT => 'vl-muted',
			self::STATUS_ACTIVE => 'vl-accent',
			self::STATUS_PARTIAL => 'vl-warn',
			self::STATUS_CLOSED => 'vl-ok',
		);
		$statusLabels = array(
			self::STATUS_DRAFT => 'VerleihLoanStatusDraft',
			self::STATUS_ACTIVE => 'VerleihLoanStatusActive',
			self::STATUS_PARTIAL => 'VerleihLoanStatusPartial',
			self::STATUS_CLOSED => 'VerleihLoanStatusClosed',
		);

		$return = '<div class="vl-card">';
		$return .= '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">';
		$return .= '<h3>'.$this->getNomUrl(1).'</h3>';
		if ($selected >= 0) {
			$return .= '<input id="cb'.$this->id.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$this->id.'"'.($selected ? ' checked="checked"' : '').'>';
		}
		$return .= '</div>';
		$classlabel = $arraydata['classlabel'] ?? null;
		if ($classlabel === null && !empty($this->fk_schoolclass)) {
			$classobj = new VerleihSchoolClass($this->db);
			$classlabel = ($classobj->fetch($this->fk_schoolclass) > 0) ? $classobj->label : '';
		}
		$return .= '<div class="vl-sub">'.dol_escape_htmltag(trim(($classlabel ?? '').' '.$this->schoolyear)).'</div>';
		$return .= '<div class="vl-body">';
		$return .= '<div class="vl-row"><span class="vl-label">'.$langs->trans('VerleihDateLoan').'</span><span>'.($this->dateloan ? dol_print_date($this->dateloan, 'day') : '').'</span></div>';
		$linecount = $arraydata['linecount'] ?? null;
		if ($linecount === null) {
			$sqllc = "SELECT COUNT(*) as nb FROM ".$this->db->prefix()."verleih_loan_line WHERE fk_loan = ".((int) $this->id);
			$resqllc = $this->db->query($sqllc);
			$linecount = ($resqllc && ($objlc = $this->db->fetch_object($resqllc))) ? (int) $objlc->nb : 0;
		}
		$return .= '<div class="vl-row"><span class="vl-label">'.$langs->trans('VerleihLoanLines').'</span><span>'.((int) $linecount).'</span></div>';
		$return .= '</div>';
		$return .= '<div class="vl-chips"><span class="vl-chip '.($statusChipClass[$this->status] ?? 'vl-muted').'">'.dol_escape_htmltag($langs->trans($statusLabels[$this->status] ?? 'Unknown')).'</span></div>';
		$return .= '</div>';

		return $return;
	}
}
