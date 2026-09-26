-- Migración: agrega el estado 'pendiente' a reservations.status
-- Corre esto UNA VEZ en phpMyAdmin sobre tu base de datos existente
-- (local y luego, cuando despliegues, también en la de producción).
-- No borra ni modifica ninguna reserva existente -- todas las que ya
-- tenías (que están en 'confirmada' o 'cancelada') se quedan exactamente igual.

ALTER TABLE reservations
  MODIFY COLUMN status ENUM('pendiente','confirmada','cancelada') NOT NULL DEFAULT 'pendiente';
