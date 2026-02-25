<?php

declare(strict_types=1);

namespace Obs\Tests;

use Obs\Client;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    protected function setUp(): void
    {
        Client::reset();
    }

    public function testParseDSNValid(): void
    {
        [$endpoint, $key] = Client::parseDSN('http://mykey@localhost:8000');
        $this->assertSame('http://localhost:8000', $endpoint);
        $this->assertSame('mykey', $key);
    }

    public function testParseDSNHttps(): void
    {
        [$endpoint, $key] = Client::parseDSN('https://secret@example.com');
        $this->assertSame('https://example.com', $endpoint);
        $this->assertSame('secret', $key);
    }

    public function testParseDSNMissingKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Client::parseDSN('http://localhost:8000');
    }

    public function testParseDSNEmptyKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Client::parseDSN('http://@localhost:8000');
    }

    public function testParseDSNInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Client::parseDSN('not a url');
    }

    public function testInitCreatesInstance(): void
    {
        Client::init([
            'dsn' => 'http://key@localhost:9999',
            'environment' => 'test',
            'server_name' => 'test-host',
        ]);

        $this->assertNotNull(Client::getInstance());
    }

    public function testCaptureMessageReturnsId(): void
    {
        Client::init(['dsn' => 'http://key@localhost:9999']);

        $id = Client::captureMessage('hello world');
        $this->assertNotEmpty($id);
        $this->assertSame(36, strlen($id));
    }

    public function testCaptureMessageBuffersEvent(): void
    {
        Client::init(['dsn' => 'http://key@localhost:9999', 'environment' => 'test']);

        Client::captureMessage('deploy started', 'warning');

        $buffer = Client::getInstance()->getBuffer();
        $this->assertCount(1, $buffer);
        $this->assertSame('deploy started', $buffer[0]['message']);
        $this->assertSame('warning', $buffer[0]['level']);
        $this->assertSame('php', $buffer[0]['platform']);
        $this->assertSame('test', $buffer[0]['environment']);
    }

    public function testCaptureExceptionBuffersEvent(): void
    {
        Client::init(['dsn' => 'http://key@localhost:9999']);

        try {
            throw new \RuntimeException('something broke');
        } catch (\Throwable $e) {
            $id = Client::captureException($e);
        }

        $this->assertNotEmpty($id);

        $buffer = Client::getInstance()->getBuffer();
        $this->assertCount(1, $buffer);
        $this->assertSame('something broke', $buffer[0]['message']);
        $this->assertSame('error', $buffer[0]['level']);
        $this->assertNotNull($buffer[0]['stacktrace']);
        $this->assertNotEmpty($buffer[0]['stacktrace']);

        // First frame should be the throw location
        $firstFrame = $buffer[0]['stacktrace'][0];
        $this->assertSame('(throw)', $firstFrame['function']);
        $this->assertStringContainsString('ClientTest.php', $firstFrame['filename']);
    }

    public function testCaptureWithoutInitReturnsEmpty(): void
    {
        $this->assertSame('', Client::captureMessage('noop'));
        $this->assertSame('', Client::captureException(new \RuntimeException('noop')));
    }

    public function testFlushClearsBuffer(): void
    {
        Client::init(['dsn' => 'http://key@localhost:9999']);

        Client::captureMessage('one');
        Client::captureMessage('two');
        $this->assertCount(2, Client::getInstance()->getBuffer());

        // Flush will fail to send (no server) but should clear buffer
        Client::getInstance()->flush();
        $this->assertCount(0, Client::getInstance()->getBuffer());
    }

    public function testUniqueEventIds(): void
    {
        Client::init(['dsn' => 'http://key@localhost:9999']);

        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $ids[] = Client::captureMessage("msg $i");
        }

        $this->assertCount(100, array_unique($ids));
    }
}
