 
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
Cicli
Nuova query x CSV import
*/

SELECT 
		'S01' as siteid,
		'S01MODOPEHEA' as defid,
		'SGRID' as objtype,
		'S01MOD' as doc_defid,
		REPLACE(I.[No_],' ','�') as doc_code,
		'' as doc_material,
		H.[Description] as doc_description,
		-- 10 as ordine,
		row_number() over (partition by I.[No_] order by CASE I.[Routing No_] WHEN H.No_ THEN 1 ELSE 2 END, H.[timestamp]) * 10 as ordine,
		H.[No_] as codice,
		H.[Description] as descrizione,
		CASE H.[Type] WHEN 0 THEN 'P' WHEN 1 THEN 'S' END as tipo,
		CASE H.[Status] WHEN 0 THEN 'N' WHEN 1 THEN 'CE' WHEN 3 THEN 'X' END  as stato,
		'' as dibarif,
		'' as fcompletato,
		CASE H.[No_] WHEN I.[Routing No_] THEN 'X' ELSE '' END as fproduzione
FROM 
	[PP_2017_PROD].[dbo].[Pelletterie Palladio$Item] (nolock) as I
		INNER JOIN [PP_2017_PROD].[dbo].[Pelletterie Palladio$Routing Header] (nolock) as H
		ON (H.[No_] like CONCAT(I.[No_],'V%') OR 
			H.[No_] like CONCAT(I.[No_],'_M%') 
			-- OR H.[No_] like CONCAT(I.[No_],'V%')
			)
			-- I.[Routing No_] = H.[No_]
  WHERE I.[Item Category Code]='PF'--  AND I.[No_] like 'LVM4487%'


|/* 
Righe Cicli
Nuova query x CSV import
*/
 SELECT 
            -- RIGHE CICLO
                    'S01' as siteid,
                    'S01MODOPEDATA' as defid,
                    'SGRID' as objtype,
                    'S01MOD' as doc_deifd,
					'S01MODOPEHEA' as parent_defid,
                    REPLACE(I.[No_],' ','�') as doc_code,
                    '' as doc_material,
                    H.Description as 'doc_description',
					'' as parent_ordine,
					'' as parent_codice,
                    CAST(L.[Operation No_] as int) as sort,
					CASE ISNULL(L.[Next Operation No_],'') WHEN '' THEN 10 ELSE CAST(L.[Next Operation No_] as int) END as nextope,
					'' as parallela,
					 CASE ISNULL(L.[Previous Operation No_],'') WHEN '' THEN -10 ELSE CAST(L.[Previous Operation No_] as int) END  as prevope,
					L.[No_] as phase,
                    L.[Work Center Group Code] as workgc,
                    L.[Setup Time] as qty,
                    L.[Routing Link Code] as routinglc,
                    L.[WIP Code] as wipcode,				
                    '' as fcost,
                    '' as notes,
                    '' as dropped
            FROM 
                [PP_2017_PROD].[dbo].[Pelletterie Palladio$Item] (nolock) as I
                    INNER JOIN [PP_2017_PROD].[dbo].[Pelletterie Palladio$Routing Header] (nolock) as H
                    ON I.[Routing No_] = H.[No_]
                    INNER JOIN 
                    [PP_2017_PROD].[dbo].[Pelletterie Palladio$Routing Line] (nolock) as L
                        ON H.No_ = L.[Routing No_]
            WHERE I.[Item Category Code] = 'PF'
                AND H.[Type] = 0 -- AND I.[No_] = 'LVM44875'




|/* 
Operazioni S01OPE (testate)
 Controlla i campi Duration , cost , currency se sono corretti.
*/
SELECT
    'S01' as siteid,
    'S01OPE' as defid,
    'ASSET' as objtype,
    RTRIM(L.[No_]) as code,
    REPLACE(I.[No_],' ','�') as refmode,
    RTRIM(L.[No_]) as phase,
    L.[Description] as description,
    '' as duration,  
    ''as cost,			
    'EUR' as currency,
    '' as notes,
    'false' as dropped
FROM
    [PP_2017_PROD].[dbo].[Pelletterie Palladio$Item] (nolock) as I
    INNER JOIN [PP_2017_PROD].[dbo].[Pelletterie Palladio$Routing Header] (nolock) as H
     ON I.[Routing No_] = H.[No_]
    INNER JOIN
    [PP_2017_PROD].[dbo].[Pelletterie Palladio$Routing Line] (nolock) as L
     ON H.No_ = L.[Routing No_]
WHERE I.[Item Category Code] = 'PF'
    AND H.[Type] = 0 -- AND I.[No_] = 'LVM44875'


|/* 
Istruzioni S01OPEISTDATA (testate)
 Controlla i campi Duration , cost , currency se sono corretti.
*/
SELECT 
    'S01' as siteid,
    'S01OPEISTDATA' as defid,
    'SGRID' as objtype,
    'S01MOD' as doc_defid,
    REPLACE(L.[No_],' ','�') as doc_code,
    REPLACE(I.[No_],' ','�') as doc_refmode,
    H.Description as "doc_description",
    row_number() over (partition by I.[No_] order by CASE I.[Routing No_] WHEN H.No_ THEN 1 ELSE 2 END, H.[timestamp]) * 10 as ordine,
    '' as codicepezzo,  
    '' as pezzicad,		
    '' as pezzibom,
    '' as numpezzi,
    '' as operation,
    '' as parametri,
    '' as tools,
    '' as video,
    '' as tempostd
FROM 
	[PP_2017_PROD].[dbo].[Pelletterie Palladio$Item] (nolock) as I
		INNER JOIN [PP_2017_PROD].[dbo].[Pelletterie Palladio$Routing Header] (nolock) as H
		    ON I.[Routing No_] = H.[No_]		 -- RESTRIZIONE RIGHE CICLO DI PRODUZIONE
		INNER JOIN 
    [PP_2017_PROD].[dbo].[Pelletterie Palladio$Routing Line] (nolock) as L
			ON H.No_ = L.[Routing No_]
WHERE I.[Item Category Code] = 'PF'
	AND H.[Type] = 0 
