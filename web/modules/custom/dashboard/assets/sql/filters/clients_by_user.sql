SELECT DISTINCT
  g.`id` AS `id`,
  g.`label` AS `name`
FROM {group_relationship_field_data} gr
INNER JOIN {groups_field_data} g
  ON g.`id` = gr.`gid`
WHERE gr.`plugin_id` = 'group_membership'
  AND gr.`entity_id` = :user_id
ORDER BY g.`label`
