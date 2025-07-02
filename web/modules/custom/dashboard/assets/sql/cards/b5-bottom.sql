WITH
  `first_group` AS (
  SELECT
    COALESCE(SUM(`source`.`sum`), 0) AS `sum`
    FROM (
    SELECT
        SUM({node__field_api_volume_in_mil}.`field_api_volume_in_mil_value`) AS `sum`
      FROM
        {node}
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
        AND (
          {node__field_date}.`field_date_value` BETWEEN DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01') AND CURRENT_DATE()
        )
      GROUP BY
        {groups_field_data}.`label`
      ORDER BY
        `sum` DESC,
        {groups_field_data}.`label` ASC
      LIMIT
        10
      )
    AS `source`
  ), `other_groups` AS (
  SELECT
    COALESCE(SUM(`source`.`sum`), 0) AS `sum`
    FROM (
    SELECT
        SUM({node__field_api_volume_in_mil}.`field_api_volume_in_mil_value`) AS `sum`
      FROM
        {node}
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
        AND (
          {node__field_date}.`field_date_value` BETWEEN DATE_FORMAT(DATE_SUB(CURRENT_DATE(), INTERVAL 1 YEAR), '%Y-%m-01')
      AND DATE_SUB(CURRENT_DATE(), INTERVAL 1 YEAR)
        )
      GROUP BY
        {groups_field_data}.`label`
      ORDER BY
        `sum` DESC,
        {groups_field_data}.`label` ASC
      LIMIT
        10
      )
    AS `source`
  )
SELECT
  `f`.`sum`,
  `o`.`sum`,
  CASE
    WHEN `f`.`sum` IS NULL OR `f`.`sum` = 0 OR `o`.`sum` IS NULL OR `o`.`sum` = 0 THEN '-100%'
    ELSE
      CONCAT(
        IF (`f`.`sum` * 100 / `o`.`sum`, '+', ''),
        TRUNCATE (`f`.`sum` * 100 / `o`.`sum`, 1),
        '%'
      )
    END AS `ratio`
FROM
  `first_group` `f`,
  `other_groups` `o`;
