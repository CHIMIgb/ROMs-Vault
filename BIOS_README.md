# BIOS de PlayStation 1

Esta carpeta debe contener los archivos BIOS de PS1 para que el emulador funcione.
**Sin el BIOS, los juegos de PS1 no cargarán.**

## Archivos necesarios

| Archivo          | Región  | MD5                              |
|------------------|---------|----------------------------------|
| scph1001.bin     | NTSC-U  | 924e392ed05558ffdb115408c263dccf |
| scph5502.bin     | PAL     | 32736f17079d0b2b7024407c39bd3050 |

## Dónde conseguirlos

Debes obtener el BIOS de tu propia consola PlayStation 1 física.
Busca "dump PS1 BIOS from console" para instrucciones de cómo extraerlo legalmente.

Verifica el MD5 del archivo antes de subirlo:
- Linux/Mac: `md5sum scph1001.bin`
- Windows:   `certutil -hashfile scph1001.bin MD5`

## Estructura final esperada

```
public/
  bios/
    ps1/
      scph1001.bin   ← NTSC (USA / Japón)
      scph5502.bin   ← PAL  (Europa)
```

## Seguridad

El archivo `.htaccess` de este proyecto bloquea el acceso directo
a esta carpeta desde el navegador. El proxy PHP es el único que
lo lee y lo pasa al emulador con los headers correctos.
