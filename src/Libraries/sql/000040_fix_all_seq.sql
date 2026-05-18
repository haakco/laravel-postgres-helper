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
        QUOTE_LITERAL(QUOTE_IDENT(pgt.nspname) || '.' || QUOTE_IDENT(s.relname)) ||
        ', GREATEST(COALESCE(MAX(' || QUOTE_IDENT(c.attname) || ') + 1, 1), 1), FALSE) FROM ' ||
        QUOTE_IDENT(pgt.nspname) || '.' || QUOTE_IDENT(t.relname) || ';' AS fix_seq
    FROM
      pg_class AS s
      JOIN pg_namespace AS sn
        ON s.relnamespace = sn.oid
      JOIN pg_depend AS d
        ON s.oid = d.objid
      JOIN pg_class AS t
        ON d.refobjid = t.oid
      JOIN pg_namespace AS pgt
        ON t.relnamespace = pgt.oid
      JOIN pg_attribute AS c
        ON d.refobjid = c.attrelid
        AND d.refobjsubid = c.attnum
    WHERE
      s.relkind = 'S'
      AND sn.nspname = 'public'
      AND pgt.nspname = 'public'
    ORDER BY
      s.relname
    LOOP
      EXECUTE table_record.fix_seq;
    END LOOP;
END;
$$ LANGUAGE 'plpgsql';
