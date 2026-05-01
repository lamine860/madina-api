<?php

declare(strict_types=1);

namespace Modules\Payments\Exceptions;

use RuntimeException;

final class InvalidLengoPayWebhookSignatureException extends RuntimeException {}
