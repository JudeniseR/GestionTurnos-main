#!/bin/bash
PHP_BIN="/opt/bitnami/php/bin/php"
SCRIPT_PHP="/opt/bitnami/apache/htdocs/Logica/Paciente/Gestion-Turnos/recordatorioTurnos.php"
LOG_DIR="/opt/bitnami/scripts/logs"
LOG_FILE="${LOG_DIR}/recordatorio_turnos_$(date '+%Y%m%d').log"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Iniciando proceso de recordatorios..."

[ ! -f "$SCRIPT_PHP" ] && echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: No se encontró $SCRIPT_PHP" && exit 1
[ ! -d "$LOG_DIR" ] && mkdir -p "$LOG_DIR"

$PHP_BIN -f "$SCRIPT_PHP" >> "$LOG_FILE" 2>&1
if [ $? -eq 0 ]; then
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] Proceso completado correctamente."
else
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR durante la ejecución. Revisar log en $LOG_FILE"
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Log guardado en: $LOG_FILE"
echo "-----------------------------------------------------------------------------"

