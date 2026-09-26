#!/bin/bash
# Pruebas de integración: requieren un servidor PHP corriendo (php -S) con
# la base de datos ya migrada. Pensado para CI, pero también funciona local.
set -uo pipefail
API_BASE="${API_BASE:-http://127.0.0.1:8080/api}"
FAILURES=0

pass() { echo "  ✓ $1"; }
fail() { echo "  ✗ $1"; FAILURES=$((FAILURES + 1)); }

json_get() {
    php -r '$d = json_decode($argv[1], true); echo $d[$argv[2]] ?? "";' "$1" "$2"
}

echo "== disponibilidad inicial =="
RESP=$(curl -s "${API_BASE}/availability.php?property_id=casa-quintana-roo&start=2028-01-01&end=2028-01-31")
if echo "$RESP" | grep -q '"rooms"'; then pass "availability.php responde con 'rooms'"; else fail "sin 'rooms' (resp: $RESP)"; fi

echo ""
echo "== crear reserva directa de 3 noches =="
RESP=$(curl -s -X POST "${API_BASE}/create_booking.php" -H "Content-Type: application/json" \
  -d '{"room_id":"qroo-cuarto-1","check_in":"2028-01-10","check_out":"2028-01-13","guest_name":"CI Test Guest","guest_phone":"5550000000"}')
SUCCESS=$(json_get "$RESP" "success")
NIGHTS=$(json_get "$RESP" "nights")
if [ "$SUCCESS" = "1" ] && [ "$NIGHTS" = "3" ]; then pass "reserva creada con 3 noches"; else fail "no se creó como se esperaba (resp: $RESP)"; fi

echo ""
echo "== las fechas quedan bloqueadas de inmediato (aunque status=pendiente) =="
RESP=$(curl -s "${API_BASE}/availability.php?room_id=qroo-cuarto-1&start=2028-01-01&end=2028-01-31")
if echo "$RESP" | grep -q '"2028-01-10"'; then pass "las noches quedaron bloqueadas al crear"; else fail "no se bloquearon (resp: $RESP)"; fi

echo ""
echo "== traslape debe RECHAZARSE de inmediato (409) =="
HTTP_CODE=$(curl -s -o /tmp/resp_conflict.json -w "%{http_code}" -X POST "${API_BASE}/create_booking.php" -H "Content-Type: application/json" \
  -d '{"room_id":"qroo-cuarto-1","check_in":"2028-01-12","check_out":"2028-01-15","guest_name":"CI Conflict","guest_phone":"5550000001"}')
if [ "$HTTP_CODE" = "409" ]; then pass "traslape rechazado con HTTP 409"; else fail "se esperaba 409, se obtuvo $HTTP_CODE"; fi

echo ""
echo "== reserva consecutiva (mismo día salida/entrada) debe ACEPTARSE =="
HTTP_CODE=$(curl -s -o /tmp/resp_consec.json -w "%{http_code}" -X POST "${API_BASE}/create_booking.php" -H "Content-Type: application/json" \
  -d '{"room_id":"qroo-cuarto-1","check_in":"2028-01-13","check_out":"2028-01-15","guest_name":"CI Consecutive","guest_phone":"5550000002"}')
if [ "$HTTP_CODE" = "201" ]; then pass "reserva consecutiva aceptada (201)"; else fail "se esperaba 201, se obtuvo $HTTP_CODE"; fi

echo ""
echo "== exportación iCal genera un VEVENT válido =="
RESP=$(curl -s "${API_BASE}/ical_export.php?room_id=qroo-cuarto-1")
if echo "$RESP" | grep -q "BEGIN:VEVENT" && echo "$RESP" | grep -q "DTSTART;VALUE=DATE:20280110"; then
  pass "VEVENT con la fecha correcta"
else
  fail "VEVENT no generado como se esperaba (resp: $RESP)"
fi

echo ""
echo "== cuarto inexistente devuelve 404 =="
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "${API_BASE}/create_booking.php" -H "Content-Type: application/json" \
  -d '{"room_id":"cuarto-que-no-existe","check_in":"2028-02-01","check_out":"2028-02-03","guest_name":"CI Ghost"}')
if [ "$HTTP_CODE" = "404" ]; then pass "room_id inexistente devuelve 404"; else fail "se esperaba 404, se obtuvo $HTTP_CODE"; fi

echo ""
echo "======================================"
if [ "$FAILURES" -eq 0 ]; then
  echo "TODAS las pruebas de integración pasaron."
  exit 0
else
  echo "$FAILURES prueba(s) de integración FALLARON."
  exit 1
fi
