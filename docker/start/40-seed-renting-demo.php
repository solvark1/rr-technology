<?php

/*
 * R&R Technology Costa Rica
 * Seeder demo para el flujo de Renting.
 *
 * Objetivo:
 * - reutilizar el producto físico RR-PC-001 del Marketplace;
 * - activar trazabilidad por número de serie único;
 * - registrar unidades demo en RR-VENTA;
 * - crear 3 clientes demo (E-sports, empresa y persona individual);
 * - crear el servicio mensual de renting;
 * - crear y validar un contrato por segmento.
 *
 * IMPORTANTE:
 * Este seeder NO inserta directamente en tablas custom del módulo Renting.
 * Eso es intencional: el alta de una unidad en RR-RENTING y las reservas deben
 * pasar por la lógica del módulo para conservar sus validaciones y estados.
 *
 * Está pensado para ejecutarse desde /var/www/scripts/rr-start/ y es idempotente
 * mediante RR_RENTING_DEMO_SEED_VERSION.
 */

if (PHP_SAPI !== 'cli') { exit(1); }
if (getenv('RR_RENTING_DEMO_SEED') !== '1') {
    print "[RR-RENT-SEED] Demo disabled.\n";
    exit(0);
}

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);

require_once '/var/www/html/master.inc.php';

require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/mouvementstock.class.php';
require_once DOL_DOCUMENT_ROOT . '/contrat/class/contrat.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';

$seedVersion = '1';
$adminLogin = getenv('DOLI_ADMIN_LOGIN') ?: 'admin';

function rrRentSeedLog($message)
{
    print '[RR-RENT-SEED] ' . $message . PHP_EOL;
}

function rrRentSeedFail($message)
{
    throw new RuntimeException($message);
}

function rrFetchThirdpartyByRefExt($db, $refExt)
{
    global $conf;

    $sql = "SELECT rowid
            FROM " . MAIN_DB_PREFIX . "societe
            WHERE ref_ext = '" . $db->escape($refExt) . "'
              AND entity = " . ((int) $conf->entity) . "
            LIMIT 1";

    $res = $db->query($sql);
    if (!$res) {
        rrRentSeedFail('No se pudo consultar el cliente: ' . $db->lasterror());
    }

    $obj = $db->fetch_object($res);
    if (!$obj) {
        return null;
    }

    $soc = new Societe($db);
    if ($soc->fetch((int) $obj->rowid) <= 0) {
        rrRentSeedFail('No se pudo cargar el cliente existente.');
    }

    return $soc;
}

function rrEnsureThirdparty($db, $user, array $data)
{
    $existing = rrFetchThirdpartyByRefExt($db, $data['ref_ext']);
    if ($existing) {
        rrRentSeedLog('Cliente existente: ' . $existing->name . ' (#' . $existing->id . ')');
        return $existing;
    }

    $soc = new Societe($db);
    $soc->name = $data['name'];
    $soc->nom = $data['name'];
    $soc->ref_ext = $data['ref_ext'];
    $soc->client = 1;
    $soc->fournisseur = 0;
    $soc->status = 1;
    $soc->code_client = -1;
    $soc->code_fournisseur = -1;
    $soc->address = $data['address'];
    $soc->town = $data['town'];
    $soc->zip = $data['zip'];
    $soc->email = $data['email'];
    $soc->phone = ''; // No asociar telefonos reales a clientes ficticios.
    $soc->note_private = $data['note'];
    $soc->country_code = 'CR';

    $countryId = getCountry('CR', '3');
    if (is_numeric($countryId)) {
        $soc->country_id = (int) $countryId;
    }

    $id = $soc->create($user);
    if ($id <= 0) {
        rrRentSeedFail(
            'No se pudo crear el cliente ' . $data['name'] . ': ' .
            ($soc->error ?: implode(' | ', (array) $soc->errors))
        );
    }

    $soc->fetch($id);
    rrRentSeedLog('Cliente creado: ' . $soc->name . ' (#' . $id . ')');

    return $soc;
}

function rrEnsureWarehouse($db, $ref)
{
    $warehouse = new Entrepot($db);
    $result = $warehouse->fetch(0, $ref);

    if ($result <= 0) {
        rrRentSeedFail(
            "No existe el almacén {$ref}. Primero debe ejecutarse la configuración del módulo Renting."
        );
    }

    rrRentSeedLog("Almacén disponible: {$ref} (#{$warehouse->id})");
    return $warehouse;
}

function rrProductHasAnyStock($db, $productId)
{
    $sql = "SELECT COUNT(*) AS qty
            FROM " . MAIN_DB_PREFIX . "product_stock
            WHERE fk_product = " . ((int) $productId);

    $res = $db->query($sql);
    if (!$res) {
        rrRentSeedFail('No se pudo comprobar el stock actual del producto #' . $productId);
    }

    $obj = $db->fetch_object($res);
    return ((float) ($obj->qty ?? 0)) > 0.000001;
}

function rrEnsureRentableProduct($db, $user)
{
    $product = new Product($db);
    $result = $product->fetch(0, 'RR-PC-001');

    if ($result <= 0) {
        rrRentSeedLog('RR-PC-001 no existe; se creará como producto demo de respaldo.');

        $product = new Product($db);
        $product->ref = 'RR-PC-001';
        $product->label = 'R&R Forge RTX Gaming PC';
        $product->description =
            'PC gaming de alto rendimiento para venta y renting, con GPU dedicada, ' .
            '16 GB de RAM, SSD NVMe y refrigeración optimizada.';
        $product->type = Product::TYPE_PRODUCT;
        $product->status = 1;
        $product->status_buy = 1;
        $product->status_batch = 2; // serial único

        $id = $product->create($user);
        if ($id <= 0) {
            rrRentSeedFail('No se pudo crear RR-PC-001: ' . $product->error);
        }

        $product->fetch($id);

        $priceResult = $product->updatePrice(799000, 'TTC', $user, 13.0);
        if ($priceResult < 0) {
            rrRentSeedFail('No se pudo asignar precio a RR-PC-001: ' . $product->error);
        }

        rrRentSeedLog('Producto físico creado: RR-PC-001 (#' . $id . ')');
        return $product;
    }

    if ((int) $product->status_batch !== 2) {
        if (rrProductHasAnyStock($db, $product->id)) {
            rrRentSeedFail(
                'RR-PC-001 ya tiene stock pero no está configurado con serie única. ' .
                'Regulariza la trazabilidad antes de ejecutar este seeder.'
            );
        }

        $product->status_batch = 2;
        $update = $product->update($product->id, $user);
        if ($update <= 0) {
            rrRentSeedFail('No se pudo activar serie única en RR-PC-001: ' . $product->error);
        }

        $product->fetch($product->id);
        rrRentSeedLog('RR-PC-001 actualizado para gestión por número de serie único.');
    } else {
        rrRentSeedLog('RR-PC-001 ya usa número de serie único.');
    }

    return $product;
}

function rrEnsureService($db, $user)
{
    $service = new Product($db);
    $result = $service->fetch(0, 'RR-RENT-PC-MES');

    if ($result > 0) {
        if ((int) $service->type !== Product::TYPE_SERVICE) {
            rrRentSeedFail('RR-RENT-PC-MES existe pero no es un servicio.');
        }
        rrRentSeedLog('Servicio existente: RR-RENT-PC-MES (#' . $service->id . ')');
        return $service;
    }

    $service = new Product($db);
    $service->ref = 'RR-RENT-PC-MES';
    $service->label = 'Renting mensual de PC Gaming Pro';
    $service->description =
        'Servicio mensual de renting de una PC gaming de alto rendimiento. ' .
        'Incluye uso del equipo durante el periodo contratado, soporte técnico, ' .
        'mantenimiento preventivo y atención de incidencias.';
    $service->type = Product::TYPE_SERVICE;
    $service->status = 1;
    $service->status_buy = 0;

    $id = $service->create($user);
    if ($id <= 0) {
        rrRentSeedFail('No se pudo crear el servicio de renting: ' . $service->error);
    }

    $service->fetch($id);

    // Precio demo académico: ₡115 000 + 13% IVA por mes.
    $priceResult = $service->updatePrice(115000, 'HT', $user, 13.0);
    if ($priceResult < 0) {
        rrRentSeedFail('No se pudo asignar precio al servicio: ' . $service->error);
    }

    $service->fetch($id);
    rrRentSeedLog('Servicio creado: RR-RENT-PC-MES (#' . $id . ')');

    return $service;
}

function rrFindSerialLocation($db, $productId, $serial)
{
    $sql = "SELECT ps.fk_entrepot, pb.qty
            FROM " . MAIN_DB_PREFIX . "product_batch pb
            INNER JOIN " . MAIN_DB_PREFIX . "product_stock ps
                ON ps.rowid = pb.fk_product_stock
            WHERE ps.fk_product = " . ((int) $productId) . "
              AND pb.batch = '" . $db->escape($serial) . "'
            LIMIT 1";

    $res = $db->query($sql);
    if (!$res) {
        rrRentSeedFail('No se pudo consultar la serie ' . $serial . ': ' . $db->lasterror());
    }

    $obj = $db->fetch_object($res);
    if (!$obj) {
        return null;
    }

    return [
        'warehouse_id' => (int) $obj->fk_entrepot,
        'qty' => (float) $obj->qty,
    ];
}

function rrEnsureSerialInSalesWarehouse($db, $user, $product, $warehouse, $serial)
{
    $location = rrFindSerialLocation($db, $product->id, $serial);

    if ($location !== null) {
        rrRentSeedLog(
            "Serie {$serial} ya existe en almacén #{$location['warehouse_id']}; no se duplica."
        );
        return;
    }

    $movement = new MouvementStock($db);
    $result = $movement->reception(
        $user,
        $product->id,
        $warehouse->id,
        1,
        0,
        'Seed demo Renting R&R',
        '',
        '',
        $serial
    );

    if ($result <= 0) {
        rrRentSeedFail(
            "No se pudo registrar la serie {$serial} en RR-VENTA: " .
            ($movement->error ?: implode(' | ', (array) $movement->errors))
        );
    }

    rrRentSeedLog("Unidad creada en RR-VENTA: {$serial}");
}

function rrFetchContractByRef($db, $ref)
{
    global $conf;

    $sql = "SELECT rowid
            FROM " . MAIN_DB_PREFIX . "contrat
            WHERE ref = '" . $db->escape($ref) . "'
              AND entity = " . ((int) $conf->entity) . "
            LIMIT 1";

    $res = $db->query($sql);
    if (!$res) {
        rrRentSeedFail('No se pudo buscar el contrato ' . $ref . ': ' . $db->lasterror());
    }

    $obj = $db->fetch_object($res);
    if (!$obj) {
        return null;
    }

    $contract = new Contrat($db);
    if ($contract->fetch((int) $obj->rowid) <= 0) {
        rrRentSeedFail('Se encontró el contrato ' . $ref . ' pero no pudo cargarse.');
    }

    return $contract;
}

function rrContractHasService($db, $contractId, $serviceId)
{
    $sql = "SELECT rowid
            FROM " . MAIN_DB_PREFIX . "contratdet
            WHERE fk_contrat = " . ((int) $contractId) . "
              AND fk_product = " . ((int) $serviceId) . "
            LIMIT 1";

    $res = $db->query($sql);
    if (!$res) {
        rrRentSeedFail('No se pudo revisar la línea del contrato #' . $contractId);
    }

    return $db->num_rows($res) > 0;
}

function rrEnsureContract($db, $user, $thirdparty, $service, array $data)
{
    $contract = rrFetchContractByRef($db, $data['ref']);

    if (!$contract) {
        $contract = new Contrat($db);
        $contract->ref = $data['ref'];
        $contract->socid = $thirdparty->id;
        $contract->date_contrat = $data['start'];
        $contract->commercial_signature_id = $user->id;
        $contract->commercial_suivi_id = $user->id;
        $contract->note_private = $data['note'];
        $contract->ref_ext = $data['ref_ext'];

        $id = $contract->create($user);
        if ($id <= 0) {
            rrRentSeedFail(
                'No se pudo crear el contrato ' . $data['ref'] . ': ' .
                ($contract->error ?: implode(' | ', (array) $contract->errors))
            );
        }

        $contract->fetch($id);
        rrRentSeedLog('Contrato creado: ' . $data['ref'] . ' (#' . $id . ')');
    } else {
        rrRentSeedLog('Contrato existente: ' . $data['ref'] . ' (#' . $contract->id . ')');
    }

    if ((int) $contract->socid !== (int) $thirdparty->id) {
        rrRentSeedFail(
            'El contrato ' . $data['ref'] . ' existe pero pertenece a otro cliente.'
        );
    }

    if (!rrContractHasService($db, $contract->id, $service->id)) {
        $lineResult = $contract->addline(
            $service->description,
            115000,
            1,
            13.0,
            0,
            0,
            $service->id,
            0,
            $data['start'],
            $data['end'],
            'HT'
        );

        if ($lineResult <= 0) {
            rrRentSeedFail(
                'No se pudo agregar el servicio al contrato ' . $data['ref'] . ': ' .
                ($contract->error ?: implode(' | ', (array) $contract->errors))
            );
        }

        rrRentSeedLog('Servicio agregado al contrato ' . $data['ref']);
        $contract->fetch($contract->id);
    }

    if ((int) $contract->status === 0 || (isset($contract->statut) && (int) $contract->statut === 0)) {
        $validate = $contract->validate($user, $data['ref']);
        if ($validate <= 0) {
            rrRentSeedFail(
                'No se pudo validar el contrato ' . $data['ref'] . ': ' .
                ($contract->error ?: implode(' | ', (array) $contract->errors))
            );
        }
        rrRentSeedLog('Contrato validado: ' . $data['ref']);
    } else {
        rrRentSeedLog('Contrato ya validado: ' . $data['ref']);
    }

    return $contract;
}

/* ------------------------------------------------------------------------- */
/* Usuario administrador                                                     */
/* ------------------------------------------------------------------------- */

try {
$seedUser = new User($db);
if ($seedUser->fetch(0, $adminLogin) <= 0 || empty($seedUser->admin)) {
    rrRentSeedFail('No se pudo encontrar el usuario administrador: ' . $adminLogin);
}

$user = $seedUser;
$GLOBALS['user'] = $seedUser;
$seedUser->getrights();

$seedLock = 'rr-renting-demo-' . $conf->entity;
$lockResult = $db->query("SELECT GET_LOCK('" . $db->escape($seedLock) . "', 30) AS acquired");
if (!$lockResult || (int) $db->fetch_object($lockResult)->acquired !== 1) {
    rrRentSeedFail('Could not lock demo initialization.');
}
$versionResult = $db->query("SELECT value FROM " . MAIN_DB_PREFIX . "const WHERE name='RR_RENTING_DEMO_SEED_VERSION' AND entity=" . ((int) $conf->entity));
if (!$versionResult) { rrRentSeedFail($db->lasterror()); }
$versionRow = $db->fetch_object($versionResult);

$currentSeedVersion = $versionRow ? $versionRow->value : '';
if ($currentSeedVersion === $seedVersion) {
    rrRentSeedLog("Seeder versión {$seedVersion} ya ejecutado. Nada que hacer.");
    exit(0);
}

/* ------------------------------------------------------------------------- */
/* Dependencias mínimas                                                      */
/* ------------------------------------------------------------------------- */

$db->begin();
if (!isModEnabled('productbatch')) {
    rrRentSeedFail(
        'El módulo Lotes/Series (productbatch) debe estar activo antes de crear unidades serializadas.'
    );
}

$warehouseSales = rrEnsureWarehouse($db, 'RR-VENTA');
rrEnsureWarehouse($db, 'RR-RENTING');
rrEnsureWarehouse($db, 'RR-CLIENTES');
rrEnsureWarehouse($db, 'RR-REVISION');
rrEnsureWarehouse($db, 'RR-REPARACION');

/* ------------------------------------------------------------------------- */
/* Producto físico + unidades serializadas                                   */
/* ------------------------------------------------------------------------- */

$product = rrEnsureRentableProduct($db, $seedUser);

$serials = [
    'RR-FORGE-001',
    'RR-FORGE-002',
    'RR-FORGE-003',
    'RR-FORGE-004',
    'RR-FORGE-005',
    'RR-FORGE-006',
];

foreach ($serials as $serial) {
    rrEnsureSerialInSalesWarehouse(
        $db,
        $seedUser,
        $product,
        $warehouseSales,
        $serial
    );
}

/* ------------------------------------------------------------------------- */
/* Servicio de renting                                                       */
/* ------------------------------------------------------------------------- */

$service = rrEnsureService($db, $seedUser);

/* ------------------------------------------------------------------------- */
/* Clientes demo: los tres segmentos del modelo de negocio                   */
/* ------------------------------------------------------------------------- */

$clientsData = [
    'esports' => [
        'ref_ext' => 'rr-demo-client-esports',
        'name' => 'Arena Tica Esports S.A. [DEMO]',
        'address' => 'Sabana, San José',
        'town' => 'San José',
        'zip' => '10108',
        'email' => 'esports@example.invalid',
        'phone' => '+506 2200-0101',
        'note' => 'Cliente ficticio para demostración académica. Segmento: organización de E-sports.',
    ],
    'corporate' => [
        'ref_ext' => 'rr-demo-client-corporate',
        'name' => 'PixelForge Studio S.R.L. [DEMO]',
        'address' => 'San Pedro, Montes de Oca',
        'town' => 'Montes de Oca',
        'zip' => '11501',
        'email' => 'studio@example.invalid',
        'phone' => '+506 2200-0202',
        'note' => 'Cliente ficticio para demostración académica. Segmento: empresa de diseño/renderizado que requiere alto rendimiento temporal.',
    ],
    'individual' => [
        'ref_ext' => 'rr-demo-client-individual',
        'name' => 'Valeria Jiménez [CLIENTE DEMO]',
        'address' => 'Heredia centro',
        'town' => 'Heredia',
        'zip' => '40101',
        'email' => 'valeria.demo@example.invalid',
        'phone' => '+506 8800-0303',
        'note' => 'Cliente ficticio para demostración académica. Segmento: persona individual mayor de edad.',
    ],
];

$clients = [];
foreach ($clientsData as $key => $clientData) {
    $clients[$key] = rrEnsureThirdparty($db, $seedUser, $clientData);
}

/* ------------------------------------------------------------------------- */
/* Contratos demo                                                            */
/* ------------------------------------------------------------------------- */

$today = dol_now();
$start = dol_mktime(0, 0, 0, (int) date('m', $today), (int) date('d', $today), (int) date('Y', $today));

$contractsData = [
    'esports' => [
        'ref' => 'RR-DEMO-ESP-001',
        'ref_ext' => 'rr-demo-contract-esports',
        'start' => $start,
        'end' => strtotime('+6 months', $start),
        'note' => 'Contrato demo de 6 meses para organización de E-sports. Preparado para crear reservas desde el módulo Renting.',
    ],
    'corporate' => [
        'ref' => 'RR-DEMO-CORP-001',
        'ref_ext' => 'rr-demo-contract-corporate',
        'start' => $start,
        'end' => strtotime('+2 months', $start),
        'note' => 'Contrato demo de 2 meses para empresa que requiere capacidad de alto rendimiento temporal.',
    ],
    'individual' => [
        'ref' => 'RR-DEMO-B2C-001',
        'ref_ext' => 'rr-demo-contract-individual',
        'start' => $start,
        'end' => strtotime('+1 month', $start),
        'note' => 'Contrato demo mensual para cliente individual.',
    ],
];

foreach ($contractsData as $key => $contractData) {
    rrEnsureContract(
        $db,
        $seedUser,
        $clients[$key],
        $service,
        $contractData
    );
}

/* ------------------------------------------------------------------------- */
/* Marcar versión                                                            */
/* ------------------------------------------------------------------------- */

$markResult = dolibarr_set_const(
    $db,
    'RR_RENTING_DEMO_SEED_VERSION',
    $seedVersion,
    'chaine',
    0,
    'Versión del seeder demo para Renting de R&R Technology',
    $conf->entity
);

rrRentSeedLog('------------------------------------------');
if ($markResult <= 0) { rrRentSeedFail('Could not save seed version.'); }
if ($db->commit() <= 0) { rrRentSeedFail('Could not commit demo initialization.'); }
rrRentSeedLog('Seeder completado correctamente.');
rrRentSeedLog('3 clientes demo configurados.');
rrRentSeedLog('1 servicio mensual de renting configurado.');
rrRentSeedLog('3 contratos validados configurados.');
rrRentSeedLog('6 PCs serializadas registradas en RR-VENTA.');
rrRentSeedLog('Siguiente paso: incorporar desde Renting las unidades deseadas a RR-RENTING y crear reservas.');
rrRentSeedLog('------------------------------------------');
} catch (Throwable $e) {
    $db->query('ROLLBACK');
    $db->transaction_opened = 0;
    fwrite(STDERR, '[RR-RENT-SEED] ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if (isset($seedLock)) { $db->query("SELECT RELEASE_LOCK('" . $db->escape($seedLock) . "')"); }
}
