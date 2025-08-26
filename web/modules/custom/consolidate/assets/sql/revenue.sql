SELECT CAST(SUM(node__field_est_revenue.field_est_revenue_value) AS SIGNED) AS `total` from node
LEFT JOIN node__field_date ON node.nid = node__field_date.entity_id
LEFT JOIN node__field_est_revenue on node.nid = node__field_est_revenue.entity_id
WHERE `type` = 'analytics'
AND node__field_date.bundle = 'analytics'
AND node__field_date.field_date_value >= :start_date
AND node__field_date.field_date_value <= :end_date
GROUP BY `type`
;
