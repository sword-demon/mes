<?php

namespace App\Modules\Process\FormRequest;

use App\Modules\Process\Model\Process;

class UpdateProcessRequest extends ProcessRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Process $process */
        $process = $this->route('process');

        return $this->processRules($process);
    }
}
