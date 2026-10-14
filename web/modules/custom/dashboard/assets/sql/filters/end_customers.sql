SELECT t.name AS name
FROM taxonomy_term_field_data t
WHERE t.vid = 'analytics_customer'
  AND (
    :user_id = 1
    OR EXISTS (
      SELECT 1
      FROM user__roles ur
      WHERE ur.entity_id = :user_id
        AND ur.deleted = 0
        AND ur.roles_target_id IN ('administrator', 'carrier_admin')
    )
  )

UNION

SELECT t.name AS name
FROM group_relationship_field_data gr
JOIN node__field_partner np
  ON np.field_partner_target_id = gr.gid
  AND np.deleted = 0
JOIN node_field_data n
  ON n.nid = np.entity_id
  AND n.type = 'analytics'
  AND n.status = 1
JOIN node__field_end_customer ec
  ON ec.entity_id = n.nid
  AND ec.deleted = 0
JOIN taxonomy_term_field_data t
  ON t.tid = ec.field_end_customer_target_id
  AND t.vid = 'analytics_customer'
WHERE gr.plugin_id = 'group_membership'
  AND gr.entity_id = :user_id
  AND :user_id <> 1
  AND NOT EXISTS (
    SELECT 1
    FROM user__roles ur
    WHERE ur.entity_id = :user_id
      AND ur.deleted = 0
      AND ur.roles_target_id IN ('administrator', 'carrier_admin')
  )

ORDER BY name
LIMIT 1048575