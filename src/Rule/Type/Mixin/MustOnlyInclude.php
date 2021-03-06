<?php

declare(strict_types=1);

namespace PhpAT\Rule\Type\Mixin;

use PHPAT\EventDispatcher\EventDispatcher;
use PhpAT\Parser\AstNode;
use PhpAT\Rule\Type\RuleType;
use PhpAT\Statement\Event\StatementNotValidEvent;
use PhpAT\Statement\Event\StatementValidEvent;

class MustOnlyInclude implements RuleType
{
    private $eventDispatcher;

    public function __construct(
        EventDispatcher $eventDispatcher
    ) {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function validate(
        string $fqcnOrigin,
        array $fqcnDestinations,
        array $astMap,
        bool $inverse = false //ignored
    ): void {
        /** @var AstNode $node */
        foreach ($astMap as $node) {
            if ($node->getClassName() !== $fqcnOrigin) {
                continue;
            }

            $mixins = $node->getMixins();
            foreach ($fqcnDestinations as $fqcnDestination) {
                $result = array_search($fqcnDestination, $mixins, true);

                if ($result === false) {
                    $this->dispatchSelectedResult(false, $fqcnOrigin, $fqcnDestination);
                    continue;
                }
                $this->dispatchSelectedResult(true, $fqcnOrigin, $fqcnDestination);
                unset($mixins[$result]);
            }

            if (empty($mixins)) {
                $this->dispatchOthersResult(true, $fqcnOrigin);

                return;
            }

            foreach ($mixins as $mixin) {
                $this->dispatchOthersResult(false, $fqcnOrigin, $mixin);
            }
        }

        return;
    }

    private function dispatchSelectedResult(bool $result, string $fqcnOrigin, string $fqcnDestination): void
    {
        $action = $result ? ' includes ' : ' does not include ';
        $event = $result ? StatementValidEvent::class : StatementNotValidEvent::class;
        $message = $fqcnOrigin . $action . $fqcnDestination;

        $this->eventDispatcher->dispatch(new $event($message));
    }

    private function dispatchOthersResult(bool $result, string $fqcnOrigin, string $fqcnDestination = ''): void
    {
        $message = $result
            ? $fqcnOrigin . ' does not include non-selected traits'
            : $fqcnOrigin . ' includes ' . $fqcnDestination;
        $event = $result ? StatementValidEvent::class : StatementNotValidEvent::class;

        $this->eventDispatcher->dispatch(new $event($message));
    }
}
