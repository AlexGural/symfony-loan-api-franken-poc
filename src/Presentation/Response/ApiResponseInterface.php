<?php

declare(strict_types=1);

namespace App\Presentation\Response;

/**
 * Marker interface for DTOs that can be auto-serialized to JSON by ReturnResponseListener.
 * Objects not implementing this interface go through standard Symfony response flow.
 */
interface ApiResponseInterface
{
}
