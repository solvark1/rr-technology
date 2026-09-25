<?php
// Independent, repeatable bank seed; existing accounts are never reset.
if (PHP_SAPI !== 'cli') { exit(1); }
if (getenv('RR_RENTING_DEMO_SEED') !== '1') { exit(0); }
define('NOLOGIN', 1); define('NOREQUIREMENU', 1); define('NOREQUIREHTML', 1); define('NOREQUIREAJAX', 1);
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
$locked = false;
$lock = 'rr-bank-seed-'.(int) $conf->entity;
$ref = $conf->currency === 'CRC' ? 'RR-BAC-CRC' : 'RR-BAC-DEMO';
try {
    if (!isModEnabled('banque')) { throw new RuntimeException('Bank module must be enabled.'); }
    $user = new User($db);
    if ($user->fetch('', getenv('DOLI_ADMIN_LOGIN') ?: 'admin') <= 0 || !$user->admin) {
        throw new RuntimeException('Configured administrator not found.');
    }
    $user->getrights();
    $res = $db->query("SELECT GET_LOCK('".$lock."',30) acquired");
    if (!$res || (int) $db->fetch_object($res)->acquired !== 1) { throw new RuntimeException('Cannot lock bank seed.'); }
    $locked = true;
    $res = $db->query("SELECT rowid FROM ".$db->prefix()."bank_account WHERE entity=".(int) $conf->entity." AND ref='".$ref."'");
    if (!$res) { throw new RuntimeException($db->lasterror()); }
    if ($db->num_rows($res)) {
        print '[RR-BANK-SEED] '.$ref." already exists; preserved unchanged.\n";
    } else {
        $res = $db->query("SELECT rowid FROM ".$db->prefix()."c_country WHERE code='CR'");
        if (!$res || !($country = $db->fetch_object($res))) { throw new RuntimeException('Country CR missing.'); }
        $account = new Account($db);
        $account->ref = $ref;
        $account->label = 'BAC DEMO - Renting '.$conf->currency;
        $account->bank = 'BAC (DEMO)';
        $account->type = Account::TYPE_CURRENT;
        $account->courant = Account::TYPE_CURRENT;
        $account->status = 0; $account->clos = 0;
        $account->country_id = (int) $country->rowid;
        $account->owner_name = 'R&R Technology';
        $account->owner_country_id = (int) $country->rowid;
        $account->currency_code = $conf->currency;
        $account->balance = 0; $account->date_solde = dol_now();
        $account->rappro = 1;
        $account->comment = 'Cuenta ficticia para pruebas de renting. Sin conexion bancaria real.';
        if ($account->create($user) <= 0) { throw new RuntimeException($account->error.' '.implode('; ', $account->errors)); }
        print '[RR-BANK-SEED] Created '.$ref.' in '.$account->currency_code." with zero initial balance.\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, '[RR-BANK-SEED] '.$error->getMessage()."\n"); exit(1);
} finally {
    if ($locked) { $db->query("SELECT RELEASE_LOCK('".$lock."')"); }
    $db->close();
}
