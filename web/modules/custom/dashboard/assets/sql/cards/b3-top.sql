WITH `first_attribute` AS
(
SELECT
  `attribute_field_data`.`name` AS `name`,
  SUM(
    `api_volume`.`field_api_volume_in_mil_value`
  ) AS `sum`
FROM
  `node__field_attribute`

LEFT JOIN `node_field_data` AS `node_field_data` ON `node__field_attribute`.`entity_id` = `node_field_data`.`nid`
  LEFT JOIN `taxonomy_term_field_data` AS `attribute_field_data` ON `node__field_attribute`.`field_attribute_target_id` = `attribute_field_data`.`tid`
  LEFT JOIN `node__field_api_volume_in_mil` AS `api_volume` ON `node_field_data`.`nid` = `api_volume`.`entity_id`
  LEFT JOIN `node__field_date` AS `node_date` ON `node_field_data`.`nid` = `node_date`.`entity_id`
WHERE
  (`node__field_attribute`.`bundle` = 'analytics')

   AND (
    `attribute_field_data`.`vid` = 'analytics_attributes'
  )
  AND (
    `node_date`.`field_date_value` BETWEEN DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01') AND CURRENT_DATE()
  )
GROUP BY
  `attribute_field_data`.`name`
ORDER BY
  `sum` DESC,
  `attribute_field_data`.`name` ASC
LIMIT 1
),
`other_attributes` AS (
  SELECT SUM(`source`.`sum`) AS `sum` FROM
(
SELECT
  `attribute_field_data`.`name` AS `name`,
  SUM(
    `api_volume`.`field_api_volume_in_mil_value`
  ) AS `sum`
FROM
  `node__field_attribute`

LEFT JOIN `node_field_data` AS `node_field_data` ON `node__field_attribute`.`entity_id` = `node_field_data`.`nid`
  LEFT JOIN `taxonomy_term_field_data` AS `attribute_field_data` ON `node__field_attribute`.`field_attribute_target_id` = `attribute_field_data`.`tid`
  LEFT JOIN `node__field_api_volume_in_mil` AS `api_volume` ON `node_field_data`.`nid` = `api_volume`.`entity_id`
  LEFT JOIN `node__field_date` AS `node_date` ON `node_field_data`.`nid` = `node_date`.`entity_id`
WHERE
  (`node__field_attribute`.`bundle` = 'analytics')

   AND (
    `attribute_field_data`.`vid` = 'analytics_attributes'
  )
  AND (
    `node_date`.`field_date_value` BETWEEN DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01') AND CURRENT_DATE()
  )
GROUP BY
  `attribute_field_data`.`name`
ORDER BY
  `sum` DESC,
  `attribute_field_data`.`name` ASC
LIMIT 1000
OFFSET 1
) AS `source`
)

SELECT
    `f`.`sum`,
    `o`.`sum`,
    CASE
        WHEN `o`.`sum` IS NULL OR `o`.`sum` = 0 THEN 100
        ELSE
          CEIL(`f`.`sum` * 100 / `o`.`sum`)
    END AS `value`
FROM
    `first_attribute` `f`,
    `other_attributes` `o`
UNION
SELECT '0' AS `sum`, '0' AS `sum`, 100 AS `value`;
