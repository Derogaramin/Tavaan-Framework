<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade گردش‌کار (Workflow — DOC-071).
 *
 * @method static \Tavaan\Engine\Workflow\ProcessDefinition define(string $name, \Closure $builder)
 * @method static \Tavaan\Engine\Workflow\ProcessDefinition defineArray(array $data)
 * @method static \Tavaan\Engine\Workflow\ProcessDefinition defineJson(string $json)
 * @method static \Tavaan\Engine\Workflow\ProcessDefinition definition(string $name)
 * @method static list<array<string, mixed>> definitions()
 * @method static \Tavaan\Engine\Workflow\ProcessInstance start(string $name, ?string $subjectType = null, ?string $subjectId = null, array $variables = [])
 * @method static \Tavaan\Engine\Workflow\ProcessInstance instance(string $id)
 * @method static list<\Tavaan\Engine\Workflow\Task> tasksFor(string|int $assigneeId, ?string $status = 'pending')
 * @method static list<string> userRoles(string|int $userId)
 * @method static \Tavaan\Engine\Workflow\Task task(string $taskId)
 * @method static \Tavaan\Engine\Workflow\Store\StoreInterface store()
 * @method static \Tavaan\Engine\Workflow\TimerProcessor timerProcessor()
 * @method static bool userHasRole(string|int $userId, string $role)
 * @method static array<string, mixed> maskVariables(array $variables)
 * @mixin \Tavaan\Engine\Workflow\WorkflowManager
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class Workflow extends Facade
{
    protected static function accessor(): string
    {
        return 'workflow';
    }
}
