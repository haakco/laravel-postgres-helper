CREATE OR REPLACE FUNCTION public.fix_all_seq(
)
  RETURNS VOID
AS
$$
DECLARE
  table_record RECORD;
BEGIN
  FOR table_record IN
    SELECT
      'SELECT SETVAL(' ||
        QUOTE_LITERAL(QUOTE_IDENT(pgt.schemaname) || '.' || QUOTE_IDENT(s.relname)) ||
        ', COALESCE(MAX(' || QUOTE_IDENT(c.attname) || ') + 1, 1),FALSE) FROM ' ||
        QUOTE_IDENT(pgt.schemaname) || '.' || QUOTE_IDENT(t.relname) || ';' AS fix_seq
    FROM
      pg_class AS s,
      pg_depend AS d,
      pg_class AS t,
      pg_attribute AS c,
      pg_tables AS pgt
    WHERE
      s.relkind = 'S'
      AND s.oid = d.objid
      AND d.refobjid = t.oid
      AND d.refobjid = c.attrelid
      AND d.refobjsubid = c.attnum
      AND t.relname = pgt.tablename
      AND pgt.schemaname NOT IN ('pg_catalog', 'information_schema', 'tiger', 'pg_toast','mssql')
      AND pgt.schemaname NOT like '%timescaledb%'
    ORDER BY
      s.relname
    LOOP
      EXECUTE table_record.fix_seq;
    END LOOP;
END;
$$ LANGUAGE 'plpgsql';
