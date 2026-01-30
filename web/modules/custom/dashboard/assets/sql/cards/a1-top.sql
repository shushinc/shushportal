SELECT
  YEAR(CURRENT_DATE()) AS `year`,
  COALESCE(
    SUM(node__field_est_revenue.field_est_revenue_value) * 1000000,
    0
  ) AS `value`
FROM node
LEFT JOIN node_field_data
  ON node.nid = node_field_data.nid
  AND node.vid = node_field_data.vid
LEFT JOIN node__field_date
  ON node.nid = node__field_date.entity_id
LEFT JOIN node__field_est_revenue
  ON node.nid = node__field_est_revenue.entity_id
LEFT JOIN node__field_partner
  ON node.nid = node__field_partner.entity_id
LEFT JOIN `groups`
  ON node__field_partner.field_partner_target_id = `groups`.id
LEFT JOIN groups_field_data
  ON `groups`.id = groups_field_data.id
WHERE node.type = 'analytics'
  AND node__field_date.field_date_value IS NOT NULL
  AND node__field_est_revenue.field_est_revenue_value IS NOT NULL
  AND YEAR(node__field_date.field_date_value) = YEAR(CURRENT_DATE())
  AND DAYOFYEAR(node__field_date.field_date_value) <= DAYOFYEAR(CURRENT_DATE());

