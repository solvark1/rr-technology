<?php
// CLI integration test. All database fixtures are rolled back.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('NOLOGIN', 1); define('NOREQUIREMENU', 1); define('NOREQUIREHTML', 1); define('NOREQUIREAJAX', 1);
require '/var/www/html/master.inc.php';
require_once __DIR__.'/../lib/recurring_billing.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
$user = new User($db); $user->fetch('', getenv('DOLI_ADMIN_LOGIN') ?: 'admin'); $user->getrights();
function billingCheck($ok, $label) {
    if (!$ok) { throw new RuntimeException($label); }
    print 'PASS '.$label."\n";
}
$db->begin();
try {
    $soc = new Societe($db); $soc->name = 'RR-BILLING-TEST-'.bin2hex(random_bytes(4));
    $soc->client = 1; $soc->status = 1; $soc->code_client = 'auto';
    billingCheck($soc->create($user) > 0, 'test customer');
    $invoice = new Facture($db); $invoice->socid = $soc->id; $invoice->date = dol_now();
    $invoice->type = 0;
    billingCheck($invoice->create($user) > 0, 'source invoice');
    billingCheck($invoice->addline('Monthly renting test', 100, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, '', 'HT', 0, 1) > 0, 'service invoice line');
    $rec = new FactureRec($db); $rec->title = $soc->name;
    $rec->frequency = 1; $rec->unit_frequency = 'm'; $rec->date_when = dol_now() - 86400;
    $rec->nb_gen_max = 2; $rec->auto_validate = 0; $rec->generate_pdf = 0;
    $terms = $db->fetch_object($db->query('SELECT rowid FROM '.$db->prefix().'c_payment_term WHERE active=1 ORDER BY rowid LIMIT 1'));
    $rec->cond_reglement_id = $terms->rowid;
    $created = $rec->create($user, $invoice->id);
    billingCheck($created > 0, 'monthly template: '.$rec->error.' '.implode('; ', $rec->errors));
    billingCheck(rrRunRecurringBilling($db, $conf->entity, $rec->id) === 1, 'due template processed');
    $res = $db->query('SELECT rowid, fk_statut, total_ht FROM '.$db->prefix().'facture WHERE fk_fac_rec_source='.(int) $rec->id);
    billingCheck($db->num_rows($res) === 1, 'one invoice generated');
    $generated = $db->fetch_object($res);
    billingCheck((int) $generated->fk_statut === 0 && (float) $generated->total_ht === 100.0, 'draft has correct amount');
    billingCheck(rrRunRecurringBilling($db, $conf->entity, $rec->id) === 0, 'repeat pass does not duplicate invoice');
    $table = $db->prefix().'facture_rec';
    $due = "date_when='".$db->idate(dol_now() - 86400)."'";
    $db->query('UPDATE '.$table.' SET '.$due.', suspended=1 WHERE rowid='.(int) $rec->id);
    billingCheck(rrRunRecurringBilling($db, $conf->entity, $rec->id) === 0, 'suspended template skipped');
    $db->query('UPDATE '.$table.' SET suspended=0, auto_validate=2 WHERE rowid='.(int) $rec->id);
    billingCheck(rrRunRecurringBilling($db, $conf->entity, $rec->id) === 0, 'email and validation templates skipped');
    $db->query('UPDATE '.$table.' SET auto_validate=0, nb_gen_done=2 WHERE rowid='.(int) $rec->id);
    billingCheck(rrRunRecurringBilling($db, $conf->entity, $rec->id) === 0, 'completed schedule skipped');
    $db->query('UPDATE '.$table.' SET nb_gen_done=1 WHERE rowid='.(int) $rec->id);
    billingCheck(rrRunRecurringBilling($db, $conf->entity, $rec->id) === 1, 'second period generated');
    $rec->fetch($rec->id);
    billingCheck($rec->isMaxNbGenReached(), 'native schedule ends after last period');
} finally {
    $db->query('ROLLBACK'); $db->transaction_opened = 0; $db->close();
}
