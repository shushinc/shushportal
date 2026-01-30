WITH base AS (
  SELECT
    CASE
      WHEN YEAR({node__field_date}.`field_date_value`) = YEAR(CURRENT_DATE())
           AND MONTH({node__field_date}.`field_date_value`) = MONTH(CURRENT_DATE())
           AND DAY({node__field_date}.`field_date_value`) <= DAY(CURRENT_DATE())
        THEN 'Current'
      WHEN YEAR({node__field_date}.`field_date_value`) = YEAR(CURRENT_DATE()) - 1
           AND MONTH({node__field_date}.`field_date_value`) = MONTH(CURRENT_DATE())
           AND DAY({node__field_date}.`field_date_value`) <= DAY(CURRENT_DATE())
        THEN 'Previous'
    END AS `Period`,
    ROUND(AVG({node__field_average_api_latency_in_mil}.`field_average_api_latency_in_mil_value`), 2) AS `AvgLatency`
  FROM {node}
  LEFT JOIN {node_field_data}
    ON {node}.`nid` = .`nid` =.`nid` AND {node}.`vid` = .`vid` =.`vid` AND {node}.`type` = .`type` =.`type`
  LEFT JOIN {node__field_date}
    ON {node}.`nid` = {node__field_date}.`entity_id` AND {node}.`vid` = {node__field_date}.`revision_id`
  LEFT JOIN {node__field_average_api_latency_in_mil}
    ON {node}.`nid` = {node__field_average_api_latency_in_mil}.`entity_id` AND {node}.`vid` = {node__field_average_api_latency_in_mil}.`revision_id`
  LEFT JOIN {node__field_partner}
    ON {node}.`nid` = {node__field_partner}.`entity_id` AND {node}.`vid` = {node__field_partner}.`revision_id` AND {node__field_partner}.`bundle` = 'analytics'
  LEFT JOIN {groups}
    ON {node__field_partner}.`field_partner_target_id` = {groups}.`id` AND {groups}.`type` = 'partner'
  LEFT JOIN {groups_field_data} gfd
    ON {groups}.`id` = gfd.`id` AND {groups}.`revision_id` = gfd.`revision_id`
    AND {groups}.`type` = gfd.`type` AND {groups}.`langcode` = gfd.`langcode`
  WHERE {node}.`type` = 'analytics'
    AND {node__field_date}.`field_date_value` IS NOT NULL
    AND {node__field_average_api_latency_in_mil}.`field_average_api_latency_in_mil_value` IS NOT NULL
    AND (
      (
        YEAR({node__field_date}.`field_date_value`) = YEAR(CURRENT_DATE())
        AND MONTH({node__field_date}.`field_date_value`) = MONTH(CURRENT_DATE())
        AND DAY({node__field_date}.`field_date_value`) <= DAY(CURRENT_DATE())
      )
      OR (
        YEAR({node__field_date}.`field_date_value`) = YEAR(CURRENT_DATE()) - 1
        AND MONTH({node__field_date}.`field_date_value`) = MONTH(CURRENT_DATE())
        AND DAY({node__field_date}.`field_date_value`) <= DAY(CURRENT_DATE())
      )
    )
  GROUP BY `Period`
),

normalized AS (
  SELECT
    COALESCE(MAX(CASE WHEN Period = 'Current' THEN AvgLatency END), NULL) AS Current_Latency,
    COALESCE(MAX(CASE WHEN Period = 'Previous' THEN AvgLatency END), NULL) AS Previous_Latency
  FROM base
)

SELECT

  CASE
    WHEN Current_Latency IS NULL THEN '0 ms'
    WHEN Current_Latency >= 100 THEN CONCAT(ROUND(Current_Latency, 0), ' ms')
    WHEN Current_Latency >= 10 THEN CONCAT(ROUND(Current_Latency, 1), ' ms')
    ELSE CONCAT(ROUND(Current_Latency, 2), ' ms')
  END AS `MTD Avg Latency`,

  CASE
    WHEN Previous_Latency IS NULL THEN '0 ms'
    WHEN Previous_Latency >= 100 THEN CONCAT(ROUND(Previous_Latency, 0), ' ms')
    WHEN Previous_Latency >= 10 THEN CONCAT(ROUND(Previous_Latency, 1), ' ms')
    ELSE CONCAT(ROUND(Previous_Latency, 2), ' ms')
  END AS `MTD Latency Last Year`,

  CASE
    WHEN Previous_Latency IS NULL OR Previous_Latency = 0 THEN '0%'
    WHEN Current_Latency IS NULL THEN '0%'
    WHEN Current_Latency = Previous_Latency THEN '0%'
    ELSE CONCAT(
      CASE
        WHEN ((Current_Latency - Previous_Latency) / Previous_Latency) > 0 THEN '+'
        WHEN ((Current_Latency - Previous_Latency) / Previous_Latency) < 0 THEN '-'
        ELSE ''
      END,
      CASE
        WHEN ABS((Current_Latency - Previous_Latency) * 100 / Previous_Latency) >= 100 THEN
          ROUND(ABS((Current_Latency - Previous_Latency) * 100 / Previous_Latency), 0)
        WHEN ABS((Current_Latency - Previous_Latency) * 100 / Previous_Latency) >= 10 THEN
          ROUND(ABS((Current_Latency - Previous_Latency) * 100 / Previous_Latency), 1)
        ELSE
          ROUND(ABS((Current_Latency - Previous_Latency) * 100 / Previous_Latency), 2)
      END,
      '%'
    )
  END AS `M-o-M %`
FROM normalized;

