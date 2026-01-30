WITH per_customer AS (
  SELECT
    p.field_partner_target_id AS partner_id,
    COALESCE(SUM(v.field_api_volume_in_mil_value), 0) AS partner_total
  FROM node n
  LEFT JOIN node__field_date d
    ON n.nid = d.entity_id
  LEFT JOIN node__field_api_volume_in_mil v
    ON n.nid = v.entity_id
  LEFT JOIN node__field_partner p
    ON n.nid = p.entity_id
  WHERE n.type = 'analytics'
    AND d.bundle = 'analytics'
    AND d.field_date_value >= :start_date
    AND d.field_date_value <= :end_date
  GROUP BY p.field_partner_target_id
),
totals AS (
  SELECT
    COALESCE(SUM(partner_total), 0) AS total_volume,
    COALESCE((
      SELECT SUM(partner_total)
      FROM (
        SELECT partner_total
        FROM per_customer
        ORDER BY partner_total DESC
        LIMIT 10
      ) t
    ), 0) AS top10_volume
  FROM per_customer
)
SELECT
   CASE
    WHEN total_volume = 0 THEN 0
    ELSE ROUND((top10_volume / total_volume) * 100, 2)
  END AS total 
FROM totals;