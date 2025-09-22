SELECT COALESCE(CAST(SUM(node__field_api_volume_in_mil.field_api_volume_in_mil_value) AS SIGNED), 0) AS `total` from node
LEFT JOIN node__field_date ON node.nid = node__field_date.entity_id
LEFT JOIN node__field_api_volume_in_mil on node.nid = node__field_api_volume_in_mil.entity_id
WHERE `type` = 'analytics'
AND node__field_date.bundle = 'analytics'
AND node__field_date.field_date_value >= :start_date
AND node__field_date.field_date_value <= :end_date
GROUP BY `type`
;
