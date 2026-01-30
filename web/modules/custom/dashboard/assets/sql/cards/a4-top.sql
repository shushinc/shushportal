SELECT
  COALESCE(SUM({node__field_est_revenue}.`field_est_revenue_value`), 0) * 1e6 AS `value`
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
  AND DATE({node__field_date}.`field_date_value`) = DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY);
