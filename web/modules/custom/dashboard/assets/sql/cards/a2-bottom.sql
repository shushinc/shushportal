WITH base AS (
  SELECT
    YEAR({node__field_date}.`field_date_value`) AS `Year`,
    MONTH({node__field_date}.`field_date_value`) AS `Month`,
    SUM({node__field_est_revenue}.`field_est_revenue_value`) AS `Revenue`
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
    AND {node__field_date}.`field_date_value` IS NOT NULL
    AND {node__field_est_revenue}.`field_est_revenue_value` IS NOT NULL
    AND (
      (YEAR({node__field_date}.`field_date_value`) = YEAR(CURRENT_DATE()) AND MONTH({node__field_date}.`field_date_value`) = MONTH(CURRENT_DATE()))
      OR
      (YEAR({node__field_date}.`field_date_value`) = YEAR(CURRENT_DATE()) - 1 AND MONTH({node__field_date}.`field_date_value`) = MONTH(CURRENT_DATE()))
    )
    AND DAYOFMONTH({node__field_date}.`field_date_value`) <= DAYOFMONTH(CURRENT_DATE())
  GROUP BY `Year`, `Month`
),
prepared AS (
  SELECT
    MAX(CASE WHEN `Year` = YEAR(CURRENT_DATE()) THEN `Revenue` END) AS `Current_Revenue`,
    MAX(CASE WHEN `Year` = YEAR(CURRENT_DATE()) - 1 THEN `Revenue` END) AS `Previous_Revenue`
  FROM base
)

SELECT
  'Current' AS `Period`,
  `Current_Revenue` AS `Revenue`,
  CASE
    WHEN `Current_Revenue` >= 1e6 THEN CONCAT('$', ROUND(`Current_Revenue` / 1e6, 2), 'T')
    WHEN `Current_Revenue` >= 1e3 THEN CONCAT('$', ROUND(`Current_Revenue` / 1e3, 2), 'B')
    ELSE CONCAT('$', ROUND(`Current_Revenue`, 2), 'M')
  END AS `Formatted_Revenue`,
  CASE
    WHEN `Previous_Revenue` IS NULL OR `Previous_Revenue` = 0 THEN '0%'
    ELSE
      CONCAT(
        CASE
          WHEN ((`Current_Revenue` - `Previous_Revenue`) / `Previous_Revenue`) > 0 THEN '+'
          WHEN ((`Current_Revenue` - `Previous_Revenue`) / `Previous_Revenue`) < 0 THEN '-'
          ELSE ''
        END,
        CASE
          WHEN ABS((`Current_Revenue` - `Previous_Revenue`) * 100.0 / `Previous_Revenue`) >= 100 THEN
            ROUND(ABS((`Current_Revenue` - `Previous_Revenue`) * 100.0 / `Previous_Revenue`), 0)
          WHEN ABS((`Current_Revenue` - `Previous_Revenue`) * 100.0 / `Previous_Revenue`) >= 10 THEN
            ROUND(ABS((`Current_Revenue` - `Previous_Revenue`) * 100.0 / `Previous_Revenue`), 1)
          ELSE
            ROUND(ABS((`Current_Revenue` - `Previous_Revenue`) * 100.0 / `Previous_Revenue`), 2)
        END,
        '%'
      )
  END AS `M-o-M %`

FROM prepared

UNION ALL

SELECT
  'Previous' AS `Period`,
  `Previous_Revenue` AS `Revenue`,
  CASE
    WHEN `Previous_Revenue` >= 1e6 THEN CONCAT('$', ROUND(`Previous_Revenue` / 1e6, 2), 'T')
    WHEN `Previous_Revenue` >= 1e3 THEN CONCAT('$', ROUND(`Previous_Revenue` / 1e3, 2), 'B')
    ELSE CONCAT('$', ROUND(`Previous_Revenue`, 2), 'M')
  END AS `Formatted_Revenue`,
  NULL AS `M-o-M %`

FROM prepared;


