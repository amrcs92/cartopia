<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business refusal of an admin action. The message is shown to the admin verbatim.
 */
class AdminActionRefused extends RuntimeException {}
