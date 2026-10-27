<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\EyeExam
 */
class EyeExamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'od' => $this->eye('od'),
            'oe' => $this->eye('oe'),
            'diagnosis' => $this->diagnosis,
            'observations' => $this->observations,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
