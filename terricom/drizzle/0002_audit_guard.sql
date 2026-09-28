-- Le journal d'audit est en ajout seul : toute modification est refusée,
-- et la suppression n'est autorisée que pour la purge de rétention (> 12 mois),
-- explicitement activée dans la transaction par : SET LOCAL terricom.audit_purge = 'on'.
CREATE OR REPLACE FUNCTION audit_log_guard() RETURNS trigger
  LANGUAGE plpgsql AS $$
BEGIN
  IF TG_OP = 'DELETE'
     AND current_setting('terricom.audit_purge', true) = 'on'
     AND OLD.occurred_at < now() - interval '12 months' THEN
    RETURN OLD;
  END IF;
  RAISE EXCEPTION 'Le journal d''audit est inaltérable (opération % refusée)', TG_OP;
END;
$$;
--> statement-breakpoint
CREATE TRIGGER audit_log_immutable
  BEFORE UPDATE OR DELETE ON audit_log
  FOR EACH ROW EXECUTE FUNCTION audit_log_guard();
--> statement-breakpoint
CREATE TRIGGER audit_log_no_truncate
  BEFORE TRUNCATE ON audit_log
  FOR EACH STATEMENT EXECUTE FUNCTION audit_log_guard();
