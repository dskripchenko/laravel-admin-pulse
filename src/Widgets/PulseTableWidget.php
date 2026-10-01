<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Widgets;

use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Dskripchenko\LaravelAdmin\Widget\TableWidget;
use Dskripchenko\LaravelAdminPulse\Support\PulseAccess;

/**
 * A table widget over grouped telemetry rows.
 *
 * The core's TableWidget lists the records of a model; these tables list
 * groups — a route, a query fingerprint, an exception — which no model row
 * holds. So the rows come from the telemetry service, and only the columns
 * and the payload shape are the core's.
 */
abstract class PulseTableWidget extends TableWidget
{
    public function __construct()
    {
        // Full width: fingerprints and exception messages are long, and in
        // half a row they squeeze the numbers out of view.
        $this->size(12)
            ->rowSpan(3)
            ->permission(PulseAccess::PERMISSION)
            ->columns($this->tableColumns());
    }

    /**
     * @return list<TableColumn>
     */
    abstract protected function tableColumns(): array;

    /**
     * @return list<array<string, mixed>>
     */
    abstract protected function rows(): array;

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        // The model-less TableWidget payload: no rows, the serialized columns.
        $payload = parent::data();
        $payload['rows'] = PulseAccess::allowed() ? $this->rows() : [];
        $payload['emptyText'] = __('Нет данных за период');

        return $payload;
    }
}
