SELECT
  CASE
    WHEN COALESCE(SUM(CAST(v.field_api_volume_in_mil_value AS SIGNED)), 0) = 0 THEN 70
    ELSE ROUND(
      (COALESCE(SUM(CAST(s.field_success_api_volume_in_mil_value AS SIGNED)), 0) * 100.0)
      / COALESCE(SUM(CAST(v.field_api_volume_in_mil_value AS SIGNED)), 0),
      2
    )
  END AS total
FROM node n
LEFT JOIN node__field_date d
  ON n.nid = d.entity_id
LEFT JOIN node__field_api_volume_in_mil v
  ON n.nid = v.entity_id
LEFT JOIN node__field_success_api_volume_in_mil s
  ON n.nid = s.entity_id
WHERE n.type = 'analytics'
  AND d.bundle = 'analytics'
  AND d.field_date_value >= :start_date
  AND d.field_date_value <= :end_date
  ;