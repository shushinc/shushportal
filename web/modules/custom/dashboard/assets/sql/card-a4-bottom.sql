WITH base AS (
  SELECT
    DATE({node__field_date}.`field_date_value`) AS `Date`,
    YEAR({node__field_date}.`field_date_value`) AS `Year`,
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

  WHERE {node}.`type` = 'analytics'
    AND (
      DATE({node__field_date}.`field_date_value`) = DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY)
      OR DATE({node__field_date}.`field_date_value`) = DATE_SUB(DATE_SUB(CURRENT_DATE(), INTERVAL 1 YEAR), INTERVAL 1 DAY)
    )
  GROUP BY `Date`, `Year`
),

pivoted AS (
  SELECT
    COALESCE(MAX(CASE WHEN `Date` = DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY) THEN `Revenue` END), 0) AS `Current_Revenue`,
    COALESCE(MAX(CASE WHEN `Date` = DATE_SUB(DATE_SUB(CURRENT_DATE(), INTERVAL 1 YEAR), INTERVAL 1 DAY) THEN `Revenue` END), 0) AS `Previous_Revenue`
  FROM base
)

-- Fila CURRENT
SELECT
  'Current' AS `Period`,
  `Current_Revenue` AS `Revenue`,
  CASE
    WHEN `Current_Revenue` >= 1e6 THEN CONCAT('$', ROUND(`Current_Revenue` / 1e6, 2), 'T')
    WHEN `Current_Revenue` >= 1e3 THEN CONCAT('$', ROUND(`Current_Revenue` / 1e3, 2), 'B')
    ELSE CONCAT('$', ROUND(`Current_Revenue`, 2), 'M')
  END AS `Formatted_Revenue`,
  CASE
    WHEN `Previous_Revenue` = 0 THEN
      CASE
        WHEN `Current_Revenue` = 0 THEN '0.00%'
        ELSE '+100%'
      END
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
  END AS `D-o-D %`,
  (`Current_Revenue` - `Previous_Revenue`) AS `Δ Revenue`

FROM pivoted

UNION ALL

SELECT
  'Previous' AS `Period`,
  `Previous_Revenue`,
  CASE
    WHEN `Previous_Revenue` >= 1e6 THEN CONCAT('$', ROUND(`Previous_Revenue` / 1e6, 2), 'T')
    WHEN `Previous_Revenue` >= 1e3 THEN CONCAT('$', ROUND(`Previous_Revenue` / 1e3, 2), 'B')
    ELSE CONCAT('$', ROUND(`Previous_Revenue`, 2), 'M')
  END,
  '0.00%' AS `D-o-D %`,
  '' AS `Δ Revenue`

FROM pivoted;
