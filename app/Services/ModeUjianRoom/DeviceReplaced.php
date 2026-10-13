<?php

namespace App\Services\ModeUjianRoom;

use RuntimeException;

/** The room was opened again on another tab or device; this one may no longer save. */
final class DeviceReplaced extends RuntimeException {}
