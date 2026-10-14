SELECT DISTINCT
  t.`tid` AS `id`,
  t.`name` AS `name`
FROM {group_relationship_field_data} gr
INNER JOIN {node__field_partner} np
  ON np.`field_partner_target_id` = gr.`gid`
  AND np.`deleted` = 0
INNER JOIN {node_field_data} n
  ON n.`nid` = np.`entity_id`
  AND n.`type` = 'analytics'
  AND n.`status` = 1
INNER JOIN {node__field_end_customer} ec
  ON ec.`entity_id` = n.`nid`
  AND ec.`deleted` = 0
INNER JOIN {taxonomy_term_field_data} t
  ON t.`tid` = ec.`field_end_customer_target_id`
  AND t.`vid` = 'analytics_customer'
WHERE gr.`plugin_id` = 'group_membership'
  AND gr.`entity_id` = :user_id
ORDER BY t.`name`
