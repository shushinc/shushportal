SELECT
  {taxonomy_term_field_data}.`tid` AS `id`,
  {taxonomy_term_field_data}.`name` AS `name`
FROM
  {taxonomy_term_field_data}
WHERE
  {taxonomy_term_field_data}.`vid` = 'analytics_carrier'
