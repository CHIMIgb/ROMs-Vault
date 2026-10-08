-- Seeds para la BD de prueba (roms-vault-test)
-- Rol admin + usuario de prueba para los tests de integración.
-- Password del usuario: admin123 (solo para pruebas)

INSERT INTO public.roles (id, nombre, descripcion) VALUES
    (1, 'admin', 'Administrador'),
    (2, 'usuario', 'Usuario estándar')
ON CONFLICT (id) DO NOTHING;

INSERT INTO public.personas (id, nombre, apellido, email) VALUES
    (1, 'Admin', 'Prueba', 'admin@test.local'),
    (2, 'Admin', 'TFA', 'admin.tfa@test.local')
ON CONFLICT (id) DO NOTHING;

INSERT INTO public.usuarios (id, persona_id, username, password_hash, rol_id, activo, login_failed_attempts, locked_until, tfa_secret, tfa_enabled) VALUES
    (1, 1, 'admin', '$2y$10$aha7kWQ.0hnOuf56qHUGX.JbZ4jXXeC74.lrYZ.6Mn0YlBDoz479i', 1, TRUE, 0, NULL, NULL, FALSE),
    (2, 2, 'admin_tfa', '$2y$10$aha7kWQ.0hnOuf56qHUGX.JbZ4jXXeC74.lrYZ.6Mn0YlBDoz479i', 1, TRUE, 0, NULL, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', TRUE)
ON CONFLICT (id) DO NOTHING;
