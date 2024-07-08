<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Carica il file .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();


// ===Definisce le costanti per Sql===
define('LOG_FILE', 'discrepancy_log.json');
define('LAST_LOG_FILE', 'last_discrepancy_log.json');

// Inizializza i file di log se non esistono
if (!file_exists(LOG_FILE)) {
    file_put_contents(LOG_FILE, json_encode([]));
}
if (!file_exists(LAST_LOG_FILE)) {
    file_put_contents(LAST_LOG_FILE, json_encode([]));
}
// ====Definisce le costanti per l'API===
define('API_TOKEN', getEnvVariable('API_TOKEN'));
define('API_URL', getEnvVariable('API_URL'));
define('ASSET_API_URL', getEnvVariable('ASSET_API_URL'));

// Definizione della costante per il numero di record per pagina
define('PER_PAGE', 1000);

// Dati centralizzati per tablecodes
$tablecodesConfig = [
    // Tabelle Semplici
    'UM' => [
        'query' => "SELECT 'UM' AS tablecode, [Code] AS code, [Description] AS description, [LV Code] AS attrc02 FROM [dbo].[Pelletterie Palladio\$Unit of Measure]",
        'table_name' => 'Unit of Measure',
        'fields' => 'attrc02',
        'database' => 'PP_2017_TST',
        'table' => 'Pelletterie Palladio$Unit of Measure',
        'required_fields' => [
            'Code',
            'Description',
            'International Standard Code',
            'LV Code'
        ],
        'valid_item_category_codes' => [],
        'field_mapping' => [
            'attrc02' => 'LV Code'
        ],
        'code_mapping' => 'Code'
    ],
    'MC' => [
        'query' => "SELECT 'MC' AS tablecode, 'S01' AS siteid, [Code] AS code, [Description] AS description FROM [dbo].[Pelletterie Palladio\$Item Category] WHERE [Code] <> 'PF'",
        'table_name' => 'CAT MATERIALI',
        'fields' => '',
        'database' => 'PP_2017_TST',
        'table' => 'Pelletterie Palladio$Item Category',
        'required_fields' => [
            'Code',
            'Parent Category',
            'Description',
            'Indentation',
            'Presentation Order',
            'Has Children',
            'PFDef_ BOM Comp_ No_ Series',
            'Block Failed Quantity',
            'Print Item Category Code'
        ],
        'valid_item_category_codes' => [],
        'code_mapping' => 'Code'
    ],
    'PC' => [
        'query' => "SELECT 'PC' as tblcode, 'S01' as siteid, [Code] as code, [Description] as description FROM [dbo].[Pelletterie Palladio\$Item Category] WHERE [Code] IN ('PF','KP','SL','SL_K')",
        'table_name' => 'CAT PRODOTTI FINITI',
        'fields' => '',
        'database' => 'PP_2017_TST',
        'table' => 'Pelletterie Palladio$Item Category',
        'required_fields' => [
            'Code',
            'Parent Category',
            'Description',
            'Indentation',
            'Presentation Order',
            'Has Children',
            'PFDef_ BOM Comp_ No_ Series',
            'Block Failed Quantity',
            'Print Item Category Code'
        ],
        'valid_item_category_codes' => ['PF', 'KP', 'SL', 'SL_K'],
        'code_mapping' => 'Code'
    ],
    'TM' => [
        'query' => "SELECT 'TM' as tblcode, 'S01' as siteid, [Item Category Code] as attrc01,[Code] as code,[Description] as description FROM [dbo].[Pelletterie Palladio\$Product Group] WHERE [Item Category Code] <> 'PF'",
        'table_name' => 'GRUPPO MATERIALI',
        'fields' => 'attrc01',
        'database' => 'PP_2017_TST',
        'table' => 'Pelletterie Palladio$Product Group',
        'required_fields' => [
            'Item Category Code',
            'Code',
            'Description',
            'Warehouse Class Code',
            'Exclude from Transfer',
            'PFDef_ BOM Comp_ No_ Series',
            'Specific PO Transfer Line'
        ],
        'valid_item_category_codes' => ['TM'],
        'field_mapping' => [
            'attrc01' => 'Item Category Code'
        ],
        'code_mapping' => 'Code'
    ],
    'PS' => [
        'query' => "SELECT 
                    CASE [Item Category Code] 
                        WHEN 'PF' THEN [Code]
                        WHEN 'KP' THEN CONCAT('1',[Code]) 
                        WHEN 'SL' THEN CONCAT('2',[Code])
                        WHEN 'SL_K' THEN CONCAT('3',[Code])
                    END as code,
                    [Code],
                    'S01' as siteid, 'PS' as tblcode, 
                    [Description] as description,
                    '' as parentcode,
                    'false' as dropped,
                    [Item Category Code] as attrc01,
                    [Item Category Code] as attrc02
                    FROM [dbo].[Pelletterie Palladio\$Product Group]
                    WHERE [Item Category Code] IN ('PF','KP','SL','SL_K')",
        'table_name' => 'GRUPPO PRODOTTI FINITI',
        'fields' => 'attrc01,attrc02',
        'database' => 'PP_2017_TST',
        'table' => 'Pelletterie Palladio$Product Group',
        'required_fields' => [
            'Item Category Code',
            'Code',
            'Description',
            'Warehouse Class Code',
            'Exclude from Transfer',
            'PFDef_ BOM Comp_ No_ Series',
            'Specific PO Transfer Line'
        ],
        'valid_item_category_codes' => ['PF', 'KP', 'SL', 'SL_K'],
        'field_mapping' => [
            'attrc01' => 'Item Category Code',
            'attrc02' => 'Item Category Code'
        ],
        'code_mapping' => 'Code'

    ],
    'CL' => [
        'query' => "SELECT 'CL' as tblcode, 'S01' as siteid, [Code] as code,[Description] as description FROM [dbo].[Pelletterie Palladio\$PFBrand]",
        'table_name' => 'MARCHIO',
        'fields' => '',
        'database' => 'PP_2017_TST',
        'table' => 'Pelletterie Palladio$PFBrand',
        'required_fields' => [
            'Code',
            'Description',
            'Default Subsidiary Code'
        ],
        'valid_item_category_codes' => ['CL'],
        'code_mapping' => 'Code'
    ],
    'FM' => [
        'query' => "SELECT 'FM' as tblcode, 'S01' as siteid, [Code] as code, ISNULL([Description], [Code]) as description FROM [dbo].[Pelletterie Palladio\$PFCollection]",
        'table_name' => 'COLLEZIONE',
        'fields' => '',
        'database' => 'PP_2017_TST',
        'table' => 'Pelletterie Palladio$PFCollection',
        'required_fields' => [
            'Code',
            'Description'
        ],
        'valid_item_category_codes' => ['FM'],
        'code_mapping' => 'Code'
    ],
    'SE' => [
        'query' => "SELECT 'SE' as tblcode, 'S01' as siteid, REPLACE([Code],' ','§') as code, CASE WHEN [Description]='' THEN [Code] ELSE [Description] END as description FROM [dbo].[Pelletterie Palladio\$PFSeason]",
        'table_name' => 'STAGIONE',
        'fields' => '',
        'database' => 'PP_2017_TST',
        'table' => 'Pelletterie Palladio$PFSeason',
        'required_fields' => [
            'Code',
            'Description',
            'Sorting',
            'OCP Order Type Code'
        ],
        'valid_item_category_codes' => ['SE'],
        'code_mapping' => 'Code'
    ],
    'GE' => [
        'query' => "SELECT 'GE' as tblcode, 'S01' as siteid, [Code] as code,[Description] as description FROM [dbo].[Pelletterie Palladio\$PFGender]",
        'table_name' => 'GENERE',
        'fields' => '',
        'database' => 'PP_2017_TST',
        'table' => 'Pelletterie Palladio$PFGender',
        'required_fields' => [
            'Code',
            'Description'
        ],
        'valid_item_category_codes' => ['GE'],
        'code_mapping' => 'Code'
    ],
    'MV' => [
        'query' => "SELECT 'MV' as tblcode, 'S01' as siteid, [Code] as code, [Description] as description FROM [dbo].[Pelletterie Palladio\$PFItem Status]",
        'table_name' => 'STATO MATERIALI',
        'fields' => '',
        'database' => 'PP_2017_TST',
        'table' => 'Pelletterie Palladio$PFItem Status',
        'required_fields' => [
            'Code',
            'Description',
            'Purchase Orders Blocked',
            'Purchase Receipt Blocked',
            'Sales Orders Blocked',
            'Sales Shipment Blocked',
            'Sales Return Blocked',
            'Purchase Return Blocked',
            'Positive Adjmt. Blocked',
            'Negative Adjmt. Blocked',
            'Transfer Blocked',
            'Consumption Blocked',
            'Output Blocked',
            'Prod. Orders Blocked',
            'Requisition Blocked',
            'Delivery Proposal Blocked',
            'Receipt Proposal Blocked',
            'Revaluation Blocked',
            'Return Receive Blocked',
            'Return Shipment Blocked',
            'All Blocked'
        ],
        'valid_item_category_codes' => ['MV'],
        'code_mapping' => 'Code'
    ],
    'AV' => [
        'query' => "SELECT 'AV' as tblcode, 'S01' as siteid, [Code] as code, [Description] as description FROM [dbo].[Pelletterie Palladio\$PFItem Status]",
        'table_name' => 'STATO PRODOTTI FINITI',
        'fields' => '',
        'database' => 'PP_2017_TST',
        'table' => 'Pelletterie Palladio$PFItem Status',
        'required_fields' => [
            'Code',
            'Description',
            'Purchase Orders Blocked',
            'Purchase Receipt Blocked',
            'Sales Orders Blocked',
            'Sales Shipment Blocked',
            'Sales Return Blocked',
            'Purchase Return Blocked',
            'Positive Adjmt. Blocked',
            'Negative Adjmt. Blocked',
            'Transfer Blocked',
            'Consumption Blocked',
            'Output Blocked',
            'Prod. Orders Blocked',
            'Requisition Blocked',
            'Delivery Proposal Blocked',
            'Receipt Proposal Blocked',
            'Revaluation Blocked',
            'Return Receive Blocked',
            'Return Shipment Blocked',
            'All Blocked'
        ],
        'valid_item_category_codes' => ['AV'],
        'code_mapping' => 'Code'
    ],
    'NM' => [
        'query' => "SELECT 'code' as code,
                         'siteid' as siteid,
                         'tblcode' as tblcode,
                         'description' as description,
                         'parentcode' as parentcode,
                         'dropped' as dropped
                  UNION
                  SELECT [No_] as code,
                         'S01' as siteid,
                         'NM' as tblcode,
                         [No_] as description,
                         '' as parentcode,
                         '' as dropped
                  FROM [dbo].[Pelletterie Palladio\$PFModel] (nolock)",
        'table_name' => 'NUMERO MODELLO PFModel',
        'fields' => '',
        'database' => 'PP_2017_TST',
        'table' => 'Pelletterie Palladio$PFModel',
        'required_fields' => [
            'timestamp',
            'No_',
            'No_ 2',
            'Description',
            'Search Description',
            'Description 2',
            'Base Unit of Measure',
            'Price Unit Conversion',
            'Inventory Posting Group',
            'Shelf No_',
            'Item_Cust_ Disc_ Gr_',
            'Allow Invoice Disc_',
            'Statistics Group',
            'Commission Group',
            'Unit Price',
            'Price_Profit Calculation',
            'Profit _',
            'Costing Method',
            'Unit Cost',
            'Standard Cost',
            'Last Direct Cost',
            'Indirect Cost _',
            'Vendor No_',
            'Vendor Item No_',
            'Lead Time Calculation',
            'Reorder Point',
            'Maximum Inventory',
            'Reorder Quantity',
            'Alternative Item No_',
            'Unit List Price',
            'Duty Due _',
            'Duty Code',
            'Gross Weight',
            'Net Weight',
            'Units per Parcel',
            'Unit Volume',
            'Durability',
            'Freight Type',
            'Tariff No_',
            'Duty Unit Conversion',
            'Country_Region Purchased Code',
            'Budget Quantity',
            'Budgeted Amount',
            'Budget Profit',
            'Blocked',
            'Last Date Modified',
            'Price Includes VAT',
            'VAT Bus_ Posting Gr_ (Price)',
            'Gen_ Prod_ Posting Group',
            'Country_Region of Origin Code',
            'Automatic Ext_ Texts',
            'No_ Series',
            'Tax Group Code',
            'VAT Prod_ Posting Group',
            'Reserve',
            'Global Dimension 1 Code',
            'Global Dimension 2 Code',
            'Assembly Policy',
            'Low-Level Code',
            'Lot Size',
            'Serial Nos_',
            'Last Unit Cost Calc_ Date',
            'Rolled-up Material Cost',
            'Rolled-up Capacity Cost',
            'Scrap _',
            'Inventory Value Zero',
            'Discrete Order Quantity',
            'Minimum Order Quantity',
            'Maximum Order Quantity',
            'Safety Stock Quantity',
            'Order Multiple',
            'Safety Lead Time',
            'Flushing Method',
            'Replenishment System',
            'Rounding Precision',
            'Sales Unit of Measure',
            'Purch_ Unit of Measure',
            'Time Bucket',
            'Reordering Policy',
            'Include Inventory',
            'Manufacturing Policy',
            'Rescheduling Period',
            'Lot Accumulation Period',
            'Dampener Period',
            'Dampener Quantity',
            'Overflow Level',
            'Manufacturer Code',
            'Item Category Code',
            'Product Group Code',
            'Service Item Group',
            'Item Tracking Code',
            'Lot Nos_',
            'Expiration Calculation',
            'Special Equipment Code',
            'Put-away Template Code',
            'Put-away Unit of Measure Code',
            'Phys Invt Counting Period Code',
            'Use Cross-Docking',
            'Components at Location',
            'Output at Location',
            'PFVert Component Group',
            'PFHorz Component Group',
            'PFCollection',
            'PFSeason',
            'PFTheme',
            'PFUserDefinedTable',
            'PFItem Status',
            'PFBrand',
            'PFComponent Group 3',
            'PFComponent Group 4',
            'PFComponent Group 5',
            'PFComponent Group 6',
            'PFComponent Group 7',
            'PFComponent Group 8',
            'PFComponent Group 9',
            'PFComponent Group 10',
            'PFItem No_Series',
            'PFCheck Order Qty_ per',
            'PFMin_ Sales Order Qty_',
            'PFDelivery Period',
            'PFQuota Category',
            'PFForecast Distribution',
            'PFSell from Date',
            'PFCheck on Receipt',
            'PFWhse Handling Time (Sec_)',
            'PFGender',
            'PFSales Profile Code',
            'PFNever Out of Stock',
            'PFMaximum Weight',
            'PFCarton Type',
            'PFProd_ Order Setup Time per',
            'PFCombine To Max_ Order Qty_',
            'PFBOM on Item level',
            'PFFixed Order Tracking',
            'Routing No_',
            'Production BOM No_',
            'Single-Level Material Cost',
            'Single-Level Capacity Cost',
            'Single-Level Subcontrd_ Cost',
            'Single-Level Cap_ Ovhd Cost',
            'Single-Level Mfg_ Ovhd Cost',
            'Overhead Rate',
            'Rolled-up Subcontracted Cost',
            'Rolled-up Mfg_ Ovhd Cost',
            'Rolled-up Cap_ Overhead Cost',
            'Order Tracking Policy',
            'Critical',
            'Common Item No_',
            'Show in Inventory Book'
        ],
        'valid_item_category_codes' => ['NM'],
        'code_mapping' => 'No_'

    ],
    // Tabelle Asset
    'SUP' => [
        'query' => "SELECT 'S01' as siteid, 'S01SUP' as defid, 'ASSET' as objtype, [No_] as code, [Name] as description from [dbo].[Pelletterie Palladio\$Vendor] (nolock)",
        'table_name' => 'Vendor',
        'fields' => '', // Specifica i campi se necessario
        'database' => 'PP_2017_TST',
        'table' => 'Pelletterie Palladio$Vendor',
        'required_fields' => [
            'No_',
            'Name'
        ],
        'field_mapping' => [
            'code' => 'No_',
            'description' => 'Name'
        ],
        'code_mapping' => 'No_',
        'defid' => 'S01SUP',
        'endpoint' => ASSET_API_URL,
        'type' => 'asset'
    ],
    'MAT' => [
        'query' => "SELECT 'S01' as siteid, 'S01MAT' as defid, 'ASSET' as objtype,  
            MT.[No_] as code,  
            MT.[Description] as description,  
            MT.[Description 2] as descrizione2,  
            MT.[No_ 2] as code2,  
            MT.[Base Unit of Measure] as uom,  
            MT.[Item Category Code] as category,  
            MT.[Product Group Code] as tpmat,  
            MT.[PFBrand Code] as brand,  
            MT.[PFCollection] as family,  
            MT.[PFSeason] as creseason,  
            MT.[PFGender] as gender,  
            MT.[PFItem Status] as prstatus,  
            MT.[Purch_ Unit of Measure] as purchaseuom,  
            MT.[Provided by Vendor in BOM] as fornitofor,  
            ISNULL(M.[No_],'') as numeromod,  
            MT.[Vendor No_] as supplier,  
            '' as modecomm,  
            MT.[LV Item ID] as idmatlv,  
            '' as fset,  
            '' as dropped  
            FROM [dbo].[Pelletterie Palladio\$Item] (nolock) AS MT  
            LEFT JOIN [dbo].[Pelletterie Palladio\$PFModel] (nolock) as M ON MT.[PFModel No_] = M.[No_]  
            WHERE MT.[Item Category Code] IN ('AC','AT','AC_K','CN','CO','FI','IM','PE','RI','SA','SA_K','MP','TF')",
        'table_name' => 'Materiale',
        'fields' => 'uom,brand,category,descrizione2,purchaseuom,code2',
        'database' => 'PP_2017_TST',
        'table' => 'Pelletterie Palladio$Item',
        'required_fields' => [
            'No_',
            'Description',
            'Base Unit of Measure'
        ],
        'field_mapping' => [
            'code' => 'No_',
            'description' => 'Description',
            'uom' => 'Base Unit of Measure',
            'brand' => 'PFBrand Code',
            'category' => 'Item Category Code',
            'descrizione2' => 'Description 2',
            'purchaseuom' => 'Purch_ Unit of Measure',
            'code2' => 'No_ 2'
        ],
        'code_mapping' => 'No_',
        'defid' => 'S01MAT',
        'endpoint' => ASSET_API_URL,
        'truncate_fields' => [
            'uom',
            'brand',
            'category',
            'purchaseuom'
        ],
        'type' => 'asset'
    ],
    'MOD' => [
        'query' => "SELECT
            'S01' as siteid,
            'S01MOD' as defid,
            'ASSET' as objtype,
            REPLACE([No_], ' ', '§') as code,
            [No_ 2] as code2,
            CASE WHEN [Description] = '' THEN [No_] ELSE [Description] END as description,
            [Base Unit of Measure] as uom,
            [Item Category Code] as category,
            CASE [Item Category Code]
                WHEN 'PF' THEN [Product Group Code]
                WHEN 'KP' THEN CONCAT('1', [Product Group Code])
                WHEN 'SL' THEN CONCAT('2', [Product Group Code])
                WHEN 'SL_K' THEN CONCAT('3', [Product Group Code])
            END as subcategory,
            [PFBrand Code] as brand,
            [PFCollection] as family,
            [PFSeason] as season,
            '' as gender,
            [PFItem Status] as prstatus,
            '' as modecomm,
            [LV Item ID] as idprflv
            FROM [dbo].[Pelletterie Palladio\$Item] (nolock)
            WHERE [Item Category Code] IN ('PF', 'KP', 'SL', 'SL_K')",
        'table_name' => 'Modelli',
        'fields' => 'code2,uom,category,subcategory,brand,family,season,prstatus,idprflv',
        'database' => 'PP_2017_TST',
        'table' => 'Pelletterie Palladio$Item',
        'required_fields' => [
            'No_',
            'Description',
            'Base Unit of Measure'
        ],
        'field_mapping' => [
            'code' => 'No_',
            'code2' => 'No_ 2',
            'uom' => 'Base Unit of Measure',
            'category' => 'Item Category Code',
            'subcategory' => 'Product Group Code',
            'brand' => 'PFBrand Code',
            'family' => 'PFCollection',
            'season' => 'PFSeason',
            'prstatus' => 'PFItem Status',
            'idprflv' => 'LV Item ID'
        ],
        'truncate_fields' => [
            'uom',
            'category',
            'purchaseuom',
            'subcategory'
        ],
        'code_mapping' => 'No_',
        'defid' => 'S01MOD',
        'endpoint' => ASSET_API_URL,
        'type' => 'asset'
    ],
];

// Lista di tablecode
$tablecodes = array_keys($tablecodesConfig);
