CREATE OR REPLACE FUNCTION public.fix_db(
)
  RETURNS VOID
AS
$$
DECLARE
BEGIN
  PERFORM public.update_updated_at_column_for_tables();
  PERFORM public.update_date_columns_default();
  PERFORM public.fix_all_seq();
END;
$$ LANGUAGE 'plpgsql';
