CREATE OR REPLACE FUNCTION public.update_date_columns_default()
  RETURNS VOID
AS
$$
DECLARE
  table_record RECORD;
BEGIN
  FOR table_record IN
    SELECT
      QUOTE_IDENT(t.table_schema) || '.' || QUOTE_IDENT(t.table_name) AS table_name,
      QUOTE_IDENT(c.column_name) AS column_name
    FROM
      information_schema.tables t
      JOIN information_schema.columns c
        ON t.table_name = c.table_name
        AND t.table_schema = c.table_schema
        AND c.column_name IN ('created_at', 'updated_at')
        AND (
          c.column_default <> 'now()' OR
            c.column_default IS NULL
          )
        AND c.data_type LIKE '%timestamp%'
    WHERE
      t.table_type = 'BASE TABLE'
    ORDER BY
      t.table_schema,
      t.table_name
    LOOP
      EXECUTE 'ALTER TABLE ' || table_record.table_name || ' ALTER COLUMN ' || table_record.column_name ||
        ' SET DEFAULT NOW()';
    END LOOP;
END;
$$ LANGUAGE 'plpgsql';
