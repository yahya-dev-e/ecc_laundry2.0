<?php

namespace App\Models;

/**
 * Booking model acting as an alias to Reservation for backwards compatibility.
 * Strictly uses the 'reservations' table and its exact schema.
 */
class Booking extends Reservation
{
}
