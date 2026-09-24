<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Hyperf\Database\Sqlsrv;

use Hyperf\Stringable\Str;
use Throwable;

/**
 * Extends the generic lost-connection detection of hyperf/database with the
 * messages emitted by the SQL Server client stack (Microsoft ODBC Driver,
 * pdo_sqlsrv, FreeTDS/dblib).
 *
 * hyperf/database only reconnects and re-runs a statement when the driver
 * error message matches a known "connection lost" phrase. The upstream list is
 * MySQL/PostgreSQL centric, so a pooled SQL Server connection that was closed
 * by the server, a firewall, a load balancer or a failover surfaced to the
 * application as a plain QueryException instead of being refreshed.
 *
 * The class using this trait must extend a class that already provides
 * `causedByLostConnection()` via \Hyperf\Database\DetectsLostConnections.
 */
trait DetectsLostConnections
{
    /**
     * Determine if the given exception was caused by a lost connection.
     */
    protected function causedByLostConnection(Throwable $e): bool
    {
        if (parent::causedByLostConnection($e)) {
            return true;
        }

        return Str::contains($e->getMessage(), static::sqlServerLostConnectionMessages());
    }

    /**
     * Error message fragments raised by the SQL Server client stack when the
     * underlying connection is gone. Statement level errors such as
     * "Query timeout expired" or "Login failed for user" are deliberately not
     * listed: re-running those would be wrong.
     *
     * @return string[]
     */
    protected static function sqlServerLostConnectionMessages(): array
    {
        return [
            // ODBC SQLSTATE 08S01 - the driver noticed the transport is gone.
            'Communication link failure',
            'SQLSTATE[08S01]',
            // ODBC SQLSTATE 08001 / HYT00 - could not (re)establish the session.
            'Login timeout expired',
            'Client unable to establish connection',
            'A network-related or instance-specific error',
            'Could not open a connection to SQL Server',
            // Idle Connection Resiliency gave up (SQLSTATE IMC01 - IMC06).
            // https://learn.microsoft.com/sql/connect/odbc/connection-resiliency
            'The connection is broken and recovery is not possible',
            'connection recovery is not possible',
            'connection is no longer usable',
            // TCP provider errors on Windows: the socket was closed underneath us.
            'An existing connection was forcibly closed by the remote host',
            'The specified network name is no longer available',
            'The semaphore timeout period has expired',
            'No connection could be made because the target machine actively refused it',
            // TCP provider errors reported as raw error codes.
            // Linux errno: 0x20 EPIPE, 0x68 ECONNRESET (upstream), 0x6E ETIMEDOUT,
            // 0x6F ECONNREFUSED, 0x71 EHOSTUNREACH.
            'TCP Provider: Error code 0x20',
            'TCP Provider: Error code 0x6E',
            'TCP Provider: Error code 0x6F',
            'TCP Provider: Error code 0x71',
            // Winsock: 0x2745 WSAECONNABORTED, 0x2746 WSAECONNRESET,
            // 0x2749 WSAECONNREFUSED, 0x274C WSAETIMEDOUT.
            'TCP Provider: Error code 0x2745',
            'TCP Provider: Error code 0x2746',
            'TCP Provider: Error code 0x2749',
            'TCP Provider: Error code 0x274C',
            // FreeTDS / dblib.
            'Adaptive Server connection failed',
            'Unexpected EOF from the server',
            'Read from the server failed',
            'Write to the server failed',
        ];
    }
}
