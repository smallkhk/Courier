<?php

namespace App\Services\Updates;

use RuntimeException;

/** An update step failed; the message is safe to show to an administrator. */
class UpdateException extends RuntimeException {}
