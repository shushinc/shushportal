SELECT
  d.date AS Date,
  0 AS `Avg. in ms`
FROM (
  SELECT DATE(CONCAT({{year_filter}}, '-', LPAD(MONTH(STR_TO_DATE({{month_filter}}, '%b')), 2, '0'), '-', LPAD(seq + 1, 2, '0'))) AS date
  FROM (
    SELECT 0 AS seq UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
    UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
    UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14
    UNION ALL SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19
    UNION ALL SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24
    UNION ALL SELECT 25 UNION ALL SELECT 26 UNION ALL SELECT 27 UNION ALL SELECT 28 UNION ALL SELECT 29
    UNION ALL SELECT 30
  ) AS days
  WHERE seq < DAY(LAST_DAY(DATE(CONCAT({{year_filter}}, '-', LPAD(MONTH(STR_TO_DATE({{month_filter}}, '%b')), 2, '0'), '-01'))))
) d

UNION

SELECT
  CAST(`node__field_date`.`field_date_value` AS DATE) AS `Date`,
  AVG(`node__field_average_api_latency_in_mil`.`field_average_api_latency_in_mil_value`) AS `Avg. in ms`
FROM `node`
LEFT JOIN `node_field_data`
  ON `node`.`nid` = `node_field_data`.`nid`
  AND `node`.`vid` = `node_field_data`.`vid`
  AND `node`.`type` = `node_field_data`.`type`
LEFT JOIN `node__field_date`
  ON `node`.`nid` = `node__field_date`.`entity_id`
  AND `node`.`vid` = `node__field_date`.`revision_id`
  AND `node`.`type` = `node__field_date`.`bundle`
LEFT JOIN `node__field_attribute`
  ON `node__field_attribute`.`entity_id` = `node`.`nid`
  AND `node__field_attribute`.`revision_id` = `node`.`vid`
LEFT JOIN `taxonomy_term_field_data` AS `taxonomy_term_field_data_0`
  ON `taxonomy_term_field_data_0`.`tid` = `node__field_attribute`.`field_attribute_target_id`
  AND `node__field_attribute`.`bundle` = 'analytics'
  AND `taxonomy_term_field_data_0`.`vid` = 'analytics_attributes'
LEFT JOIN `node__field_carrier`
  ON `node__field_carrier`.`entity_id` = `node`.`nid`
  AND `node__field_carrier`.`revision_id` = `node`.`vid`
LEFT JOIN `taxonomy_term_field_data` AS `taxonomy_term_field_data_1`
  ON `taxonomy_term_field_data_1`.`tid` = `node__field_carrier`.`field_carrier_target_id`
  AND `node__field_carrier`.`bundle` = 'analytics'
  AND `taxonomy_term_field_data_1`.`vid` = 'analytics_carrier'
LEFT JOIN `node__field_partner`
  ON `node`.`nid` = `node__field_partner`.`entity_id`
  AND `node`.`vid` = `node__field_partner`.`revision_id`
  AND `node__field_partner`.`bundle` = 'analytics'
LEFT JOIN `groups`
  ON `node__field_partner`.`field_partner_target_id` = `groups`.`id`
  AND `groups`.`type` = 'partner'
LEFT JOIN `groups_field_data`
  ON `groups`.`id` = `groups_field_data`.`id`
  AND `groups`.`revision_id` = `groups_field_data`.`revision_id`
  AND `groups`.`type` = `groups_field_data`.`type`
  AND `groups`.`langcode` = `groups_field_data`.`langcode`
LEFT JOIN `node__field_end_customer`
  ON `node__field_end_customer`.`entity_id` = `node`.`nid`
  AND `node__field_end_customer`.`revision_id` = `node`.`vid`
LEFT JOIN `taxonomy_term_field_data` AS `taxonomy_term_field_data_2`
  ON `taxonomy_term_field_data_2`.`tid` = `node__field_end_customer`.`field_end_customer_target_id`
  AND `node__field_end_customer`.`bundle` = 'analytics'
  AND `taxonomy_term_field_data_2`.`vid` = 'analytics_customer'
LEFT JOIN `node__field_average_api_latency_in_mil`
  ON `node`.`nid` = `node__field_average_api_latency_in_mil`.`entity_id`
  AND `node`.`vid` = `node__field_average_api_latency_in_mil`.`revision_id`
  AND `node`.`type` = `node__field_average_api_latency_in_mil`.`bundle`
WHERE `node`.`type` = 'analytics'
  [[AND DATE_FORMAT(CAST(`node__field_date`.`field_date_value` AS DATETIME), '%b') = {{month_filter}}]]
  [[AND CAST(YEAR(CAST(`node__field_date`.`field_date_value` AS DATETIME)) AS CHAR) = {{year_filter}}]]
GROUP BY
  CAST(`node__field_date`.`field_date_value` AS DATE),
  CAST(YEAR(CAST(`node__field_date`.`field_date_value` AS DATETIME)) AS CHAR),
  DATE_FORMAT(CAST(`node__field_date`.`field_date_value` AS DATETIME), '%b')
ORDER BY
  `Date` ASC;
