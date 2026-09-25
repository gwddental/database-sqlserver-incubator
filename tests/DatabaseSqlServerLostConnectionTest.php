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

namespace HyperfTest\Database\Sqlsrv;

use Closure;
use Hyperf\Database\Sqlsrv\Connectors\SqlServerConnector;
use Hyperf\Database\Sqlsrv\SqlServerConnection;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 * @coversNothing
 */
class DatabaseSqlServerLostConnectionTest extends TestCase
{
    /**
     * @dataProvider provideLostConnectionMessageCases
     */
    public function testConnectionDetectsSqlServerLostConnection(string $message)
    {
        $this->assertTrue($this->causedByLostConnection($this->connection(), $message));
    }

    /**
     * @dataProvider provideLostConnectionMessageCases
     */
    public function testConnectorDetectsSqlServerLostConnection(string $message)
    {
        $this->assertTrue($this->causedByLostConnection(new SqlServerConnector(), $message));
    }

    /**
     * @dataProvider provideStatementErrorCases
     */
    public function testStatementErrorsAreNotTreatedAsLostConnection(string $message)
    {
        $this->assertFalse($this->causedByLostConnection($this->connection(), $message));
        $this->assertFalse($this->causedByLostConnection(new SqlServerConnector(), $message));
    }

    public function testUpstreamMessagesStillDetected()
    {
        $message = 'SQLSTATE[HY000]: General error: 2006 MySQL server has gone away';

        $this->assertTrue($this->causedByLostConnection($this->connection(), $message));
    }

    public static function provideLostConnectionMessageCases(): iterable
    {
        return [
            'odbc 08S01 linux reset' => ['SQLSTATE[08S01]: Communication link failure: 0 [Microsoft][ODBC Driver 18 for SQL Server]TCP Provider: Error code 0x68'],
            'odbc 08S01 linux broken pipe' => ['SQLSTATE[08S01]: Communication link failure: 0 [Microsoft][ODBC Driver 18 for SQL Server]TCP Provider: Error code 0x20'],
            'odbc 08S01 windows reset' => ['SQLSTATE[08S01]: Communication link failure: 10054 [Microsoft][ODBC Driver 17 for SQL Server]TCP Provider: An existing connection was forcibly closed by the remote host.'],
            'odbc 08S01 bare' => ['SQLSTATE[08S01]: [Microsoft][ODBC Driver 17 for SQL Server]Communication link failure'],
            'odbc smux' => ['SQLSTATE[08S01]: [Microsoft][ODBC Driver 17 for SQL Server]SMux Provider: Physical connection is not usable [xFFFFFFFF].'],
            'odbc login timeout' => ['SQLSTATE[HYT00]: [Microsoft][ODBC Driver 18 for SQL Server]Login timeout expired'],
            'odbc unable to connect' => ['SQLSTATE[08001]: [Microsoft][ODBC Driver 18 for SQL Server]Client unable to establish connection'],
            'odbc resiliency IMC01' => ['SQLSTATE[IMC01]: [Microsoft][ODBC Driver 17 for SQL Server]The connection is broken and recovery is not possible. The client driver attempted to recover the connection one or more times and all attempts failed. Increase the value of ConnectRetryCount to increase the number of recovery attempts.'],
            'odbc resiliency IMC02' => ['SQLSTATE[IMC02]: [Microsoft][ODBC Driver 17 for SQL Server]The server did not acknowledge a recovery attempt, connection recovery is not possible.'],
            'odbc resiliency IMC06' => ['SQLSTATE[IMC06]: [Microsoft][ODBC Driver 17 for SQL Server]The connection is broken and recovery is not possible. The connection is marked by the client driver as unrecoverable. No attempt was made to restore the connection.'],
            'odbc semaphore timeout' => ['SQLSTATE[08S01]: [Microsoft][ODBC Driver 17 for SQL Server]TCP Provider: The semaphore timeout period has expired.'],
            'odbc winsock timeout code' => ['SQLSTATE[08001]: [Microsoft][ODBC Driver 17 for SQL Server]TCP Provider: Error code 0x274C'],
            'sqlsrv network error' => ['SQLSTATE[08001]: [Microsoft][ODBC Driver 17 for SQL Server]A network-related or instance-specific error has occurred while establishing a connection to SQL Server.'],
            'dblib eof' => ['SQLSTATE[HY000]: General error: 20017 Unexpected EOF from the server [20017] (severity 9) [(null)]'],
            'dblib connect' => ['SQLSTATE[HY000] [2002] Adaptive Server connection failed (severity 9)'],
        ];
    }

    public static function provideStatementErrorCases(): iterable
    {
        return [
            'query timeout' => ['SQLSTATE[HYT00]: [Microsoft][ODBC Driver 17 for SQL Server]Query timeout expired'],
            'login failed' => ['SQLSTATE[28000]: [Microsoft][ODBC Driver 17 for SQL Server][SQL Server]Login failed for user \'sa\'.'],
            'deadlock' => ['SQLSTATE[40001]: [Microsoft][ODBC Driver 17 for SQL Server][SQL Server]Transaction (Process ID 52) was deadlocked on lock resources with another process and has been chosen as the deadlock victim. Rerun the transaction.'],
            'syntax' => ['SQLSTATE[42000]: [Microsoft][ODBC Driver 17 for SQL Server][SQL Server]Incorrect syntax near \'FROM\'.'],
            'invalid object' => ['SQLSTATE[42S02]: [Microsoft][ODBC Driver 17 for SQL Server][SQL Server]Invalid object name \'users\'.'],
        ];
    }

    protected function connection(): SqlServerConnection
    {
        return new SqlServerConnection(function () {
            throw new RuntimeException('The PDO resolver must not be invoked by this test.');
        }, 'test', '', ['driver' => 'sqlsrv', 'name' => 'sqlsrv']);
    }

    protected function causedByLostConnection(object $target, string $message): bool
    {
        $probe = function (PDOException $e) {
            return $this->causedByLostConnection($e);
        };

        return Closure::bind($probe, $target, $target::class)(new PDOException($message));
    }
}
