-- Function to automatically apply PostgreSQL standards to new tables
CREATE OR REPLACE FUNCTION auto_apply_table_standards()
RETURNS event_trigger
LANGUAGE plpgsql
AS $$
DECLARE
    obj record;
    table_name text;
    has_updated_at boolean;
BEGIN
    -- Loop through all objects created in this command
    FOR obj IN SELECT * FROM pg_event_trigger_ddl_commands() WHERE object_type = 'table'
    LOOP
        -- Extract table name from object identity
        table_name := split_part(obj.object_identity, '.', 2);
        
        -- Skip system tables and temporary tables
        IF table_name IS NULL OR 
           table_name LIKE 'pg_%' OR 
           table_name LIKE 'sql_%' OR
           obj.schema_name != 'public' THEN
            CONTINUE;
        END IF;
        
        -- Check if table has updated_at column
        SELECT EXISTS (
            SELECT 1 
            FROM information_schema.columns 
            WHERE table_schema = 'public' 
            AND table_name = table_name 
            AND column_name = 'updated_at'
        ) INTO has_updated_at;
        
        -- If table has updated_at column, create trigger
        IF has_updated_at THEN
            -- Create the trigger (if it doesn't exist)
            EXECUTE format('
                CREATE TRIGGER update_%I_updated_at
                BEFORE UPDATE ON %I
                FOR EACH ROW
                EXECUTE FUNCTION update_updated_at_column()',
                table_name, table_name
            );
            
            RAISE NOTICE 'Applied updated_at trigger to table: %', table_name;
        END IF;
        
        -- Fix sequences for the table
        PERFORM fix_sequence_for_table(table_name);
        
    END LOOP;
END;
$$;

-- Helper function to fix sequences for a specific table
CREATE OR REPLACE FUNCTION fix_sequence_for_table(p_table_name text)
RETURNS void
LANGUAGE plpgsql
AS $$
DECLARE
    seq_record record;
    max_id bigint;
    column_name text;
BEGIN
    -- Find all sequences associated with this table
    FOR seq_record IN
        SELECT 
            s.sequence_name,
            a.attname as column_name
        FROM information_schema.sequences s
        JOIN pg_class seq ON seq.relname = s.sequence_name
        JOIN pg_depend d ON d.objid = seq.oid
        JOIN pg_class tbl ON tbl.oid = d.refobjid
        JOIN pg_attribute a ON a.attrelid = tbl.oid AND a.attnum = d.refobjsubid
        WHERE tbl.relname = p_table_name
        AND s.sequence_schema = 'public'
    LOOP
        -- Get the maximum value from the column
        EXECUTE format('SELECT COALESCE(MAX(%I), 0) FROM %I', 
                      seq_record.column_name, p_table_name) INTO max_id;
        
        -- Set the sequence to the max value (or 1 if table is empty)
        EXECUTE format('SELECT setval(%L, GREATEST(%s, 1), true)', 
                      seq_record.sequence_name, max_id);
                      
        RAISE NOTICE 'Fixed sequence % for table %.%', 
                    seq_record.sequence_name, p_table_name, seq_record.column_name;
    END LOOP;
END;
$$;

-- Create the event trigger (disabled by default)
-- This will be enabled/disabled via the enable_event_triggers() function
DO $$
BEGIN
    -- Drop the trigger if it exists
    IF EXISTS (
        SELECT 1 FROM pg_event_trigger 
        WHERE evtname = 'auto_apply_standards_trigger'
    ) THEN
        DROP EVENT TRIGGER auto_apply_standards_trigger;
    END IF;
END $$;