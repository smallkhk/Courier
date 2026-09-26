<?php

namespace App\Services\Notifications;

/** A delivery failure that will not succeed on retry (e.g. invalid recipient, bad credentials). */
class PermanentDeliveryFailure extends \RuntimeException {}
