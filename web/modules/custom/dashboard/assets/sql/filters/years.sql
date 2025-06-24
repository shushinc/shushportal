SELECT
  CAST(YEAR({node__field_date}.`field_date_value`) AS CHAR) AS `name`
FROM {node__field_date}
GROUP BY
  `name`
ORDER BY
  `name` DESC
