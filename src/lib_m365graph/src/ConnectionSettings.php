<?php

/**
 * @package     M365Graph
 * @subpackage  lib_m365graph
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace M365Graph;

use M365Graph\Exception\ConfigurationException;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Immutable connection settings passed in by the caller.
 *
 * The library never reads configuration on its own: callers build this
 * value object from their own (validated) settings.
 *
 * @since  1.0.0
 */
final class ConnectionSettings
{
    /**
     * Regular expression for a GUID.
     *
     * @var    string
     * @since  1.0.0
     */
    public const GUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * Regular expression for a tenant domain such as contoso.onmicrosoft.com.
     *
     * @var    string
     * @since  1.0.0
     */
    public const DOMAIN_PATTERN = '/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i';

    /**
     * Constructor.
     *
     * @param   string  $tenantId         Directory (tenant) ID or verified domain.
     * @param   string  $clientId         Application (client) ID.
     * @param   string  $certificatePath  Absolute path of the public certificate (PEM or DER).
     * @param   string  $privateKeyPath   Absolute path of the PEM private key.
     *
     * @since   1.0.0
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $clientId,
        public readonly string $certificatePath,
        public readonly string $privateKeyPath
    ) {
    }

    /**
     * Whether all values are filled in.
     *
     * @return  boolean
     *
     * @since   1.0.0
     */
    public function isComplete(): bool
    {
        return $this->tenantId !== ''
            && $this->clientId !== ''
            && $this->certificatePath !== ''
            && $this->privateKeyPath !== '';
    }

    /**
     * Validate the settings.
     *
     * @param   string  $webRoot  Absolute web root (JPATH_ROOT); key files must be outside of it.
     *
     * @return  void
     *
     * @throws  ConfigurationException
     *
     * @since   1.0.0
     */
    public function validate(string $webRoot): void
    {
        if (!$this->isComplete()) {
            throw new ConfigurationException('Connection settings are incomplete.', 'incomplete');
        }

        if (!self::isValidTenantId($this->tenantId)) {
            throw new ConfigurationException('Tenant ID is invalid.', 'tenant');
        }

        if (!preg_match(self::GUID_PATTERN, $this->clientId)) {
            throw new ConfigurationException('Client ID is invalid.', 'client');
        }

        self::assertSafeFile($this->certificatePath, $webRoot);
        self::assertSafeFile($this->privateKeyPath, $webRoot);
    }

    /**
     * Check a tenant identifier (GUID or verified domain).
     *
     * @param   string  $tenantId  The value.
     *
     * @return  boolean
     *
     * @since   1.0.0
     */
    public static function isValidTenantId(string $tenantId): bool
    {
        return (bool) preg_match(self::GUID_PATTERN, $tenantId) || (bool) preg_match(self::DOMAIN_PATTERN, $tenantId);
    }

    /**
     * Ensure a key/certificate file exists, is readable and is not inside the web root.
     *
     * @param   string  $path     The file path.
     * @param   string  $webRoot  The web root.
     *
     * @return  void
     *
     * @throws  ConfigurationException
     *
     * @since   1.0.0
     */
    public static function assertSafeFile(string $path, string $webRoot): void
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new ConfigurationException('File path is empty or invalid.', 'path_invalid');
        }

        $real = realpath($path);

        if ($real === false || !is_file($real)) {
            throw new ConfigurationException('File does not exist.', 'path_missing');
        }

        if (!is_readable($real)) {
            throw new ConfigurationException('File is not readable.', 'path_unreadable');
        }

        if (self::isInside($real, $webRoot)) {
            throw new ConfigurationException('File is inside the web root.', 'path_in_webroot');
        }
    }

    /**
     * Whether a resolved path lies inside a directory.
     *
     * @param   string  $realPath   Resolved file path.
     * @param   string  $directory  Directory.
     *
     * @return  boolean
     *
     * @since   1.0.0
     */
    public static function isInside(string $realPath, string $directory): bool
    {
        $root = realpath($directory);

        if ($root === false) {
            return false;
        }

        $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $file = str_replace('\\', '/', $realPath);

        if (DIRECTORY_SEPARATOR === '\\') {
            $root = strtolower($root);
            $file = strtolower($file);
        }

        return str_starts_with($file, $root);
    }
}
