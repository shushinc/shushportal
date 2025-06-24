WITH base AS (
  SELECT
    CASE
      WHEN YEAR({node__field_date}.field_date_value) = YEAR(CURRENT_DATE())
           AND MONTH({node__field_date}.field_date_value) = MONTH(CURRENT_DATE())
           AND DAY({node__field_date}.field_date_value) <= DAY(CURRENT_DATE())
        THEN 'Current'
      WHEN YEAR({node__field_date}.field_date_value) = YEAR(CURRENT_DATE()) - 1
           AND MONTH({node__field_date}.field_date_value) = MONTH(CURRENT_DATE())
           AND DAY({node__field_date}.field_date_value) <= DAY(CURRENT_DATE())
        THEN 'Previous'
    END AS `Period`,
    SUM(nvol.field_api_volume_in_mil_value) AS `Volume_M`
  FROM {node}
  LEFT JOIN {node__field_date}
    ON {node}.nid = {node__field_date}.entity_id AND {node}.vid = {node__field_date}.revision_id
  LEFT JOIN `node__field_api_volume_in_mil` nvol
    ON {node}.nid = nvol.entity_id AND {node}.vid = nvol.revision_id
  WHERE {node}.type = 'analytics'
    AND (
      (YEAR({node__field_date}.field_date_value) = YEAR(CURRENT_DATE()) AND MONTH({node__field_date}.field_date_value) = MONTH(CURRENT_DATE()) AND DAY({node__field_date}.field_date_value) <= DAY(CURRENT_DATE()))
      OR
      (YEAR({node__field_date}.field_date_value) = YEAR(CURRENT_DATE()) - 1 AND MONTH({node__field_date}.field_date_value) = MONTH(CURRENT_DATE()) AND DAY({node__field_date}.field_date_value) <= DAY(CURRENT_DATE()))
    )
  GROUP BY `Period`
),

final AS (
  SELECT
    COALESCE(MAX(CASE WHEN Period = 'Current' THEN Volume_M END), 0) AS Current_Volume_M,
    COALESCE(MAX(CASE WHEN Period = 'Previous' THEN Volume_M END), 0) AS Previous_Volume_M
  FROM base
)

SELECT

  Current_Volume_M,
  Previous_Volume_M,

  CASE
    WHEN Current_Volume_M * 1e6 >= 1e12 THEN CONCAT(ROUND(Current_Volume_M * 1e6 / 1e12, IF(Current_Volume_M * 1e6 / 1e12 >= 100, 0, IF(Current_Volume_M * 1e6 / 1e12 >= 10, 1, 2))), ' T')
    WHEN Current_Volume_M * 1e6 >= 1e9  THEN CONCAT(ROUND(Current_Volume_M * 1e6 / 1e9,  IF(Current_Volume_M * 1e6 / 1e9  >= 100, 0, IF(Current_Volume_M * 1e6 / 1e9  >= 10, 1, 2))), ' B')
    WHEN Current_Volume_M * 1e6 >= 1e6  THEN CONCAT(ROUND(Current_Volume_M * 1e6 / 1e6,  IF(Current_Volume_M * 1e6 / 1e6  >= 100, 0, IF(Current_Volume_M * 1e6 / 1e6  >= 10, 1, 2))), ' M')
    WHEN Current_Volume_M * 1e6 >= 1e3  THEN CONCAT(ROUND(Current_Volume_M * 1e6 / 1e3,  IF(Current_Volume_M * 1e6 / 1e3  >= 100, 0, IF(Current_Volume_M * 1e6 / 1e3  >= 10, 1, 2))), ' K')
    ELSE ROUND(Current_Volume_M * 1e6, 0)
  END AS `Formatted_Current`,

  CASE
    WHEN Previous_Volume_M * 1e6 >= 1e12 THEN CONCAT(ROUND(Previous_Volume_M * 1e6 / 1e12, IF(Previous_Volume_M * 1e6 / 1e12 >= 100, 0, IF(Previous_Volume_M * 1e6 / 1e12 >= 10, 1, 2))), ' T')
    WHEN Previous_Volume_M * 1e6 >= 1e9  THEN CONCAT(ROUND(Previous_Volume_M * 1e6 / 1e9,  IF(Previous_Volume_M * 1e6 / 1e9  >= 100, 0, IF(Previous_Volume_M * 1e6 / 1e9  >= 10, 1, 2))), ' B')
    WHEN Previous_Volume_M * 1e6 >= 1e6  THEN CONCAT(ROUND(Previous_Volume_M * 1e6 / 1e6,  IF(Previous_Volume_M * 1e6 / 1e6  >= 100, 0, IF(Previous_Volume_M * 1e6 / 1e6  >= 10, 1, 2))), ' M')
    WHEN Previous_Volume_M * 1e6 >= 1e3  THEN CONCAT(ROUND(Previous_Volume_M * 1e6 / 1e3,  IF(Previous_Volume_M * 1e6 / 1e3  >= 100, 0, IF(Previous_Volume_M * 1e6 / 1e3  >= 10, 1, 2))), ' K')
    ELSE ROUND(Previous_Volume_M * 1e6, 0)
  END AS `Formatted_Previous`,

  CASE
    WHEN Previous_Volume_M IS NULL OR Previous_Volume_M = 0 THEN '0%'
    WHEN Current_Volume_M = Previous_Volume_M THEN '0%'
    ELSE
      CONCAT(
        CASE
          WHEN ((Current_Volume_M - Previous_Volume_M) / Previous_Volume_M) > 0 THEN '+'
          WHEN ((Current_Volume_M - Previous_Volume_M) / Previous_Volume_M) < 0 THEN '-'
          ELSE ''
        END,
        CASE
          WHEN ABS((Current_Volume_M - Previous_Volume_M) * 100.0 / Previous_Volume_M) >= 100 THEN
            ROUND(ABS((Current_Volume_M - Previous_Volume_M) * 100.0 / Previous_Volume_M), 0)
          WHEN ABS((Current_Volume_M - Previous_Volume_M) * 100.0 / Previous_Volume_M) >= 10 THEN
            ROUND(ABS((Current_Volume_M - Previous_Volume_M) * 100.0 / Previous_Volume_M), 1)
          ELSE
            ROUND(ABS((Current_Volume_M - Previous_Volume_M) * 100.0 / Previous_Volume_M), 2)
        END,
        '%'
      )
  END AS `M-o-M %`
FROM final;
