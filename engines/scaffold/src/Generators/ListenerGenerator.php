<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold\Generators;

use Tavaan\Engine\Scaffold\GenerateCommand;
use Tavaan\Engine\Scaffold\Generator;
use Tavaan\Support\Str;

/**
 * تَوان | Tavaan — Scaffold
 * ListenerGenerator — `make:listener SendInvoiceOnOrderPaid [--event=OrderWasPaid]`.
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class ListenerGenerator extends Generator
{
    public function generate(GenerateCommand $cmd): array
    {
        $class = $this->validateClassName($cmd->nameArgument());
        if (!\str_ends_with($class, 'Listener')) {
            $class .= 'Listener';
        }

        $event = $cmd->opt('event');
        $event = \is_string($event) && $event !== '' ? $this->validateClassName($event) : '';

        $context = $this->context($cmd);
        $eventName = $event === '' ? '' : Str::snake($event, '.');

        return [
            $this->target($context['src'] . '/Listeners/' . $class . '.php') => $this->render('listener.stub', [
                'namespace' => $context['namespace'] . '\\Listeners',
                'class' => $class,
                'module' => $context['module'] ?? '',
                'event' => $event === '' ? '(نام رویداد)' : $event,
                'eventName' => $eventName,
            ]),
        ];
    }
}
