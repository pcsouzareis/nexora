ALTER TABLE n006
    DROP CONSTRAINT IF EXISTS ck_n006_vis006;

ALTER TABLE n006
    ALTER COLUMN vis006 DROP DEFAULT;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = current_schema()
          AND table_name = 'n006'
          AND column_name = 'vis006'
          AND data_type <> 'smallint'
    ) THEN
        EXECUTE $ddl$
            ALTER TABLE n006
                ALTER COLUMN vis006 TYPE SMALLINT
                USING CASE vis006
                    WHEN 'Público' THEN 1
                    WHEN 'Interno' THEN 2
                    WHEN 'Restrito' THEN 3
                END
        $ddl$;
    END IF;
END $$;

ALTER TABLE n006
    ALTER COLUMN vis006 SET DEFAULT 2;

ALTER TABLE n006
    ADD CONSTRAINT ck_n006_vis006
    CHECK (vis006 IN (1, 2, 3));
