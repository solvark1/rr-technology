<?php
/* R&R Technology. GPL-3.0-or-later. */
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture-rec.class.php';

/** Generate due native templates in draft mode only. Returns processed template count. */
function rrRunRecurringBilling($db, $entity, $templateId = 0)
{
    $lock = 'rr-recurring-billing-'.(int) $entity;
    $result = $db->query("SELECT GET_LOCK('".$lock."', 0) acquired");
    if (!$result) { throw new RuntimeException($db->lasterror()); }
    if ((int) $db->fetch_object($result)->acquired !== 1) { return 0; }
    try {
        $sql = 'SELECT rowid FROM '.$db->prefix().'facture_rec WHERE entity='.(int) $entity;
        $sql .= ' AND frequency>0 AND suspended=0 AND auto_validate=0';
        $sql .= ' AND date_when IS NOT NULL';
        $sql .= " AND date_when <= '".$db->idate(dol_now())."'";
        $sql .= ' AND (nb_gen_max=0 OR nb_gen_done<nb_gen_max)';
        if ($templateId > 0) { $sql .= ' AND rowid='.(int) $templateId; }
        $result = $db->query($sql);
        if (!$result) { throw new RuntimeException($db->lasterror()); }
        $ids = array();
        while ($row = $db->fetch_object($result)) { $ids[] = (int) $row->rowid; }
        foreach ($ids as $id) {
            $recurring = new FactureRec($db);
            if ($recurring->createRecurringInvoices($id) !== 0) {
                throw new RuntimeException('Template '.$id.': '.$recurring->error.' '.implode('; ', $recurring->errors));
            }
        }
        return count($ids);
    } finally {
        $db->query("SELECT RELEASE_LOCK('".$lock."')");
    }
}
