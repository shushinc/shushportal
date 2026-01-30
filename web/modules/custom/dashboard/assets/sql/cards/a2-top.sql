SELECT
  YEAR({node__field_date}.`field_date_value`) AS `Year`,
  MONTH({node__field_date}.`field_date_value`) AS `Month`,
  SUM({node__field_est_revenue}.`field_est_revenue_value`) * 1000000 AS `value`
FROM {node}
LEFT JOIN {node_field_data}
  ON {node}.`nid` = {node_field_data}.`nid`
  AND {node}.`vid` = {node_field_data}.`vid`
  AND {node}.`type` = {node_field_data}.`type`

LEFT JOIN {node__field_date}
  ON {node}.`nid` = {node__field_date}.`entity_id`
  AND {node}.`type` = {node__field_date}.`bundle`

LEFT JOIN {node__field_est_revenue}
  ON {node}.`nid` = {node__field_est_revenue}.`entity_id`
  AND {node}.`type` = {node__field_est_revenue}.`bundle`

LEFT JOIN {node__field_partner}
  ON {node}.`nid` = {node__field_partner}.`entity_id`
  AND {node__field_partner}.`bundle` = 'analytics'

LEFT JOIN {groups}
  ON {node__field_partner}.`field_partner_target_id` = {groups}.`id`
  AND {groups}.`type` = 'partner'

LEFT JOIN {groups_field_data}
  ON {groups}.`id` = {groups_field_data}.`id`
  AND {groups}.`type` = {groups_field_data}.`type`
  AND {groups}.`langcode` = {groups_field_data}.`langcode`

WHERE {node}.`type` = 'analytics'
  AND YEAR({node__field_date}.`field_date_value`) = YEAR(CURRENT_DATE())
  AND MONTH({node__field_date}.`field_date_value`) = MONTH(CURRENT_DATE())
  AND DAY({node__field_date}.`field_date_value`) <= DAY(CURRENT_DATE())
GROUP BY `Year`, `Month`
