<?php

declare(strict_types=1);

namespace PHPeste\Payments\Gateway;

use RuntimeException;

class InvalidWebhookSignature extends RuntimeException {}
