<?php

declare(strict_types=1);

namespace App\Tools\PHPStan;

use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * PHPStan rule: Logger::error/critical/alert/emergency requieren $context array.
 *
 * Estos niveles describen condiciones anómalas. Sin contexto estructurado,
 * son imposibles de diagnosticar. Esta rule obliga a siempre pasar $context.
 *
 * @implements Rule<StaticCall>
 */
final class LoggerContextRule implements Rule
{
    private const array REQUIRED_CONTEXT_METHODS = ['error', 'critical', 'alert', 'emergency'];

    #[Override]
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /**
     * @param StaticCall $node
     * @return list<\PHPStan\Rules\RuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->class instanceof Node\Name) {
            return [];
        }

        $className = $node->class->toString();

        if ($className !== 'App\\Core\\Logger' && $className !== 'Logger') {
            return [];
        }

        if (!$node->name instanceof Node\Identifier) {
            return [];
        }

        $methodName = $node->name->toString();

        if (!\in_array($methodName, self::REQUIRED_CONTEXT_METHODS, true)) {
            return [];
        }

        // Verificar que se pasa al menos 2 argumentos (message + context)
        if (\count($node->args) >= 2) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                \sprintf(
                    'Logger::%s() requires a $context array as second argument. ' .
                    'Error-level logs without context are undiagnosable.',
                    $methodName
                )
            )->build(),
        ];
    }
}
