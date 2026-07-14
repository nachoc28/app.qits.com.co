<?php

namespace App\Services\AiFlows;

use App\Models\AiFlowVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AiFlowVersionService
{
    /** @var AiFlowVersionValidationService */
    private $validationService;

    public function __construct(AiFlowVersionValidationService $validationService)
    {
        $this->validationService = $validationService;
    }

    public function publish(AiFlowVersion $version, User $user): AiFlowVersion
    {
        $validation = $this->validationService->validateForPublication($version);

        if (! $validation['can_publish']) {
            throw new InvalidArgumentException(implode(' ', $validation['errors']));
        }

        return DB::transaction(function () use ($version, $user): AiFlowVersion {
            $version = AiFlowVersion::query()
                ->whereKey($version->id)
                ->lockForUpdate()
                ->firstOrFail();

            AiFlowVersion::query()
                ->where('ai_flow_id', $version->ai_flow_id)
                ->where('id', '<>', $version->id)
                ->where('status', AiFlowVersion::STATUS_PUBLISHED)
                ->update([
                    'status' => AiFlowVersion::STATUS_ARCHIVED,
                    'updated_at' => now(),
                ]);

            $version->forceFill([
                'status' => AiFlowVersion::STATUS_PUBLISHED,
                'published_at' => now(),
                'published_by' => $user->id,
            ])->save();

            return $version->fresh();
        });
    }

    public function ensureVersionCanBeEdited(AiFlowVersion $version): void
    {
        if ($version->status === AiFlowVersion::STATUS_PUBLISHED && $version->executions()->exists()) {
            throw new InvalidArgumentException('No se puede editar una versión publicada con ejecuciones históricas.');
        }
    }
}
