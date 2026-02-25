<?php

declare(strict_types=1);

namespace Obs;

final class Client
{
    private static ?self $instance = null;

    private string $endpoint;
    private string $apiKey;
    private string $environment;
    private string $serverName;
    /** @var array<int, array<string, mixed>> */
    private array $buffer = [];

    private function __construct(string $endpoint, string $apiKey, string $environment, string $serverName)
    {
        $this->endpoint = $endpoint;
        $this->apiKey = $apiKey;
        $this->environment = $environment;
        $this->serverName = $serverName;
    }

    /**
     * @param array{dsn: string, environment?: string, server_name?: string} $options
     */
    public static function init(array $options): void
    {
        [$endpoint, $apiKey] = self::parseDSN($options['dsn']);

        self::$instance = new self(
            $endpoint,
            $apiKey,
            $options['environment'] ?? '',
            $options['server_name'] ?? gethostname() ?: '',
        );

        register_shutdown_function([self::$instance, 'flush']);
    }

    public static function captureException(\Throwable $e): string
    {
        if (self::$instance === null) {
            return '';
        }

        $frames = [];
        foreach ($e->getTrace() as $frame) {
            $frames[] = [
                'filename' => $frame['file'] ?? '?',
                'function' => isset($frame['class'])
                    ? $frame['class'] . $frame['type'] . $frame['function']
                    : $frame['function'],
                'lineno' => $frame['line'] ?? 0,
            ];
        }

        // Add the throw location as the first frame
        array_unshift($frames, [
            'filename' => $e->getFile(),
            'function' => '(throw)',
            'lineno' => $e->getLine(),
        ]);

        return self::$instance->capture('error', $e->getMessage(), $frames);
    }

    public static function captureMessage(string $message, string $level = 'info'): string
    {
        if (self::$instance === null) {
            return '';
        }

        return self::$instance->capture($level, $message);
    }

    /**
     * @param array<int, array<string, mixed>>|null $stacktrace
     */
    private function capture(string $level, string $message, ?array $stacktrace = null): string
    {
        $id = self::uuid();

        $this->buffer[] = [
            'event_id' => $id,
            'level' => $level,
            'message' => $message,
            'stacktrace' => $stacktrace,
            'platform' => 'php',
            'timestamp' => date('Y-m-d H:i:s'),
            'server_name' => $this->serverName,
            'environment' => $this->environment,
        ];

        return $id;
    }

    public function flush(): void
    {
        foreach ($this->buffer as $event) {
            $this->send($event);
        }
        $this->buffer = [];
    }

    /**
     * @param array<string, mixed> $event
     */
    private function send(array $event): void
    {
        $json = json_encode($event, JSON_THROW_ON_ERROR);
        $url = $this->endpoint . '/api/events';

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nX-OBS-Key: {$this->apiKey}\r\n",
                'content' => $json,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        @file_get_contents($url, false, $context);
    }

    /**
     * @return array{string, string} [endpoint, apiKey]
     */
    public static function parseDSN(string $dsn): array
    {
        $parts = parse_url($dsn);
        if ($parts === false || !isset($parts['scheme'], $parts['host'], $parts['user'])) {
            throw new \InvalidArgumentException('Invalid DSN: must be http(s)://key@host[:port]');
        }

        $apiKey = $parts['user'];
        if ($apiKey === '') {
            throw new \InvalidArgumentException('Invalid DSN: API key must not be empty');
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $endpoint = $parts['scheme'] . '://' . $parts['host'] . $port;

        return [$endpoint, $apiKey];
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /** @internal For testing only. */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /** @internal For testing only. */
    public function getBuffer(): array
    {
        return $this->buffer;
    }

    /** @internal For testing only. */
    public static function getInstance(): ?self
    {
        return self::$instance;
    }
}
