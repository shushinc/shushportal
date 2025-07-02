WITH base AS (
  SELECT
    CASE
      WHEN YEAR(nfd.field_date_value) = YEAR(CURRENT_DATE())
           AND MONTH(nfd.field_date_value) = MONTH(CURRENT_DATE())
           AND DAY(nfd.field_date_value) <= DAY(CURRENT_DATE())
        THEN 'Current'
      WHEN YEAR(nfd.field_date_value) = YEAR(CURRENT_DATE()) - 1
           AND MONTH(nfd.field_date_value) = MONTH(CURRENT_DATE())
           AND DAY(nfd.field_date_value) <= DAY(CURRENT_DATE())
        THEN 'Previous'
    END AS `Period`,

    SUM(COALESCE(nsav.field_success_api_volume_in_mil_value, 0)) AS success_volume,
    SUM(COALESCE(nav.field_api_volume_in_mil_value, 0)) AS total_volume

  FROM node n
  LEFT JOIN node__field_date nfd
    ON n.nid = nfd.entity_id AND n.vid = nfd.revision_id

  LEFT JOIN node__field_success_api_volume_in_mil nsav
    ON n.nid = nsav.entity_id AND n.vid = nsav.revision_id

  LEFT JOIN node__field_api_volume_in_mil nav
    ON n.nid = nav.entity_id AND n.vid = nav.revision_id

  WHERE n.type = 'analytics'
    AND nfd.field_date_value IS NOT NULL
    AND (
      (
        YEAR(nfd.field_date_value) = YEAR(CURRENT_DATE())
        AND MONTH(nfd.field_date_value) = MONTH(CURRENT_DATE())
        AND DAY(nfd.field_date_value) <= DAY(CURRENT_DATE())
      )
      OR (
        YEAR(nfd.field_date_value) = YEAR(CURRENT_DATE()) - 1
        AND MONTH(nfd.field_date_value) = MONTH(CURRENT_DATE())
        AND DAY(nfd.field_date_value) <= DAY(CURRENT_DATE())
      )
    )
  GROUP BY `Period`
),

percentages AS (
  SELECT
    MAX(CASE WHEN Period = 'Current' THEN ROUND(success_volume / NULLIF(total_volume, 0) * 100, 4) END) AS current_success_pct,
    MAX(CASE WHEN Period = 'Previous' THEN ROUND(success_volume / NULLIF(total_volume, 0) * 100, 4) END) AS previous_success_pct
  FROM base
)

SELECT
  current_success_pct AS `Current Success API %`,
  previous_success_pct AS `Previous Success API %`,

  CASE
    WHEN previous_success_pct IS NULL OR previous_success_pct = 0 THEN '0%'
    WHEN current_success_pct IS NULL THEN '0%'
    WHEN current_success_pct = previous_success_pct THEN '0%'
    ELSE CONCAT(
      CASE
        WHEN ((current_success_pct - previous_success_pct) / previous_success_pct) > 0 THEN '+'
        WHEN ((current_success_pct - previous_success_pct) / previous_success_pct) < 0 THEN '-'
        ELSE ''
      END,
      ROUND(ABS((current_success_pct - previous_success_pct) * 100 / previous_success_pct), 1),
      '%'
    )
  END AS `M-o-M Change in Success API %`
FROM percentages;
