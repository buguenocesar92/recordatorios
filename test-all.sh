#!/usr/bin/env bash

# ==============================================================================
# Script de Automatización de Pruebas Completas y Generación de Manuales
# ==============================================================================

GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # Sin color
BOLD='\033[1m'

echo -e "${BOLD}======================================================================"
echo -e "      INICIANDO PIPELINE DE PRUEBAS Y GENERACIÓN DE MANUALES"
echo -e "======================================================================${NC}"

# 1. Verificar contenedores Docker
echo -e "\n${BOLD}[1/5] Verificando contenedores de infraestructura...${NC}"
if ! docker compose ps | grep -q "recordatorios-app-frankenphp"; then
    echo -e "${RED}Error: Los contenedores no están activos. Ejecuta 'docker compose up -d' primero.${NC}"
    exit 1
fi
echo -e "${GREEN}✔ Contenedores activos y en ejecución.${NC}"

# 2. Ejecutar Pest Tests
echo -e "\n${BOLD}[2/5] Ejecutando pruebas unitarias y de funcionalidad (Pest)...${NC}"
docker compose exec app php -d memory_limit=1G ./vendor/bin/pest
PEST_STATUS=$?

if [ $PEST_STATUS -eq 0 ]; then
    echo -e "${GREEN}✔ Pruebas de Pest completadas con éxito.${NC}"
else
    echo -e "${RED}❌ Error: Algunas pruebas en Pest fallaron.${NC}"
fi

# 3. Preparar Entorno Playwright
echo -e "\n${BOLD}[3/5] Preparando entorno de pruebas E2E (Playwright)...${NC}"
if [ ! -d "e2e/node_modules" ]; then
    echo -e "Instalando dependencias de Node.js en la carpeta E2E..."
    cd e2e && npm install && cd ..
fi
echo -e "${GREEN}✔ Entorno Playwright listo.${NC}"

# 4. Ejecutar Pruebas E2E (Playwright)
echo -e "\n${BOLD}[4/5] Ejecutando pruebas de flujo en navegador (Playwright E2E)...${NC}"
cd e2e
npx playwright test
PLAYWRIGHT_STATUS=$?
cd ..

if [ $PLAYWRIGHT_STATUS -eq 0 ]; then
    echo -e "${GREEN}✔ Pruebas E2E completadas con éxito.${NC}"
else
    echo -e "${RED}❌ Error: Algunas pruebas en Playwright E2E fallaron.${NC}"
fi

# 5. Generación de Manuales y Videos
echo -e "\n${BOLD}[5/5] Generando manuales PDF/HTML y video-tours...${NC}"
cd e2e
# 5.1 Generar capturas
npm run manual:capturas
CAPTURA_STATUS=$?

# 5.2 Compilar manuales a PDF/HTML
npm run manual:build
BUILD_STATUS=$?

# 5.3 Grabar video-tours con subtítulos
npm run manual:video
VIDEO_STATUS=$?
cd ..

# ==============================================================================
# RESUMEN DE EJECUCIÓN
# ==============================================================================
echo -e "\n${BOLD}======================================================================${NC}"
echo -e "${BOLD}                     RESUMEN FINAL DEL PIPELINE                       ${NC}"
echo -e "${BOLD}======================================================================${NC}"

if [ $PEST_STATUS -eq 0 ]; then
    echo -e "Pruebas de Backend (Pest):              ${GREEN}PASÓ (OK)${NC}"
else
    echo -e "Pruebas de Backend (Pest):              ${RED}FALLÓ (FAIL)${NC}"
fi

if [ $PLAYWRIGHT_STATUS -eq 0 ]; then
    echo -e "Pruebas E2E en Navegador (Playwright):  ${GREEN}PASÓ (OK)${NC}"
else
    echo -e "Pruebas E2E en Navegador (Playwright):  ${RED}FALLÓ (FAIL)${NC}"
fi

if [ $CAPTURA_STATUS -eq 0 ] && [ $BUILD_STATUS -eq 0 ]; then
    echo -e "Generación de Manuales (HTML/PDF):      ${GREEN}PASÓ (OK)${NC}"
else
    echo -e "Generación de Manuales (HTML/PDF):      ${RED}FALLÓ (FAIL)${NC}"
fi

if [ $VIDEO_STATUS -eq 0 ]; then
    echo -e "Generación de Video-Tours (WebM/MP4):   ${GREEN}PASÓ (OK)${NC}"
else
    echo -e "Generación de Video-Tours (WebM/MP4):   ${RED}FALLÓ (FAIL)${NC}"
fi
echo -e "${BOLD}======================================================================${NC}"

# Retornar código de salida global
if [ $PEST_STATUS -eq 0 ] && [ $PLAYWRIGHT_STATUS -eq 0 ] && [ $CAPTURA_STATUS -eq 0 ] && [ $BUILD_STATUS -eq 0 ]; then
    echo -e "${GREEN}${BOLD}¡Felicidades! Todo el ecosistema de pruebas y documentación está operativo.${NC}"
    exit 0
else
    echo -e "${RED}${BOLD}Advertencia: Se encontraron errores durante el pipeline.${NC}"
    exit 1
fi
