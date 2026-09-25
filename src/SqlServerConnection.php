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

use Hyperf\Database\Connection;
use Hyperf\Database\Sqlsrv\Query\Grammars\SqlServerGrammar as QueryGrammar;
use Hyperf\Database\Sqlsrv\Query\Processors\SqlServerProcessor;
use Hyperf\Database\Sqlsrv\Query\SqlServerBuilder as QueryBuilder;
use Hyperf\Database\Sqlsrv\Schema\Grammars\SqlServerGrammar as SchemaGrammar;
use Hyperf\Database\Sqlsrv\Schema\SqlServerBuilder;

class SqlServerConnection extends Connection
{
    use DetectsLostConnections;

    /**
     * Get a schema builder instance for the connection.
     */
    public function getSchemaBuilder(): SqlServerBuilder
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new SqlServerBuilder($this);
    }

    /**
     * Get a new query builder instance.
     */
    public function query(): QueryBuilder
    {
        return new QueryBuilder(
            $this,
            $this->getQueryGrammar(),
            $this->getPostProcessor()
        );
    }

    /**
     * Escape a binary value for safe SQL embedding.
     *
     * @param string $value
     */
    protected function escapeBinary($value): string
    {
        $hex = bin2hex($value);

        return "0x{$hex}";
    }

    /**
     * Get the default query grammar instance.
     */
    protected function getDefaultQueryGrammar(): QueryGrammar
    {
        return $this->withTablePrefix(new QueryGrammar());
    }

    /**
     * Get the default schema grammar instance.
     */
    protected function getDefaultSchemaGrammar(): SchemaGrammar
    {
        return $this->withTablePrefix(new SchemaGrammar());
    }

    /**
     * Get the default post processor instance.
     */
    protected function getDefaultPostProcessor(): SqlServerProcessor
    {
        return new SqlServerProcessor();
    }
}
