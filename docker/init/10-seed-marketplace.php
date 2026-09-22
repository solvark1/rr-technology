<?php

/*
 * R&R Technologies
 * Datos iniciales para Marketplace.
 *
 * Se ejecuta automáticamente durante la primera
 * instalación de Dolibarr mediante docker-init.d.
 */

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);

require_once '/var/www/html/master.inc.php';

require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';


/*
|--------------------------------------------------------------------------
| Configuración
|--------------------------------------------------------------------------
|
| IMPORTANTE:
| Subimos la versión a 2 porque esta versión agrega los idiomas
| requeridos por Marketplace en llx_product_lang.
|
*/

$seedVersion = '2';

$adminLogin = getenv('DOLI_ADMIN_LOGIN') ?: 'admin';

$productLanguages = [
    'en_US',
    'es_ES',
    'es_CR',
];


function rrSeedLog($message)
{
    print "[RR-SEED] " . $message . PHP_EOL;
}


/*
|--------------------------------------------------------------------------
| Límite máximo de subida
|--------------------------------------------------------------------------
|
| El valor viene del compose.yaml mediante RR_MAIN_UPLOAD_DOC_KB.
| Este bloque se ejecuta antes de comprobar la versión del seeder,
| para que el límite pueda actualizarse aunque el seed ya exista.
|
*/

$maxUploadKb =
    (int) (
        getenv('RR_MAIN_UPLOAD_DOC_KB')
        ?: 25600
    );

$resultUploadLimit =
    dolibarr_set_const(
        $db,
        'MAIN_UPLOAD_DOC',
        $maxUploadKb,
        'chaine',
        0,
        'Maximum upload size in KB',
        $conf->entity
    );

if ($resultUploadLimit < 0) {

    rrSeedLog(
        'ERROR configurando MAIN_UPLOAD_DOC'
    );

} else {

    rrSeedLog(
        "MAIN_UPLOAD_DOC configurado en {$maxUploadKb} KB"
    );
}


/*
|--------------------------------------------------------------------------
| Crear / actualizar idioma de producto
|--------------------------------------------------------------------------
|
| Marketplace filtra los productos usando llx_product_lang.
| Si no existe una fila para el idioma activo, el producto no aparece.
|
*/

function rrSeedProductLanguage(
    $db,
    $productId,
    $language,
    $label,
    $description
) {
    $sqlCheck =
        "SELECT rowid
         FROM " . MAIN_DB_PREFIX . "product_lang
         WHERE fk_product = " . ((int) $productId) . "
           AND lang = '" . $db->escape($language) . "'
         LIMIT 1";

    $resCheck = $db->query($sqlCheck);

    if (!$resCheck) {
        rrSeedLog(
            "ERROR verificando idioma {$language} para producto #{$productId}: "
            . $db->lasterror()
        );

        return -1;
    }

    $escapedLabel =
        $db->escape((string) $label);

    $escapedDescription =
        $db->escape((string) $description);


    if ($db->num_rows($resCheck) > 0) {

        $sqlUpdate =
            "UPDATE " . MAIN_DB_PREFIX . "product_lang
             SET label = '" . $escapedLabel . "',
                 description = '" . $escapedDescription . "',
                 note = '" . $escapedDescription . "'
             WHERE fk_product = " . ((int) $productId) . "
               AND lang = '" . $db->escape($language) . "'";

        if (!$db->query($sqlUpdate)) {
            rrSeedLog(
                "ERROR actualizando idioma {$language} para producto #{$productId}: "
                . $db->lasterror()
            );

            return -1;
        }

        return 1;
    }


    $sqlInsert =
        "INSERT INTO " . MAIN_DB_PREFIX . "product_lang
         (
             fk_product,
             lang,
             label,
             description,
             note
         )
         VALUES
         (
             " . ((int) $productId) . ",
             '" . $db->escape($language) . "',
             '" . $escapedLabel . "',
             '" . $escapedDescription . "',
             '" . $escapedDescription . "'
         )";

    if (!$db->query($sqlInsert)) {
        rrSeedLog(
            "ERROR creando idioma {$language} para producto #{$productId}: "
            . $db->lasterror()
        );

        return -1;
    }

    return 1;
}


/*
|--------------------------------------------------------------------------
| Usuario administrador
|--------------------------------------------------------------------------
*/

$seedUser = new User($db);

$result = $seedUser->fetch(0, $adminLogin);

if ($result <= 0) {
    rrSeedLog(
        "ERROR: No se pudo encontrar el usuario administrador: "
        . $adminLogin
    );

    exit(1);
}


/*
 * Algunas funciones internas de Dolibarr utilizan
 * la variable global $user.
 */

$user = $seedUser;

$GLOBALS['user'] = $seedUser;


/*
|--------------------------------------------------------------------------
| Evitar ejecutar nuevamente la misma versión
|--------------------------------------------------------------------------
*/

$currentSeedVersion =
    getDolGlobalString('RR_MARKETPLACE_SEED_VERSION');

if ($currentSeedVersion === $seedVersion) {

    rrSeedLog(
        "Seeder versión {$seedVersion} ya ejecutado. Nada que hacer."
    );

    exit(0);
}


/*
|--------------------------------------------------------------------------
| Obtener categoría raíz creada por Marketplace
|--------------------------------------------------------------------------
*/

$marketplaceRootId =
    getDolGlobalInt('MARKETPLACE_ROOT_CATEGORY_ID');

if ($marketplaceRootId <= 0) {

    rrSeedLog(
        'ERROR: Marketplace no tiene categoría raíz.'
    );

    rrSeedLog(
        'Comprueba que el módulo Marketplace esté activado.'
    );

    exit(1);
}


rrSeedLog(
    "Marketplace root category ID: {$marketplaceRootId}"
);


/*
|--------------------------------------------------------------------------
| Categorías
|--------------------------------------------------------------------------
|
| Marketplace
| ├── PC Gaming
| ├── Mouse Gaming
| ├── Teclados Gaming
| ├── Audio Gaming
| └── Monitores Gaming
|
*/

$categoriesData = [

    [
        'key'         => 'pc',
        'ref_ext'     => 'rr-gaming-pc',
        'label'       => 'PC Gaming',
        'description' =>
            'Computadoras gaming de alto rendimiento.'
    ],

    [
        'key'         => 'mouse',
        'ref_ext'     => 'rr-gaming-mouse',
        'label'       => 'Mouse Gaming',
        'description' =>
            'Mouse y periféricos de precisión para gaming.'
    ],

    [
        'key'         => 'keyboard',
        'ref_ext'     => 'rr-gaming-keyboard',
        'label'       => 'Teclados Gaming',
        'description' =>
            'Teclados mecánicos y periféricos para jugadores.'
    ],

    [
        'key'         => 'audio',
        'ref_ext'     => 'rr-gaming-audio',
        'label'       => 'Audio Gaming',
        'description' =>
            'Headsets y dispositivos de audio para gaming.'
    ],

    [
        'key'         => 'monitor',
        'ref_ext'     => 'rr-gaming-monitor',
        'label'       => 'Monitores Gaming',
        'description' =>
            'Monitores de alta tasa de refresco para gaming.'
    ],
];


$categoryIds = [];


foreach ($categoriesData as $categoryData) {

    $category = new Categorie($db);

    /*
     * Buscamos por ref_ext para hacer el seeder idempotente.
     */

    $result = $category->fetch(
        0,
        '',
        Categorie::TYPE_PRODUCT,
        $categoryData['ref_ext']
    );


    if ($result > 0) {

        rrSeedLog(
            "Categoría existente: "
            . $categoryData['label']
        );

        $categoryIds[$categoryData['key']] =
            $category->id;

        continue;
    }


    $category = new Categorie($db);

    $category->label =
        $categoryData['label'];

    $category->description =
        $categoryData['description'];

    $category->type =
        Categorie::TYPE_PRODUCT;

    $category->fk_parent =
        $marketplaceRootId;

    $category->visible = 1;

    $category->ref_ext =
        $categoryData['ref_ext'];


    $categoryId =
        $category->create($seedUser);


    if ($categoryId <= 0) {

        rrSeedLog(
            "ERROR creando categoría "
            . $categoryData['label']
            . ": "
            . $category->error
        );

        exit(1);
    }


    $categoryIds[$categoryData['key']] =
        $categoryId;


    rrSeedLog(
        "Categoría creada: "
        . $categoryData['label']
        . " (#{$categoryId})"
    );
}


/*
|--------------------------------------------------------------------------
| Productos
|--------------------------------------------------------------------------
|
| Los precios son datos ficticios para demostración académica.
| Se expresan según la moneda configurada en Dolibarr.
|
*/

$productsData = [

    [
        'ref'         => 'RR-PC-001',
        'label'       => 'R&R Forge RTX Gaming PC',

        'description' =>
            'PC gaming de alto rendimiento con GPU dedicada, '
            . '16 GB de memoria RAM, almacenamiento SSD NVMe '
            . 'y sistema de refrigeración optimizado para gaming.',

        'price'       => 799000,

        'category'    => 'pc'
    ],

    [
        'ref'         => 'RR-MOUSE-001',
        'label'       => 'Razer DeathAdder V3',

        'description' =>
            'Mouse gaming ergonómico de alto rendimiento '
            . 'diseñado para precisión y sesiones prolongadas.',

        'price'       => 49900,

        'category'    => 'mouse'
    ],

    [
        'ref'         => 'RR-KEY-001',
        'label'       => 'Razer BlackWidow V4 X',

        'description' =>
            'Teclado mecánico gaming con iluminación RGB '
            . 'y controles dedicados para una experiencia '
            . 'de juego competitiva.',

        'price'       => 89900,

        'category'    => 'keyboard'
    ],

    [
        'ref'         => 'RR-AUDIO-001',
        'label'       => 'Razer BlackShark V2 X',

        'description' =>
            'Headset gaming ligero con sonido envolvente '
            . 'y micrófono diseñado para comunicación clara.',

        'price'       => 39900,

        'category'    => 'audio'
    ],

    [
        'ref'         => 'RR-MON-001',
        'label'       => 'Samsung Odyssey G5 27',

        'description' =>
            'Monitor gaming de 27 pulgadas orientado a '
            . 'experiencias fluidas y juegos de alto rendimiento.',

        'price'       => 189900,

        'category'    => 'monitor'
    ],
];


foreach ($productsData as $productData) {

    $product = new Product($db);


    /*
     * Buscar producto por referencia.
     */

    $result =
        $product->fetch(
            0,
            $productData['ref']
        );


    if ($result > 0) {

        rrSeedLog(
            "Producto existente: "
            . $productData['label']
        );

    } else {

        $product =
            new Product($db);


        $product->ref =
            $productData['ref'];

        $product->label =
            $productData['label'];

        $product->description =
            $productData['description'];


        /*
         * Producto físico.
         */

        $product->type =
            Product::TYPE_PRODUCT;


        /*
         * Disponible para venta.
         */

        $product->status = 1;


        /*
         * También puede ser comprado a proveedores.
         */

        $product->status_buy = 1;


        $productId =
            $product->create(
                $seedUser
            );


        if ($productId <= 0) {

            rrSeedLog(
                "ERROR creando producto "
                . $productData['label']
                . ": "
                . $product->error
            );

            exit(1);
        }


        /*
         * Recargar objeto.
         */

        $product->fetch(
            $productId
        );


        /*
         * Precio de venta.
         *
         * TTC = precio con impuesto incluido.
         * 13 = IVA utilizado para estos datos demo.
         */

        $priceResult =
            $product->updatePrice(
                $productData['price'],
                'TTC',
                $seedUser,
                13.0
            );


        if ($priceResult < 0) {

            rrSeedLog(
                "ERROR asignando precio a "
                . $productData['label']
                . ": "
                . $product->error
            );

            exit(1);
        }


        rrSeedLog(
            "Producto creado: "
            . $productData['label']
            . " (#{$productId})"
        );
    }


    /*
     * Asociar producto:
     *
     * 1. A la categoría raíz del Marketplace.
     * 2. A su categoría específica.
     */

    $categoryId =
        $categoryIds[
            $productData['category']
        ];


    $categoryResult =
        $product->setCategories(
            [
                $marketplaceRootId,
                $categoryId
            ]
        );


    if ($categoryResult < 0) {

        rrSeedLog(
            "ERROR asignando categorías a "
            . $productData['label']
        );

        exit(1);
    }


    rrSeedLog(
        "Categorías asignadas a "
        . $productData['label']
    );


    /*
    |--------------------------------------------------------------------------
    | Idiomas requeridos por Marketplace
    |--------------------------------------------------------------------------
    |
    | El grid del Marketplace hace JOIN contra llx_product_lang.
    | Por eso cada producto debe existir en los idiomas que puede
    | utilizar el sitio.
    |
    */

    foreach ($productLanguages as $language) {

        $languageResult =
            rrSeedProductLanguage(
                $db,
                $product->id,
                $language,
                $productData['label'],
                $productData['description']
            );


        if ($languageResult < 0) {

            rrSeedLog(
                "ERROR configurando idioma {$language} para "
                . $productData['label']
            );

            exit(1);
        }


        rrSeedLog(
            "Idioma {$language} configurado para "
            . $productData['label']
        );
    }
}


/*
|--------------------------------------------------------------------------
| Marcar seed como completado
|--------------------------------------------------------------------------
*/

dolibarr_set_const(
    $db,
    'RR_MARKETPLACE_SEED_VERSION',
    $seedVersion,
    'chaine',
    0,
    'Versión del seeder inicial de R&R Technologies',
    $conf->entity
);


rrSeedLog(
    '------------------------------------------'
);

rrSeedLog(
    'Seeder completado correctamente.'
);

rrSeedLog(
    '5 categorías configuradas.'
);

rrSeedLog(
    '5 productos configurados.'
);

rrSeedLog(
    'Idiomas en_US, es_ES y es_CR configurados.'
);

rrSeedLog(
    '------------------------------------------'
);
