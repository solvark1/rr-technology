<?php
/* R&R Technology adaptation of Verleih. GPL-3.0-or-later. */
function rrRentingMigrate($db)
{
    $p = $db->prefix();
    $tables = array(
        "rr_renting_contract_link" => "fk_booking INT PRIMARY KEY, fk_contract_line INT NOT NULL, fk_product INT NOT NULL, qty INT NOT NULL, KEY idx_rr_contract_line (fk_contract_line)",
        "rr_renting_config" => "entity INT PRIMARY KEY, sale INT NOT NULL, available INT NOT NULL, customer INT NOT NULL, review INT NOT NULL, repair INT NOT NULL",
        "rr_renting_asset" => "rowid INT AUTO_INCREMENT PRIMARY KEY, entity INT NOT NULL, fk_product INT NOT NULL, serial VARCHAR(128) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'available', item_condition VARCHAR(20) NOT NULL DEFAULT 'good', fk_warehouse INT NOT NULL, note VARCHAR(255), date_creation DATETIME NOT NULL, UNIQUE KEY uk_rr_serial (entity,fk_product,serial)",
        "rr_renting_booking" => "rowid INT AUTO_INCREMENT PRIMARY KEY, entity INT NOT NULL, ref VARCHAR(40) NOT NULL, fk_soc INT NOT NULL, fk_contract INT NOT NULL, fk_service INT NOT NULL, date_start DATE NOT NULL, date_end DATE NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'reserved', note VARCHAR(255), fk_user INT NOT NULL, date_creation DATETIME NOT NULL, UNIQUE KEY uk_rr_ref (entity,ref)",
        "rr_renting_line" => "rowid INT AUTO_INCREMENT PRIMARY KEY, fk_booking INT NOT NULL, fk_asset INT NOT NULL, condition_out VARCHAR(20), condition_in VARCHAR(20), date_out DATETIME, date_return DATETIME, UNIQUE KEY uk_rr_booking_asset (fk_booking,fk_asset), KEY idx_rr_asset (fk_asset)",
        "rr_renting_event" => "rowid INT AUTO_INCREMENT PRIMARY KEY, entity INT NOT NULL, fk_asset INT, fk_booking INT, event VARCHAR(32) NOT NULL, note VARCHAR(255), fk_user INT NOT NULL, date_creation DATETIME NOT NULL"
    );
    foreach ($tables as $name => $definition) {
        if (!$db->query("CREATE TABLE IF NOT EXISTS ".$p.$name." (".$definition.") ENGINE=InnoDB")) {
            throw new RuntimeException($db->lasterror());
        }
    }
}
