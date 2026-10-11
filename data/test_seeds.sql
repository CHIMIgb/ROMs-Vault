-- Seeds para la BD de prueba (roms-vault-test)
-- Rol admin + usuarios de prueba para los tests de integración.
--
-- Los secretos NO se guardan aquí: IntegrationTestCase::resetDatabase()
-- sustituye los placeholders __ADMIN_PASSWORD_HASH__ y __TFA_SECRET__ en
-- runtime a partir de tests/.env.test (TEST_ADMIN_PASSWORD / TEST_TFA_SECRET).

INSERT INTO public.roles (id, nombre, descripcion) VALUES
    (1, 'admin', 'Administrador'),
    (2, 'usuario', 'Usuario estándar')
ON CONFLICT (id) DO NOTHING;

INSERT INTO public.personas (id, nombre, apellido, email) VALUES
    (1, 'Admin', 'Prueba', 'admin@test.local'),
    (2, 'Admin', 'TFA', 'admin.tfa@test.local')
ON CONFLICT (id) DO NOTHING;

INSERT INTO public.usuarios (id, persona_id, username, password_hash, rol_id, activo, login_failed_attempts, locked_until, tfa_secret, tfa_enabled) VALUES
    (1, 1, 'admin', '__ADMIN_PASSWORD_HASH__', 1, TRUE, 0, NULL, NULL, FALSE),
    (2, 2, 'admin_tfa', '__ADMIN_PASSWORD_HASH__', 1, TRUE, 0, NULL, '__TFA_SECRET__', TRUE)
ON CONFLICT (id) DO NOTHING;
