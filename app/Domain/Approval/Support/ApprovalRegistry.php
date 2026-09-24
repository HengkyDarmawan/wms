<?php

declare(strict_types=1);

namespace App\Domain\Approval\Support;

use App\Domain\Approval\Contracts\ApprovalHandler;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Exceptions\ApprovalRuleException;
use Illuminate\Contracts\Container\Container;

/**
 * Daftar penangan per jenis dokumen (D-28). Diisi service provider modul
 * dokumen; mesin approval hanya membaca dari sini.
 *
 *   app(ApprovalRegistry::class)->register('material_request', RequestApprovalHandler::class);
 */
class ApprovalRegistry
{
    /** @var array<string, class-string<ApprovalHandler>> */
    private array $handlers = [];

    public function __construct(private readonly Container $container) {}

    /** @param  class-string<ApprovalHandler>  $handlerClass */
    public function register(ApprovalDocumentType|string $type, string $handlerClass): void
    {
        $this->handlers[$type instanceof ApprovalDocumentType ? $type->value : $type] = $handlerClass;
    }

    public function has(ApprovalDocumentType|string $type): bool
    {
        return isset($this->handlers[$type instanceof ApprovalDocumentType ? $type->value : $type]);
    }

    public function handler(ApprovalDocumentType|string $type): ApprovalHandler
    {
        $kunci = $type instanceof ApprovalDocumentType ? $type->value : $type;

        if (! isset($this->handlers[$kunci])) {
            throw ApprovalRuleException::rule('BR-GEN-10', 'Jenis dokumen '.$kunci.' belum tersambung ke mesin approval.');
        }

        return $this->container->make($this->handlers[$kunci]);
    }

    /** @return array<int, ApprovalDocumentType> jenis dokumen yang sudah tersambung */
    public function types(): array
    {
        return array_values(array_filter(
            ApprovalDocumentType::cases(),
            fn (ApprovalDocumentType $t) => $this->has($t),
        ));
    }

    /** @return array<string, string> nilai => "REQ — Permintaan Material" */
    public function typeOptions(): array
    {
        $hasil = [];

        foreach ($this->types() as $t) {
            $hasil[$t->value] = $t->longLabel();
        }

        return $hasil;
    }

    /** @return array<int, string> permission approve semua jenis tersambung */
    public function approvePermissions(): array
    {
        return array_values(array_unique(array_map(
            fn (ApprovalDocumentType $t) => $this->handler($t)->approvePermission(),
            $this->types(),
        )));
    }
}
