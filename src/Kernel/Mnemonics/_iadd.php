<?php
namespace PHPJava\Kernel\Mnemonics;

use PHPJava\Exceptions\NotImplementedException;
use PHPJava\Kernel\Types\_Int;
use PHPJava\Utilities\BinaryTool;
use PHPJava\Utilities\Extractor;

final class _iadd implements OperationInterface
{
    use \PHPJava\Kernel\Core\Accumulator;
    use \PHPJava\Kernel\Core\ConstantPool;

    public function execute(): void
    {
        $rightValue = $this->getStack();
        $leftValue = $this->getStack();

        $this->pushStack(
            new _Int(
                BinaryTool::add(
                    Extractor::realValue($leftValue),
                    Extractor::realValue($rightValue)
                )
            )
        );
    }
}
