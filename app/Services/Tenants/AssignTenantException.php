<?php

namespace App\Services\Tenants;

use RuntimeException;

/**
 * An expected, user-facing failure inside the assign-tenant transaction
 * (room taken, tenant already housed, storage write failed…). The message is
 * already translated — controllers flash it verbatim and bounce the form back
 * with its input intact. Anything else escaping the service is a genuine bug
 * and gets the generic "nothing was saved" flash instead.
 */
class AssignTenantException extends RuntimeException {}
