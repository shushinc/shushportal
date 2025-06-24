SELECT
  COALESCE(AVG({node__field_average_api_latency_in_mil}.`field_average_api_latency_in_mil_value`), 0) AS `value`
FROM {node}
LEFT JOIN {node_field_data}
  ON {node}.`nid` = {node_field_data}.`nid`
  AND {node}.`vid` = {node_field_data}.`vid`
  AND {node}.`type` = {node_field_data}.`type`

LEFT JOIN {node__field_date}
  ON {node}.`nid` = {node__field_date}.`entity_id`
  AND {node}.`vid` = {node__field_date}.`revision_id`
  AND {node}.`type` = {node__field_date}.`bundle`

LEFT JOIN {node__field_average_api_latency_in_mil}
  ON {node}.`nid` = {node__field_average_api_latency_in_mil}.`entity_id`
  AND {node}.`vid` = {node__field_average_api_latency_in_mil}.`revision_id`
  AND {node}.`type` = {node__field_average_api_latency_in_mil}.`bundle`

WHERE {node}.`type` = 'analytics'
  AND {node__field_date}.`field_date_value` IS NOT NULL
  AND {node__field_average_api_latency_in_mil}.`field_average_api_latency_in_mil_value` IS NOT NULL
  AND YEAR({node__field_date}.`field_date_value`) = YEAR(CURRENT_DATE())
  AND MONTH({node__field_date}.`field_date_value`) = MONTH(CURRENT_DATE())
  AND DAY({node__field_date}.`field_date_value`) <= DAY(CURRENT_DATE())
