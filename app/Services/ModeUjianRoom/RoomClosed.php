<?php

namespace App\Services\ModeUjianRoom;

use RuntimeException;

/** The attempt no longer takes answers (submitted, or past deadline + grace). */
final class RoomClosed extends RuntimeException {}
