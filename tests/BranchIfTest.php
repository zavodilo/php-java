<?php
namespace PHPJava\Tests;

use PHPUnit\Framework\TestCase;

class BranchIfTest extends Base
{
    protected $fixtures = [
        'BranchIfTest',
    ];

    private function call($method, $value1, $value2)
    {
        $calculatedValue = $this->initiatedJavaClasses['BranchIfTest']
            ->getInvoker()
            ->getStatic()
            ->getMethods()
            ->call($method, $value1, $value2);

        return $calculatedValue->getValue();
    }

    public function testIfAcmpEq()
    {
        $actual = $this->call('ifAcmpEq', 'value1', 'value2');
        $this->assertEquals(0, $actual);
    }

    public function testIfAcmpNe()
    {
        $actual = $this->call('ifAcmpNe', 'value1', 'value2');
        $this->assertEquals(1, $actual);
    }

    // public function testIfIcmpEq()
    // {
    //     $actual = $this->call('ifIcmpEq', 5, 3);
    //     $this->assertEquals(0, $actual);
    // }

    public function testIfIcmpNe()
    {
        $actual = $this->call('ifIcmpNe', 5, 3);
        $this->assertEquals(1, $actual);
    }

    public function testIfIcmpLt()
    {
        $actual = $this->call('ifIcmpLt', 5, 3);
        $this->assertEquals(0, $actual);
    }

    public function testIfIcmpGe()
    {
        $actual = $this->call('ifIcmpGe', 5, 3);
        $this->assertEquals(1, $actual);
    }

    public function testIfIcmpGt()
    {
        $actual = $this->call('ifIcmpGt', 5, 3);
        $this->assertEquals(1, $actual);
    }

    // public function testIfIcmpLe()
    // {
    //     $actual = $this->call('ifIcmpLe', 5, 3);
    //     $this->assertEquals(0, $actual);
    // }
}
