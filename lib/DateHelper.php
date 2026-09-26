<?php
/**
 * Genera la lista de noches entre check_in y check_out.
 * check_in es inclusive, check_out es exclusivo (la noche de salida no se cuenta).
 */
function nights_between(string $checkIn, string $checkOut): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkIn) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkOut)) {
        throw new InvalidArgumentException('Formato de fecha inválido, usa YYYY-MM-DD');
    }

    $in = new DateTime($checkIn);
    $out = new DateTime($checkOut);

    if ($out <= $in) {
        throw new InvalidArgumentException('check_out debe ser posterior a check_in');
    }

    $nights = [];
    $cursor = clone $in;
    while ($cursor < $out) {
        $nights[] = $cursor->format('Y-m-d');
        $cursor->modify('+1 day');
    }
    return $nights;
}
