CREATE OR REPLACE FUNCTION public.update_updated_at_column()
  RETURNS TRIGGER AS
$$
BEGIN
  IF new.updated_at IS NULL OR new.updated_at = old.updated_at THEN
    new.updated_at = NOW();
  END IF;
  RETURN new;
END;
$$ LANGUAGE 'plpgsql';
