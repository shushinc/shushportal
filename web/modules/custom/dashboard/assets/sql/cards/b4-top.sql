WITH
  `first_group` AS (
    SELECT
      `gfd`.`label` AS `name`,
      SUM(`nfav`.`field_api_volume_in_mil_value`) AS `sum`
    FROM
      `node` AS `n`
      LEFT JOIN `node_field_data` AS `nfd` ON `n`.`nid` = `nfd`.`nid`
      LEFT JOIN `node__field_date` AS `nfdt` ON `n`.`nid` = `nfdt`.`entity_id`
      LEFT JOIN `node__field_api_volume_in_mil` AS `nfav` ON `n`.`nid` = `nfav`.`entity_id`
      LEFT JOIN `node__field_partner` AS `nfp` ON `n`.`nid` = `nfp`.`entity_id`
      LEFT JOIN `groups` AS `g` ON `nfp`.`field_partner_target_id` = `g`.`id`
      LEFT JOIN `groups_field_data` AS `gfd` ON `g`.`id` = `gfd`.`id`
    WHERE
      `n`.`type` = 'analytics'
      AND `nfd`.`type` = 'analytics'
      AND `nfdt`.`bundle` = 'analytics'
      AND `g`.`type` = 'partner'
      AND (
        `nfdt`.`field_date_value` BETWEEN DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01') AND CURRENT_DATE()
      )
    GROUP BY
      `gfd`.`label`
    ORDER BY
      `sum` DESC,
      `gfd`.`label` ASC
    LIMIT
      1
  ), `other_groups` AS (
    SELECT
      SUM(`source`.`sum`) AS `sum`
    FROM
      (
        SELECT
          `gfd`.`label` AS `name`,
          SUM(`nfav`.`field_api_volume_in_mil_value`) AS `sum`
        FROM
          `node` AS `n`
          LEFT JOIN `node_field_data` AS `nfd` ON `n`.`nid` = `nfd`.`nid`
          LEFT JOIN `node__field_date` AS `nfdt` ON `n`.`nid` = `nfdt`.`entity_id`
          LEFT JOIN `node__field_api_volume_in_mil` AS `nfav` ON `n`.`nid` = `nfav`.`entity_id`
          LEFT JOIN `node__field_partner` AS `nfp` ON `n`.`nid` = `nfp`.`entity_id`
          LEFT JOIN `groups` AS `g` ON `nfp`.`field_partner_target_id` = `g`.`id`
          LEFT JOIN `groups_field_data` AS `gfd` ON `g`.`id` = `gfd`.`id`
        WHERE
          `n`.`type` = 'analytics'
          AND `nfd`.`type` = 'analytics'
          AND `nfdt`.`bundle` = 'analytics'
          AND `g`.`type` = 'partner'
          AND (
            `nfdt`.`field_date_value` BETWEEN DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01') AND CURRENT_DATE()
          )
        GROUP BY
          `gfd`.`label`
        ORDER BY
          `sum` DESC,
          `gfd`.`label` ASC
        LIMIT
          1000
        OFFSET
          1
      ) AS `source`
  )
SELECT
  `f`.`sum`,
  `o`.`sum`,
  IF (`f`.`sum` IS NULL OR `f`.`sum` = 0, 0, CEIL (`f`.`sum` * 100 / `o`.`sum`)) AS `value`
FROM
  `first_group` `f`,
  `other_groups` `o`
UNION
SELECT '0' AS `sum`, '0' AS `sum`, 100 AS `value`;
