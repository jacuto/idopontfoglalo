<?php
namespace App\Services;

/** Request/job-local context. Never infer private access from a public selector. */
final class Tenant
{
    public int $id = 1;
    public function run(int $id, callable $callback): mixed
    {
        $previous = $this->id;
        $this->id = $id;
        try { return $callback(); } finally { $this->id = $previous; }
    }
}
