-- =====================================================
-- Tabla: emuladores
-- Emuladores recomendados por consola (principal + alterno).
--   - Una fila por emulador.
--   - es_alterno = FALSE → emulador principal; TRUE → alternativa.
--   - plataformas guarda un CSV: 'PC', 'Android' o 'PC,Android'.
--   - UNIQUE (consola_id, es_alterno) garantiza 1 principal y 1 alterno por consola.
-- Ejecutar después de crear las consolas (data/import_data.sql).
-- =====================================================
CREATE TABLE IF NOT EXISTS public.emuladores (
    id SERIAL PRIMARY KEY,
    consola_id INTEGER NOT NULL REFERENCES public.consolas(id) ON DELETE CASCADE,
    nombre VARCHAR(100) NOT NULL,
    plataformas VARCHAR(100) NOT NULL DEFAULT 'PC',
    url VARCHAR(300) NOT NULL,
    es_alterno BOOLEAN NOT NULL DEFAULT FALSE,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_emulador_por_consola UNIQUE (consola_id, es_alterno)
);

-- Índice para el lookup por consola en la ficha pública
CREATE INDEX IF NOT EXISTS idx_emuladores_consola ON public.emuladores (consola_id);

-- =====================================================
-- Seeds — emuladores por consola (ids 1-15 de import_data.sql)
-- =====================================================

-- 1. PSP
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (1, 'PPSSPP', 'PC,Android', 'https://www.ppsspp.org/', FALSE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (1, 'RetroArch (core PPSSPP)', 'PC,Android', 'https://www.retroarch.com/', TRUE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;

-- 2. PSX
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (2, 'DuckStation', 'PC,Android', 'https://www.duckstation.org/', FALSE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (2, 'ePSXe', 'PC,Android', 'https://www.epsxe.com/', TRUE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;

-- 3. PS2
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (3, 'PCSX2', 'PC', 'https://pcsx2.net/', FALSE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (3, 'AetherSX2', 'Android', 'https://www.aethersx2.com/', TRUE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;

-- 4. Game Boy Advance
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (4, 'mGBA', 'PC,Android', 'https://mgba.io/', FALSE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (4, 'RetroArch (core mGBA)', 'PC,Android', 'https://www.retroarch.com/', TRUE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;

-- 5. Game Boy Color
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (5, 'mGBA', 'PC,Android', 'https://mgba.io/', FALSE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (5, 'SameBoy', 'PC', 'https://sameboy.github.io/', TRUE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;

-- 6. Game Boy
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (6, 'SameBoy', 'PC', 'https://sameboy.github.io/', FALSE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (6, 'mGBA', 'PC,Android', 'https://mgba.io/', TRUE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;

-- 7. Nintendo DS
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (7, 'MelonDS', 'PC', 'https://melonds.kuribo64.net/', FALSE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (7, 'DraStic', 'Android', 'https://www.drastic-ds.com/', TRUE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;

-- 8. Nintendo 64
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (8, 'Project64', 'PC', 'https://www.pj64-emu.com/', FALSE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (8, 'Mupen64Plus', 'PC,Android', 'https://www.mupen64plus.org/', TRUE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;

-- 9. Gamecube
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (9, 'Dolphin', 'PC,Android', 'https://dolphin-emu.org/', FALSE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (9, 'RetroArch (core Dolphin)', 'PC', 'https://www.retroarch.com/', TRUE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;

-- 10. Wii
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (10, 'Dolphin', 'PC,Android', 'https://dolphin-emu.org/', FALSE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (10, 'RetroArch (core Dolphin)', 'PC', 'https://www.retroarch.com/', TRUE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;

-- 11. NES
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (11, 'Mesen', 'PC', 'https://www.mesen.ca/', FALSE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (11, 'RetroArch (core Nestopia UE)', 'PC,Android', 'https://www.retroarch.com/', TRUE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;

-- 12. SNES
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (12, 'Snes9x', 'PC,Android', 'https://www.snes9x.com/', FALSE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (12, 'RetroArch (core Snes9x)', 'PC,Android', 'https://www.retroarch.com/', TRUE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;

-- 13. Dreamcast
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (13, 'Flycast', 'PC,Android', 'https://flycast.do/', FALSE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (13, 'Redream', 'PC,Android', 'https://redream.io/', TRUE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;

-- 14. Saturn
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (14, 'RetroArch (core Beetle Saturn)', 'PC', 'https://www.retroarch.com/', FALSE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (14, 'Yaba Sanshiro', 'PC,Android', 'https://www.uoyabause.org/', TRUE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;

-- 15. Genesis
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (15, 'Kega Fusion', 'PC', 'http://www.carpeludum.com/kega-fusion/', FALSE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;
INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno) VALUES (15, 'RetroArch (core Genesis Plus GX)', 'PC,Android', 'https://www.retroarch.com/', TRUE) ON CONFLICT (consola_id, es_alterno) DO NOTHING;
