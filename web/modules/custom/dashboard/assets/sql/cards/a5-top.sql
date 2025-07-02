SELECT
  YEAR({node__field_date}.`field_date_value`) AS `Year`,
  MONTH({node__field_date}.`field_date_value`) AS `Month`,
  SUM({node__field_api_volume_in_mil}.`field_api_volume_in_mil_value` * 1e6) AS `value`
FROM {node}
LEFT JOIN {node_field_data}
  ON {node}.`nid` = {node_field_data}.`nid`
  AND {node}.`vid` = {node_field_data}.`vid`
  AND {node}.`type` = {node_field_data}.`type`

LEFT JOIN {node__field_date} {node__field_date}
  ON {node}.`nid` = {node__field_date}.`entity_id`
  AND {node}.`vid` = {node__field_date}.`revision_id`
  AND {node}.`type` = {node__field_date}.`bundle`

LEFT JOIN {node__field_api_volume_in_mil}
  ON {node}.`nid` = {node__field_api_volume_in_mil}.`entity_id`
  AND {node}.`vid` = {node__field_api_volume_in_mil}.`revision_id`
  AND {node}.`type` = {node__field_api_volume_in_mil}.`bundle`

LEFT JOIN {node__field_partner}
  ON {node}.`nid` = {node__field_partner}.`entity_id`
  AND {node}.`vid` = {node__field_partner}.`revision_id`
  AND {node__field_partner}.`bundle` = 'analytics'

LEFT JOIN {groups} g
  ON {node__field_partner}.`field_partner_target_id` = g.`id`
  AND g.`type` = 'partner'

LEFT JOIN {groups_field_data}
  ON g.`id` = {groups_field_data}.`id`
  AND g.`revision_id` = {groups_field_data}.`revision_id`
  AND g.`type` = {groups_field_data}.`type`
  AND g.`langcode` = {groups_field_data}.`langcode`

WHERE {node}.`type` = 'analytics'
  AND {node__field_date}.`field_date_value` >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')
  AND {node__field_date}.`field_date_value` <= CURRENT_DATE()
GROUP BY `Year`, `Month`
