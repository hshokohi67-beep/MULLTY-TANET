<?php

namespace App\Support\Tenancy;

use RuntimeException;

/** The request did not identify an existing, reachable tenant. Rendered as 404. */
final class TenantNotResolvedException extends RuntimeException {}
