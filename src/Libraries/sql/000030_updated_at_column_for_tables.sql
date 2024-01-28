CREATE OR REPLACE FUNCTION public.update_updated_at_column_for_tables()
  RETURNS VOID
AS
$$
DECLARE
  table_record RECORD;
BEGIN
  FOR table_record IN
    SELECT
      t.table_schema || '.' || t.table_name AS table_name,
      t.table_name AS prefix
    FROM
      information_schema.tables t
      JOIN information_schema.columns c
        ON t.table_name = c.table_name
        AND t.table_schema = c.table_schema
        AND c.column_name = 'updated_at'
      LEFT JOIN information_schema.triggers tr
        ON t.table_schema = tr.trigger_schema
        AND t.table_name = tr.event_object_table
        AND tr.action_statement = 'EXECUTE FUNCTION update_updated_at_column()'
    WHERE
      tr.event_object_table IS NULL
    ORDER BY
      t.table_schema,
      t.table_name
    LOOP
      EXECUTE 'CREATE TRIGGER
        ' || table_record.prefix || '_before_update_updated_at BEFORE UPDATE ON ' || table_record.table_name || '
        FOR EACH ROW EXECUTE PROCEDURE update_updated_at_column()';
    END LOOP;
END;
$$ LANGUAGE 'plpgsql';
