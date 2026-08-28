<?php

namespace Tests\App\Helpers;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../app/Helpers/SessionHelper.php';

use App\Helpers\SessionHelper;

class SessionHelperTest extends TestCase
{
    public function testIsPauloNascimentoExactMatch()
    {
        $this->assertTrue(SessionHelper::isPauloNascimento('Paulo Nascimento'));
    }

    public function testIsPauloNascimentoCaseInsensitive()
    {
        $this->assertTrue(SessionHelper::isPauloNascimento('paulo nascimento'));
        $this->assertTrue(SessionHelper::isPauloNascimento('PAULO NASCIMENTO'));
        $this->assertTrue(SessionHelper::isPauloNascimento(' Paulo Nascimento '));
    }

    public function testIsPauloNascimentoOtherUser()
    {
        $this->assertFalse(SessionHelper::isPauloNascimento('João Silva'));
        $this->assertFalse(SessionHelper::isPauloNascimento('Maria Santos'));
        $this->assertFalse(SessionHelper::isPauloNascimento('Paulo'));
        $this->assertFalse(SessionHelper::isPauloNascimento(''));
        $this->assertFalse(SessionHelper::isPauloNascimento(null));
    }
}
