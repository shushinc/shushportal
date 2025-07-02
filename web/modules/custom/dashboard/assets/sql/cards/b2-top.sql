SELECT
  COALESCE(SUM(COALESCE({node__field_success_api_volume_in_mil}.`field_success_api_volume_in_mil_value`, 0)), 0) AS `success_volume`,
  COALESCE(SUM(COALESCE({node__field_api_volume_in_mil}.`field_api_volume_in_mil_value`, 0)), 0) AS `value`
FROM {node}
LEFT JOIN {node_field_data}
  ON {node}.`nid` = {node_field_data}.`nid`
  AND {node}.`vid` = {node_field_data}.`vid`
  AND {node}.`type` = {node_field_data}.`type`

LEFT JOIN {node__field_date}
  ON {node}.`nid` = {node__field_date}.`entity_id`
  AND {node}.`vid` = {node__field_date}.`revision_id`
  AND {node}.`type` = {node__field_date}.`bundle`

LEFT JOIN {node__field_success_api_volume_in_mil}
  ON {node}.`nid` = {node__field_success_api_volume_in_mil}.`entity_id`
  AND {node}.`vid` = {node__field_success_api_volume_in_mil}.`revision_id`
  AND {node}.`type` = {node__field_success_api_volume_in_mil}.`bundle`

LEFT JOIN {node__field_api_volume_in_mil}
  ON {node}.`nid` = {node__field_api_volume_in_mil}.`entity_id`
  AND {node}.`vid` = {node__field_api_volume_in_mil}.`revision_id`
  AND {node}.`type` = {node__field_api_volume_in_mil}.`bundle`

LEFT JOIN {node__field_partner}
  ON {node}.`nid` = {node__field_partner}.`entity_id`
  AND {node}.`vid` = {node__field_partner}.`revision_id`
  AND {node__field_partner}.`bundle` = 'analytics'

LEFT JOIN {groups}
  ON {node__field_partner}.`field_partner_target_id` = {groups}.`id`
  AND {groups}.`type` = 'partner'

LEFT JOIN {groups_field_data}
  ON {groups}.`id` = {groups_field_data}.`id`
  AND {groups}.`revision_id` = {groups_field_data}.`revision_id`
  AND {groups}.`type` = {groups_field_data}.`type`
  AND {groups}.`langcode` = {groups_field_data}.`langcode`

WHERE {node}.`type` = 'analytics'
  AND {node__field_date}.`field_date_value` IS NOT NULL
  AND MONTH({node__field_date}.`field_date_value`) = MONTH(CURRENT_DATE())
  AND YEAR({node__field_date}.`field_date_value`) = YEAR(CURRENT_DATE())
  AND DAY({node__field_date}.`field_date_value`) <= DAY(CURRENT_DATE())
