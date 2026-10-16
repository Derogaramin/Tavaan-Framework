<?php

declare(strict_types=1);

/*
 * Translation keys for the Report engine (DOC-072) — en
 */

return [
    'no_dataset' => 'Report ":report" has no dataset',
    'unknown_format' => 'Export format ":format" is not supported',
    'format_disabled' => 'Format ":format" is not allowed by configuration',
    'pdf_not_configured' => 'PDF export is not configured (pdf_driver in config/report.php)',
    'pdf_driver_unsupported' => 'PDF driver ":driver" is not supported',
    'path_not_allowed' => 'Path ":path" is outside the allowed project sandbox',
    'unknown_field' => 'Field ":field" is not defined in report ":report"',
    'unknown_aggregate' => 'Unknown aggregate function ":aggregate" (sum|avg|count|min|max)',
    'unknown_filter_type' => 'Unknown filter type ":type" (text|select|daterange|number|bool)',
    'database_unavailable' => 'Query/SQL dataset requires a database connection',
];
