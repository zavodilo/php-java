<?php
namespace PHPJava\Kernel\Mnemonics;

use PHPJava\Exceptions\NotImplementedException;
use PHPJava\Kernel\Types\_Long;
use PHPJava\Utilities\BinaryTool;
use PHPJava\Utilities\Extractor;

final class _ladd implements OperationInterface
{
    use \PHPJava\Kernel\Core\Accumulator;
    use \PHPJava\Kernel\Core\ConstantPool;

    public function execute(): void
    {
        $value2 = $this->getStack();
        $value1 = $this->getStack();

        $this->pushStack(
            new _Long(
                BinaryTool::add(
                    Extractor::realValue($value1),
                    Extractor::realValue($value2)
                )
            )
        );
    }
}
