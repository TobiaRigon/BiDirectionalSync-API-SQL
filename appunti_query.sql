 
 /*  
- Seleziona testate cicli + fproduzione 
*/
 SELECT 
    'S01' as siteid,
    'S01MODOPEHEA' as defid,
    'FORM' as objtype,
    'S01MOD' as doc_defid,
    REPLACE(I.[No_],' ','�') as doc_code,
    '' as doc_material,
    H.[Description] as doc_description,
    H.[No_] as codice,
    H.[Description] as descrizione,
    CASE H.[Type] WHEN 0 THEN 'P' WHEN 1 THEN 'S' END as tipo,
    CASE H.[Status] WHEN 0 THEN 'N' WHEN 1 THEN 'C' WHEN 3 THEN 'X' END as stato,
    '' as fcompletato,
    CASE WHEN H.[No_] = I.[Routing No_]THEN 'X' ELSE '' END as fproduzione  -- Colonna che indica se è in produzione
FROM 
    [PP_2017_PROD].[dbo].[Pelletterie Palladio$Item] (nolock) as I
    INNER JOIN [PP_2017_PROD].[dbo].[Pelletterie Palladio$Routing Header] (nolock) as H
    ON (H.[No_] = I.[No_] OR 
        H.[No_] LIKE I.[No_] + '_M%' OR 
        H.[No_] LIKE I.[No_] + 'V%')
WHERE 
    I.[Item Category Code]='PF' AND I.[No_] = 'LVM44875';



|/* 
- Seleziona testate cicli + fproduzione 
- Recupera dibarif(Sbagliato)
*/
           WITH BOM_Info AS (
    SELECT 
        BOMH.[No_] as bom_no,
        ROW_NUMBER() OVER (PARTITION BY I2.[No_] ORDER BY 
                            CASE I2.[Production BOM No_] WHEN BOMH.[No_] THEN 1 ELSE 2 END, BOMH.[Creation Date]) * 10 as ordine,
        BOMH.[Description] as bom_description
    FROM 
        [PP_2017_PROD].[dbo].[Pelletterie Palladio$Production BOM Header] as BOMH (nolock)
    INNER JOIN 
        [PP_2017_PROD].[dbo].[Pelletterie Palladio$Item] as I2 (nolock)
    ON 
        BOMH.[PFItem No_] = I2.[No_]
    WHERE 
        I2.[Item Category Code] = 'PF'
)
SELECT 
    'S01' as siteid,
    'S01MODOPEHEA' as defid,
    'FORM' as objtype,
    'S01MOD' as doc_defid,
    REPLACE(I.[No_],' ','�') as doc_code,
    '' as doc_material,
    H.[Description] as doc_description,
    H.[No_] as codice,
    H.[Description] as descrizione,
    CASE H.[Type] WHEN 0 THEN 'P' WHEN 1 THEN 'S' END as tipo,
    CASE H.[Status] WHEN 0 THEN 'N' WHEN 1 THEN 'C' WHEN 3 THEN 'X' END as stato,
    '' as fcompletato,
    CASE WHEN H.[No_] = I.[Routing No_] THEN 'X' ELSE '' END as fproduzione,  -- Colonna che indica se è in produzione
    CONCAT(BI.ordine, ' ', BI.bom_description) as dibarif
FROM 
    [PP_2017_PROD].[dbo].[Pelletterie Palladio$Item] (nolock) as I
INNER JOIN 
    [PP_2017_PROD].[dbo].[Pelletterie Palladio$Routing Header] (nolock) as H
ON 
    (H.[No_] = I.[No_] OR 
     H.[No_] LIKE I.[No_] + '_M%' OR 
     H.[No_] LIKE I.[No_] + 'V%')
LEFT JOIN 
    BOM_Info as BI
ON 
    I.[Production BOM No_] = BI.bom_no
WHERE 
    I.[Item Category Code] = 'PF' 
    AND I.[No_] = 'LVM44875';
        


|/* 
- Seleziona testate cicli + fproduzione 
- Recupera dibarif(Corretto)
*/

-- 1 Capisci collegamento tra testate diba e testate cicli