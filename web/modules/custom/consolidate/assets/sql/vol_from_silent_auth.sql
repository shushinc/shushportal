SELECT 
    COALESCE(SUM(CASE 
        WHEN node__field_attribute.field_attribute_target_id = 182 
        THEN node__field_api_volume_in_mil.field_api_volume_in_mil_value 
        ELSE 0 
    END), 0) AS target_volume,
    COALESCE(SUM(node__field_api_volume_in_mil.field_api_volume_in_mil_value), 0) AS total_volume,
    CEIL(
        (COALESCE(SUM(CASE 
            WHEN node__field_attribute.field_attribute_target_id = 182 
            THEN node__field_api_volume_in_mil.field_api_volume_in_mil_value 
            ELSE 0 
        END), 0) / NULLIF(SUM(node__field_api_volume_in_mil.field_api_volume_in_mil_value), 0)) * 100) AS total
FROM node
LEFT JOIN node__field_date ON node.nid = node__field_date.entity_id
LEFT JOIN node__field_api_volume_in_mil ON node.nid = node__field_api_volume_in_mil.entity_id
LEFT JOIN node__field_attribute ON node.nid = node__field_attribute.entity_id
WHERE node.type = 'analytics'
AND node__field_date.bundle = 'analytics'
AND node__field_date.field_date_value >= :start_date
AND node__field_date.field_date_value <= :end_date
GROUP BY node.type;
