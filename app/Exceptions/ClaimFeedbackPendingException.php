<?php

namespace Modules\Insurance\Exceptions;

use RuntimeException;

/**
 * The payer has not decided the claim yet; feedback polling should retry later.
 */
class ClaimFeedbackPendingException extends RuntimeException {}
