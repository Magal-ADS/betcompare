<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CollectionSourceResult extends Model
{
    protected $fillable = [
        'collection_run_id',
        'bookmaker_id',
        'status',
        'events_count',
        'collected_at',
        'error_message',
        'http_status',
        'retry_at',
    ];

    protected function casts(): array
    {
        return [
            'collected_at' => 'datetime',
            'retry_at' => 'datetime',
        ];
    }

    public function bookmaker(): BelongsTo
    {
        return $this->belongsTo(Bookmaker::class);
    }

    public function collectionRun(): BelongsTo
    {
        return $this->belongsTo(CollectionRun::class);
    }

    public function displayErrorMessage(): ?string
    {
        if ($this->status === 'rate_limited') {
            return 'A fonte limitou temporariamente o acesso. Nova tentativa após '.$this->displayRetryAt().'.';
        }

        if ($this->status === 'deferred') {
            return 'Consulta adiada para respeitar o bloqueio da fonte. Nova tentativa após '.$this->displayRetryAt().'.';
        }

        if ($this->error_message === null) {
            return null;
        }

        if (Str::contains($this->error_message, ['status code 429', 'error code: 1015'], ignoreCase: true)) {
            return 'Limite temporário da fonte atingido. Aguarde antes de atualizar novamente.';
        }

        return 'Não foi possível consultar esta fonte nesta atualização.';
    }

    public function displayStatusSummary(): string
    {
        return match ($this->status) {
            'completed' => $this->events_count.' eventos',
            'empty' => 'sem eventos',
            'rate_limited' => 'limitada até '.$this->displayRetryAt(),
            'deferred' => 'em espera até '.$this->displayRetryAt(),
            default => 'falhou',
        };
    }

    private function displayRetryAt(): string
    {
        return $this->retry_at?->setTimezone(config('app.display_timezone'))->format('d/m/Y H:i')
            ?? 'o horário informado no histórico';
    }
}
