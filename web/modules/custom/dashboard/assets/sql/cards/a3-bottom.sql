WITH ranges AS (
  SELECT
    DATE_SUB(CURRENT_DATE(), INTERVAL ((DAYOFWEEK(CURRENT_DATE()) + 6) % 7 + 1) DAY) AS current_week_end,
    DATE_SUB(CURRENT_DATE(), INTERVAL ((DAYOFWEEK(CURRENT_DATE()) + 6) % 7 + 7) DAY) AS current_week_start
),
revenues AS (
  SELECT
    CASE
      WHEN DATE({node__field_date}.field_date_value) BETWEEN r.current_week_start AND r.current_week_end THEN 'Current'
      WHEN DATE({node__field_date}.field_date_value) BETWEEN
           DATE_SUB(r.current_week_start, INTERVAL 1 YEAR) AND
           DATE_SUB(r.current_week_end, INTERVAL 1 YEAR) THEN 'Previous'
    END AS WeekPeriod,
    SUM({node__field_est_revenue}.field_est_revenue_value) AS Revenue
  FROM ranges r
  JOIN {node} ON {node}.type = 'analytics'
  LEFT JOIN {node__field_date} {node__field_date} ON {node}.nid = {node__field_date}.entity_id AND {node}.type = {node__field_date}.bundle
  LEFT JOIN {node__field_est_revenue} ON {node}.nid = {node__field_est_revenue}.entity_id AND {node}.type = {node__field_est_revenue}.bundle
  WHERE (
    DATE({node__field_date}.field_date_value) BETWEEN r.current_week_start AND r.current_week_end
    OR DATE({node__field_date}.field_date_value) BETWEEN
       DATE_SUB(r.current_week_start, INTERVAL 1 YEAR) AND
       DATE_SUB(r.current_week_end, INTERVAL 1 YEAR)
  )
  GROUP BY WeekPeriod
),
filled AS (
  SELECT 'Current' AS WeekPeriod, COALESCE(MAX(CASE WHEN WeekPeriod = 'Current' THEN Revenue END), 0) AS Revenue FROM revenues
  UNION ALL
  SELECT 'Previous', COALESCE(MAX(CASE WHEN WeekPeriod = 'Previous' THEN Revenue END), 0) FROM revenues
),
formatted AS (
  SELECT
    WeekPeriod,
    Revenue,
    CASE
      WHEN Revenue >= 1e6 THEN CONCAT('$', ROUND(Revenue / 1e6, 2), 'T')
      WHEN Revenue >= 1e3 THEN CONCAT('$', ROUND(Revenue / 1e3, 2), 'B')
      ELSE CONCAT('$', ROUND(Revenue, 2), 'M')
    END AS Formatted_Revenue
  FROM filled
),
pivoted AS (
  SELECT
    MAX(CASE WHEN WeekPeriod = 'Current' THEN Revenue END) AS Current_Revenue,
    MAX(CASE WHEN WeekPeriod = 'Previous' THEN Revenue END) AS Previous_Revenue
  FROM filled
)

SELECT
  f.WeekPeriod,
  f.Revenue,
  f.Formatted_Revenue,
  CASE
    WHEN p.Previous_Revenue = 0 THEN '0%'
    WHEN f.WeekPeriod = 'Current' THEN
      CONCAT(
        CASE
          WHEN ((p.Current_Revenue - p.Previous_Revenue) / p.Previous_Revenue) > 0 THEN '+'
          WHEN ((p.Current_Revenue - p.Previous_Revenue) / p.Previous_Revenue) < 0 THEN '-'
          ELSE ''
        END,
        CASE
          WHEN ABS((p.Current_Revenue - p.Previous_Revenue) * 100.0 / p.Previous_Revenue) >= 100 THEN
            ROUND(ABS((p.Current_Revenue - p.Previous_Revenue) * 100.0 / p.Previous_Revenue), 0)
          WHEN ABS((p.Current_Revenue - p.Previous_Revenue) * 100.0 / p.Previous_Revenue) >= 10 THEN
            ROUND(ABS((p.Current_Revenue - p.Previous_Revenue) * 100.0 / p.Previous_Revenue), 1)
          ELSE
            ROUND(ABS((p.Current_Revenue - p.Previous_Revenue) * 100.0 / p.Previous_Revenue), 2)
        END,
        '%'
      )
    ELSE NULL
  END AS `W-o-W %`
FROM formatted f
CROSS JOIN pivoted p
ORDER BY
  CASE f.WeekPeriod
    WHEN 'Current' THEN 0
    WHEN 'Previous' THEN 1
    ELSE 2
  END;
