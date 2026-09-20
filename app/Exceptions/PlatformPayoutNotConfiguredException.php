<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Subscription checkout cannot mint a QR because nobody has said where the
 * money should go.
 *
 * Distinct from every other checkout failure on purpose. A timeout or a refused
 * token is transient and the customer should try again; THIS is a configuration
 * hole that will still be there in five minutes, and the person who can close
 * it is the platform operator, not the person holding the phone. Both entry
 * points catch it separately so the message reaches the screen intact instead
 * of being flattened into "payment could not be started".
 *
 * Renamed from KhqrPlatformCredentialsMissingException in 2026-09: with khqr.cc
 * gone there are no platform credentials left to be missing. What can be
 * missing is the Bakong account id — the payout destination printed inside
 * every QR, which is identity rather than a secret.
 */
class PlatformPayoutNotConfiguredException extends RuntimeException {}
