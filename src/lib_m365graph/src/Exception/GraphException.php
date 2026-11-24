<?php

/**
 * @package     M365Graph
 * @subpackage  lib_m365graph
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace M365Graph\Exception;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Error returned by Microsoft Graph or the Entra token endpoint.
 *
 * Only the HTTP status code, the request-id and the service error code are
 * kept; response bodies and tokens are never part of the exception.
 *
 * @since  0.1.0
 */
class GraphException extends \RuntimeException
{
    /**
     * Constructor.
     *
     * @param   string           $message     Technical message (English, for logs).
     * @param   integer          $statusCode  HTTP status code (0 for transport errors).
     * @param   string           $requestId   The request-id response header, if any.
     * @param   string           $errorCode   Service error code, e.g. ErrorAccessDenied.
     * @param   \Throwable|null  $previous    Previous exception.
     *
     * @since   0.1.0
     */
    public function __construct(
        string $message,
        private readonly int $statusCode = 0,
        private readonly string $requestId = '',
        private readonly string $errorCode = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    /**
     * HTTP status code.
     *
     * @return  integer
     *
     * @since   0.1.0
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Value of the request-id header.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public function getRequestId(): string
    {
        return $this->requestId;
    }

    /**
     * Service error code.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
