WITH `current_month` AS (
  SELECT
    {groups_field_data}.`label` AS `name`,
    SUM({node__field_api_volume_in_mil}.`field_api_volume_in_mil_value`) AS `sum`
  FROM
    {node} AS {node}
    LEFT JOIN {node_field_data} ON {node}.`nid` = {node_field_data}.`nid`
    LEFT JOIN {node__field_date} ON {node}.`nid` = {node__field_date}.`entity_id`
    LEFT JOIN {node__field_api_volume_in_mil} ON {node}.`nid` = {node__field_api_volume_in_mil}.`entity_id`
    LEFT JOIN {node__field_partner} ON {node}.`nid` = {node__field_partner}.`entity_id`
    LEFT JOIN {groups} ON {node__field_partner}.`field_partner_target_id` = {groups}.`id`
    LEFT JOIN {groups_field_data} ON {groups}.`id` = {groups_field_data}.`id`
  WHERE
    {node}.`type` = 'analytics'
    AND {node_field_data}.`type` = 'analytics'
    AND {node__field_date}.`bundle` = 'analytics'
    AND {groups}.`type` = 'partner'
    AND {node__field_date}.`field_date_value` BETWEEN DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01') AND CURRENT_DATE()
  GROUP BY {groups_field_data}.`label`
  ORDER BY `sum` DESC
  LIMIT 1
),
`last_year_same_period` AS (
  SELECT
    {groups_field_data}.`label` AS `name`,
    SUM({node__field_api_volume_in_mil}.`field_api_volume_in_mil_value`) AS `sum`
  FROM
    {node} AS {node}
    LEFT JOIN {node_field_data} ON {node}.`nid` = {node_field_data}.`nid`
    LEFT JOIN {node__field_date} ON {node}.`nid` = {node__field_date}.`entity_id`
    LEFT JOIN {node__field_api_volume_in_mil} ON {node}.`nid` = {node__field_api_volume_in_mil}.`entity_id`
    LEFT JOIN {node__field_partner} ON {node}.`nid` = {node__field_partner}.`entity_id`
    LEFT JOIN {groups} ON {node__field_partner}.`field_partner_target_id` = {groups}.`id`
    LEFT JOIN {groups_field_data} ON {groups}.`id` = {groups_field_data}.`id`
  WHERE
    {node}.`type` = 'analytics'
    AND {node_field_data}.`type` = 'analytics'
    AND {node__field_date}.`bundle` = 'analytics'
    AND {groups}.`type` = 'partner'
    AND {groups_field_data}.`label` = (SELECT `name` FROM `current_month`)
    AND {node__field_date}.`field_date_value` BETWEEN
      DATE_FORMAT(DATE_SUB(CURRENT_DATE(), INTERVAL 1 YEAR), '%Y-%m-01')
      AND DATE_SUB(CURRENT_DATE(), INTERVAL 1 YEAR)
  GROUP BY {groups_field_data}.`label`
)

SELECT
  `c`.`name`,
  `c`.`sum` AS `current_sum`,
  COALESCE(`p`.`sum`, 0) AS `previous_sum`,
  (`c`.`sum` - COALESCE(`p`.`sum`, 0)) AS `difference`,
  TRUNCATE(
    CASE
      WHEN `p`.`sum` IS NULL OR `p`.`sum` = 0 THEN 100.0
      ELSE ABS((`c`.`sum` - `p`.`sum`) * 100 / `p`.`sum`)
    END,
    1
  ) AS `percent_change`,
  CASE
    WHEN `p`.`sum` IS NULL OR `p`.`sum` = 0 THEN '+'
    WHEN `c`.`sum` - `p`.`sum` >= 0 THEN '+'
    ELSE '-'
  END AS `sign`,
  CONCAT(
    CASE
      WHEN `p`.`sum` IS NULL OR `p`.`sum` = 0 THEN '+'
      WHEN `c`.`sum` - `p`.`sum` >= 0 THEN '+'
      ELSE '-'
    END,
    TRUNCATE(
      CASE
        WHEN `p`.`sum` IS NULL OR `p`.`sum` = 0 THEN 100.0
        ELSE ABS((`c`.`sum` - `p`.`sum`) * 100 / `p`.`sum`)
      END,
      1
    ),
    '%'
  ) AS `MoM_change`
FROM
  `current_month` `c`
LEFT JOIN
  `last_year_same_period` `p` ON `c`.`name` = `p`.`name`
UNION
SELECT '' AS `name`, '' AS `current_sum`, '' AS `previous_sum`, '' AS `difference`, '' AS `percent_change`, '' AS `sign`, '-100%' AS `MoM_change`
