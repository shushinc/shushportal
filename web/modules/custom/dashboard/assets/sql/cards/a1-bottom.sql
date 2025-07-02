CREATE OR REPLACE VIEW base_revenue AS
SELECT
  YEAR(node__field_date.field_date_value) AS `Year`,
  SUM(node__field_est_revenue.field_est_revenue_value) AS `Revenue`
FROM node
LEFT JOIN node_field_data
  ON node.nid = node_field_data.nid
  AND node.vid = node_field_data.vid
  AND node.type = node_field_data.type
LEFT JOIN node__field_date
  ON node.nid = node__field_date.entity_id
  AND node.type = node__field_date.bundle
LEFT JOIN node__field_est_revenue
  ON node.nid = node__field_est_revenue.entity_id
  AND node.type = node__field_est_revenue.bundle
LEFT JOIN node__field_partner
  ON node.nid = node__field_partner.entity_id
  AND node__field_partner.bundle = 'analytics'
LEFT JOIN `groups`
  ON node__field_partner.field_partner_target_id = `groups`.id
  AND `groups`.type = 'partner'
LEFT JOIN groups_field_data
  ON `groups`.id = groups_field_data.id
  AND `groups`.type = groups_field_data.type
  AND `groups`.langcode = groups_field_data.langcode
WHERE node.type = 'analytics'
  AND node__field_date.field_date_value IS NOT NULL
  AND node__field_est_revenue.field_est_revenue_value IS NOT NULL
  AND DAYOFYEAR(node__field_date.field_date_value) <= DAYOFYEAR(CURRENT_DATE())
GROUP BY `Year`;

SET @yoy_revenue = (
  SELECT ROUND(
    CASE
      WHEN b2.Revenue IS NULL OR b2.Revenue = 0 THEN 0
      ELSE ((b1.Revenue - b2.Revenue) / b2.Revenue) * 100
    END,
    2
  )
  FROM base_revenue b1
  LEFT JOIN base_revenue b2
    ON b1.Year = b2.Year + 1
  WHERE b1.Year = YEAR(CURRENT_DATE())
);

SELECT @yoy_revenue AS `yoy_percentage`;

SELECT
  b1.Year,
  b1.Revenue,
  b2.Revenue AS Previous_Revenue,
  ROUND(b1.Revenue, 2) AS Formatted_Revenue,
  ROUND(b2.Revenue, 2) AS Formatted_Previous_Revenue,
  CASE
    WHEN b2.Revenue IS NULL OR b2.Revenue = 0 THEN '0%'
    ELSE CONCAT(
      ROUND(((b1.Revenue - b2.Revenue) / b2.Revenue) * 100, 2),
      '%'
    )
  END AS `YoY %`
FROM base_revenue b1
LEFT JOIN base_revenue b2
  ON b1.Year = b2.Year + 1
ORDER BY b1.Year DESC;