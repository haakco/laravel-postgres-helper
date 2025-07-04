-- UUID Helper Functions for PostgreSQL
-- Added in laravel-postgres-helper v4.0.0

-- Function to generate UUID if null (commonly used in triggers)
CREATE OR REPLACE FUNCTION generate_uuid_if_null()
RETURNS trigger AS $$
BEGIN
    IF NEW.id IS NULL THEN
        NEW.id = uuid_generate_v4();
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

COMMENT ON FUNCTION generate_uuid_if_null() IS 
'Trigger function that generates a UUID v4 for the id column if it is null. Added by laravel-postgres-helper v4.0.0';