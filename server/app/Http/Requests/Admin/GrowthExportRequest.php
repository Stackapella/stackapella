<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Actions\Growth\GrowthCsv;
use App\Support\Stats\FairCompare;
use Illuminate\Foundation\Http\FormRequest;

/** admin.growth.export: which page, and that page's own period, as the page itself asks for it. */
final class GrowthExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $page = $this->query('page');

        return [
            'page' => ['required', 'in:'.implode(',', GrowthCsv::PAGES)],
            'period' => ['sometimes', 'nullable', 'in:'.implode(',', $page === 'choirs' ? ['7d', '30d'] : FairCompare::PERIODS)],
            'days' => ['sometimes', 'nullable', 'integer', 'in:'.($page === 'sources' ? '1,7,30,90' : '7,30,90')],
            'experiment' => ['sometimes', 'nullable', 'string', 'max:40'],
        ];
    }
}
