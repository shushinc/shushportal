WITH
  `current_period` AS (
    SELECT
      {taxonomy_term_field_data}.`name` AS `name`,
      SUM({node__field_api_volume_in_mil}.`field_api_volume_in_mil_value`) AS `sum`
    FROM
      {node__field_attribute}
      LEFT JOIN {node_field_data} ON {node__field_attribute}.`entity_id` = {node_field_data}.`nid`
      LEFT JOIN {taxonomy_term_field_data} ON {node__field_attribute}.`field_attribute_target_id` = {taxonomy_term_field_data}.`tid`
      LEFT JOIN {node__field_api_volume_in_mil} ON {node_field_data}.`nid` = {node__field_api_volume_in_mil}.`entity_id`
      LEFT JOIN {node__field_date} ON {node_field_data}.`nid` = {node__field_date}.`entity_id`
    WHERE
      {node__field_attribute}.`bundle` = 'analytics'
      AND {taxonomy_term_field_data}.`vid` = 'analytics_attributes'
      AND {node__field_date}.`field_date_value` BETWEEN DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01') AND CURRENT_DATE()
    GROUP BY
      {taxonomy_term_field_data}.`name`
    ORDER BY
      `sum` DESC
    LIMIT
      1
  ),
  `previous_period` AS (
    SELECT
      {taxonomy_term_field_data}.`name` AS `name`,
      SUM({node__field_api_volume_in_mil}.`field_api_volume_in_mil_value`) AS `sum`
    FROM
      {node__field_attribute}
      LEFT JOIN {node_field_data} ON {node__field_attribute}.`entity_id` = {node_field_data}.`nid`
      LEFT JOIN {taxonomy_term_field_data} ON {node__field_attribute}.`field_attribute_target_id` = {taxonomy_term_field_data}.`tid`
      LEFT JOIN {node__field_api_volume_in_mil} ON {node_field_data}.`nid` = {node__field_api_volume_in_mil}.`entity_id`
      LEFT JOIN {node__field_date} ON {node_field_data}.`nid` = {node__field_date}.`entity_id`
    WHERE
      {node__field_attribute}.`bundle` = 'analytics'
      AND {taxonomy_term_field_data}.`vid` = 'analytics_attributes'
      AND {taxonomy_term_field_data}.`name` = (
        SELECT
          `name`
        FROM
          `current_period`
      )
      AND {node__field_date}.`field_date_value` BETWEEN DATE_FORMAT(
        DATE_SUB(CURRENT_DATE(), INTERVAL 1 YEAR),
        '%Y-%m-01'
      ) AND DATE_SUB(CURRENT_DATE(), INTERVAL 1 YEAR)
    GROUP BY
      {taxonomy_term_field_data}.`name`
  )
SELECT
  `c`.`name`,
  `c`.`sum` AS `current_sum`,
  COALESCE(`p`.`sum`, 0) AS `previous_sum`,
  (`c`.`sum` - COALESCE(`p`.`sum`, 0)) AS `difference`,
TRUNCATE (
  CASE
    WHEN `c`.`sum` IS NULL OR `c`.`sum` = 0 THEN 100.0
    WHEN `p`.`sum` IS NULL OR `p`.`sum` = 0 THEN 100.0
    ELSE ABS((`c`.`sum` - `p`.`sum`) * 100 / `p`.`sum`)
  END,
  1
) AS `percentage`,
CONCAT(
  CASE
    WHEN `p`.`sum` = 0
    OR (`c`.`sum` - `p`.`sum`) >= 0 THEN '+'
    ELSE '-'
  END,
  TRUNCATE (
    CASE
      WHEN `p`.`sum` IS NULL OR `p`.`sum` = 0 THEN 100.0
      ELSE ABS((`c`.`sum` - `p`.`sum`) * 100 / `p`.`sum`)
    END,
    1
  ),
  '%'
) AS `MoM_change`
FROM
  `current_period` `c`
  LEFT JOIN `previous_period` `p` ON `c`.`name` = `p`.`name`;
