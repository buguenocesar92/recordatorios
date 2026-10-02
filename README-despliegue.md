# Despliegue

El sitio se despliega solo al empujar a `main`, con un runner que vive **dentro
del servidor de despliegue**. Esa decisión tiene una consecuencia que conviene entender:
no hacen falta llaves SSH guardadas en GitHub ni abrir el servidor a internet.
El runner sale hacia GitHub a preguntar si hay trabajo; GitHub nunca entra al
servidor. Una llave filtrada no serviría de nada, porque no hay ninguna.

## Qué hace el despliegue, en orden

1. **Comprueba que existe `.env.host`** en el servidor. Ese archivo no se
   versiona —tiene credenciales— y si falta, conviene detenerse ahí y no a mitad
   del arranque.
2. **Respalda la base.** Si la migración sale mal, esto es lo que permite volver.
3. Trae el código, reconstruye la imagen y levanta.
4. **Migra y compila las cachés** de configuración, rutas, vistas y eventos. En
   un proceso largo como Octane eso se paga una vez y se cobra en cada visita.
5. **Vacía la caché del sitio**: cambió el código, así que el HTML guardado antes
   puede no corresponder al que genera la versión nueva.
6. **Recarga Octane.** Sin esto sigue corriendo el código viejo desde memoria
   aunque los archivos ya hayan cambiado — es un error que da tests verdes y
   producción sin actualizar.
7. **Le pregunta al sitio si responde**, hasta un minuto. Que el contenedor esté
   «up» no significa que la aplicación funcione.
8. **Si algo falló, vuelve solo** al commit anterior y levanta con él. Un
   despliegue a medias es peor que uno que no ocurrió.

## Instalar el runner en el VPS

Una sola vez, como usuario sin privilegios (nunca como `root`: el runner ejecuta
lo que venga en el repositorio).

```bash
sudo useradd -m -s /bin/bash runner
sudo usermod -aG docker runner        # para levantar los contenedores
sudo su - runner

mkdir actions-runner && cd actions-runner
curl -o runner.tar.gz -L https://github.com/actions/runner/releases/latest/download/actions-runner-linux-x64.tar.gz
tar xzf runner.tar.gz

# El testigo se saca en: Settings → Actions → Runners → New self-hosted runner.
# Dura una hora.
./config.sh --url https://github.com/TU-ORGANIZACION/TU-SISTEMA \
            --token <TESTIGO> \
            --labels self-hosted,despliegue \
            --name vps-municipal --unattended

sudo ./svc.sh install runner
sudo ./svc.sh start
```

Las etiquetas **`self-hosted,despliegue`** son las que busca el flujo de trabajo.
Si no coinciden, el despliegue se queda esperando un runner para siempre sin
decir por qué.

## Qué configurar en GitHub

En *Settings → Secrets and variables → Actions → Variables*:

| Variable | Para qué | Ejemplo |
|---|---|---|
| `RUTA_DESPLIEGUE` | Dónde vive el repositorio en el VPS | `/opt/TU-SISTEMA` |
| `URL_SALUD` | Qué dirección se consulta para saber si quedó en pie | `http://localhost:8000/up` |

No hacen falta secretos: no hay credenciales que pasarle: el runner ya está
dentro y el `.env` vive en el servidor.

## Preparar el servidor la primera vez

```bash
sudo mkdir -p /opt/TU-SISTEMA && sudo chown runner: /opt/TU-SISTEMA
sudo -u runner git clone https://github.com/TU-ORGANIZACION/TU-SISTEMA /opt/TU-SISTEMA
cd /opt/TU-SISTEMA

# El .env de producción, que NO se versiona
sudo -u runner cp .env.example .env.host
sudo -u runner nano .env.host
```

En ese archivo, lo que **no** puede quedar como viene:

```ini
APP_ENV=production
APP_DEBUG=false            # con true, un error muestra la configuración entera
APP_KEY=                   # generar con: php artisan key:generate --show
MFA_SHOW_CODE=false        # con true, el segundo factor se publica en pantalla
TRUSTED_PROXIES=<rango del ingress>
SESSION_SECURE_COOKIE=true # el sitio va por HTTPS
```

Después, el contenido: los datos vienen en el seeder y los archivos aparte (ver
`README-contenido.md`).

`docker-compose.prod.yml` exige la variable `APP_IMAGE` (imagen:tag a correr) en
CUALQUIER subcomando, no solo en `up` — sin ella, hasta un `ps` o un `config`
fallan explícito. `desplegar.yml` la exporta sola en cada corrida automática;
este primer arranque manual hay que exportarla a mano:

```bash
export APP_IMAGE=ghcr.io/TU-ORGANIZACION/TU-SISTEMA:latest
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec app php artisan migrate --seed --force
```

## Lo que el despliegue NO hace, a propósito

- **No toca los archivos subidos.** Están en un volumen; si el despliegue los
  tocara, un error se llevaría 292 MB de documentos municipales.
- **No corre el seeder.** Solo la primera vez, a mano. Un seeder que corriera en
  cada despliegue borraría el contenido que el editor cargó desde el panel.
- **No abre puertos.** El sitio queda detrás del ingress del ecosistema.
