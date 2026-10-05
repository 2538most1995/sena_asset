<?php
/** Keep annual observations intact; resolve absent locations from the current registry. */
function sena_asset_code_sql(string $column): string {
    // Imported codes may contain spaces, line breaks or non-breaking spaces.
    return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM($column),' ',''),CHAR(9),''),CHAR(10),''),CHAR(13),''),' ','')";
}

function sena_inspection_source_sql(): string {
    $registryCode = sena_asset_code_sql('equipment_code');
    $inspectionCode = sena_asset_code_sql('i.asset_code');
    // A repeated code is usable only when every matching record has the same nonblank location.
    // Including blanks in the distinct count prevents choosing a location from partial evidence.
    return "(SELECT i.*, CASE WHEN NULLIF(TRIM(i.location),'') IS NOT NULL THEN TRIM(i.location)
                    WHEN m.location_count=1 THEN NULLIF(m.registry_location,'') ELSE NULL END AS effective_location,
                CASE WHEN NULLIF(TRIM(i.location),'') IS NOT NULL THEN 'annual'
                    WHEN m.location_count=1 AND m.registry_location<>'' THEN 'registry'
                    WHEN m.location_count>1 THEN 'conflict' ELSE 'missing' END AS location_origin
            FROM inspection_items i
            LEFT JOIN (SELECT $registryCode AS code_key,
                        COUNT(DISTINCT COALESCE(TRIM(location),'')) AS location_count,
                        MIN(COALESCE(TRIM(location),'')) AS registry_location
                       FROM equipment_registry WHERE $registryCode<>'' GROUP BY $registryCode) m
                ON m.code_key=$inspectionCode) inspection_resolved";
}

function sena_inspection_location_label(array $row): string {
    return trim((string)($row['effective_location'] ?? '')) ?: 'ยังไม่ระบุสถานที่';
}

function sena_inspection_location_origin(array $row): string {
    return ['annual'=>'บันทึกในบัญชีตรวจ', 'registry'=>'อ้างอิงทะเบียนปัจจุบัน',
            'conflict'=>'รหัสตรงกับหลายสถานที่ ต้องตรวจสอบ', 'missing'=>'ไม่พบสถานที่จากทะเบียน ต้องตรวจสอบ']
        [$row['location_origin'] ?? 'missing'];
}
