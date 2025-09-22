WITH
customers AS (
  SELECT node__field_partner.field_partner_target_id AS `partner_id`, CAST(SUM(node__field_api_volume_in_mil.field_api_volume_in_mil_value) AS SIGNED) AS `partner_total` from node
  LEFT JOIN node__field_date ON node.nid = node__field_date.entity_id
  LEFT JOIN node__field_api_volume_in_mil ON node.nid = node__field_api_volume_in_mil.entity_id
  LEFT JOIN node__field_partner ON node.nid = node__field_partner.entity_id
  WHERE `type` = 'analytics'
  AND node__field_date.bundle = 'analytics'
  AND node__field_date.field_date_value >= :start_date
  AND node__field_date.field_date_value <= :end_date
  GROUP BY node__field_partner.field_partner_target_id
  ORDER BY `partner_total` DESC
  LIMIT 1
)
SELECT COALESCE(SUM(partner_total), 0) AS `total` FROM customers;
