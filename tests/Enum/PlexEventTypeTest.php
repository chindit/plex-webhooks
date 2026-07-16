<?php

namespace App\Tests\Enum;

use App\Enum\PlexEventType;
use PHPUnit\Framework\TestCase;

class PlexEventTypeTest extends TestCase
{
    public function testLibraryNewValue(): void
    {
        $this->assertSame('library.new', PlexEventType::LibraryNew->value);
    }

    public function testUnknownEventDoesNotResolve(): void
    {
        $this->assertNull(PlexEventType::tryFrom('not.a.real.event'));
    }
}
