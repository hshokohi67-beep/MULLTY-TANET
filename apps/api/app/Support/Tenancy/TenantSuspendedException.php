<?php

namespace App\Support\Tenancy;

use RuntimeException;

/** The tenant exists but is not allowed to operate (suspended/archived). Rendered as 403. */
final class TenantSuspendedException extends RuntimeException {}
